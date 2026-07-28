<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PickupEvent;
use App\Models\PickupEventStudent;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class GatePickupEventParallelConfirmationTest extends TestCase
{
    private string $token;

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $columns = [];

    /** @var array<int, Process> */
    private array $processes = [];

    /** @var array<int, string> */
    private array $runDirectories = [];

    private bool $lockTransactionActive = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardTestingDatabase();
        $this->assertRequiredTables();
        $this->assertParallelRuntime();
        $this->assertInnoDb();

        config()->set('biometrics.security.bind_pickup_confirmation_to_session', false);

        $this->token = 'parallel-'.Str::lower(Str::random(16));
    }

    protected function tearDown(): void
    {
        try {
            $this->stopProcesses();
            $this->rollbackLock();
            $this->cleanupFixtures();
            $this->cleanupRunDirectories();
        } finally {
            parent::tearDown();
        }
    }

    public function test_parallel_identical_requests_create_one_event_and_replay_the_other(): void
    {
        $fixture = $this->createFixture();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'face_verification_attempt_id' => (int) $fixture['attempt']->id,
            'student_ids' => [(int) $fixture['student']->id],
            'notes' => 'Konfirmasi paralel identik '.$this->token,
        ];

        $results = $this->runParallelRequests(
            (int) $fixture['attempt']->id,
            [
                $this->job('identical-a', $fixture['officer'], $payload),
                $this->job('identical-b', $fixture['officer'], $payload),
            ],
        );

        $this->assertSame([200, 201], $this->statuses($results), $this->diagnostics($results));

        $replayed = [
            (bool) data_get($results[0], 'json.replayed', false),
            (bool) data_get($results[1], 'json.replayed', false),
        ];
        sort($replayed);

        $this->assertSame([false, true], $replayed, $this->diagnostics($results));

        $eventIds = array_values(array_unique(array_map(
            static fn (array $result): int => (int) data_get($result, 'json.pickup_event.id', 0),
            $results,
        )));

        $this->assertCount(1, $eventIds, $this->diagnostics($results));
        $this->assertGreaterThan(0, $eventIds[0], $this->diagnostics($results));

        $events = PickupEvent::query()
            ->where('face_verification_attempt_id', $fixture['attempt']->id)
            ->get();

        $this->assertCount(1, $events);

        $event = $events->firstOrFail();

        $this->assertSame($idempotencyKey, (string) $event->idempotency_key);
        $this->assertSame(PickupEvent::STATUS_CONFIRMED, (string) $event->status);
        $this->assertSame(
            1,
            PickupEventStudent::query()->where('pickup_event_id', $event->id)->count(),
        );
    }

    public function test_parallel_requests_with_same_attempt_and_different_keys_create_only_one_event(): void
    {
        $fixture = $this->createFixture();
        $firstKey = (string) Str::uuid();
        $secondKey = (string) Str::uuid();

        $basePayload = [
            'face_verification_attempt_id' => (int) $fixture['attempt']->id,
            'student_ids' => [(int) $fixture['student']->id],
            'notes' => 'Konfirmasi paralel attempt sama '.$this->token,
        ];

        $results = $this->runParallelRequests(
            (int) $fixture['attempt']->id,
            [
                $this->job(
                    'different-key-a',
                    $fixture['officer'],
                    [...$basePayload, 'idempotency_key' => $firstKey],
                ),
                $this->job(
                    'different-key-b',
                    $fixture['officer'],
                    [...$basePayload, 'idempotency_key' => $secondKey],
                ),
            ],
        );

        $this->assertSame([201, 409], $this->statuses($results), $this->diagnostics($results));

        $created = array_values(array_filter(
            $results,
            static fn (array $result): bool => (int) ($result['status'] ?? 0) === 201,
        ));
        $conflicts = array_values(array_filter(
            $results,
            static fn (array $result): bool => (int) ($result['status'] ?? 0) === 409,
        ));

        $this->assertCount(1, $created, $this->diagnostics($results));
        $this->assertCount(1, $conflicts, $this->diagnostics($results));
        $this->assertFalse((bool) data_get($created[0], 'json.replayed', true));

        $events = PickupEvent::query()
            ->where('face_verification_attempt_id', $fixture['attempt']->id)
            ->get();

        $this->assertCount(1, $events);

        $event = $events->firstOrFail();

        $this->assertContains((string) $event->idempotency_key, [$firstKey, $secondKey]);
        $this->assertSame(
            1,
            PickupEventStudent::query()->where('pickup_event_id', $event->id)->count(),
        );
    }

    /**
     * @return array{
     *     school: School,
     *     officer: User,
     *     pickup_person: PickupPerson,
     *     student: Student,
     *     attempt: PickupPersonFaceVerificationAttempt
     * }
     */
    private function createFixture(): array
    {
        $now = now()->format('Y-m-d H:i:s');

        $schoolId = $this->insertWithId('schools', [
            'name' => 'Parallel School '.$this->token,
            'code' => 'PS-'.Str::upper(Str::substr($this->token, -10)),
            'slug' => $this->token,
            'email' => $this->token.'@school.test',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['school'] = $schoolId;

        $officerId = $this->insertWithId('users', [
            'school_id' => $schoolId,
            'name' => 'Parallel Officer '.$this->token,
            'email' => $this->token.'@officer.test',
            'password' => password_hash('TestPassword123!', PASSWORD_BCRYPT),
            'role' => User::ROLE_GATE_OFFICER,
            'roles' => [User::ROLE_GATE_OFFICER],
            'is_active' => true,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['officer'] = $officerId;

        $pickupPersonId = $this->insertWithId('pickup_persons', [
            'school_id' => $schoolId,
            'full_name' => 'Parallel Pickup Person '.$this->token,
            'name' => 'Parallel Pickup Person '.$this->token,
            'phone' => '0812'.random_int(10000000, 99999999),
            'email' => $this->token.'@pickup.test',
            'is_active' => true,
            'face_status' => PickupPerson::FACE_REGISTERED,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['pickup_person'] = $pickupPersonId;

        $classId = $this->insertWithId('school_classes', [
            'school_id' => $schoolId,
            'name' => 'Parallel Class '.$this->token,
            'class_name' => 'Parallel Class '.$this->token,
            'label' => 'Parallel Class '.$this->token,
            'grade_level' => '1',
            'grade' => '1',
            'academic_year' => '2026/2027',
            'school_year' => '2026/2027',
            'year' => '2026',
            'is_active' => true,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['school_class'] = $classId;

        $studentId = $this->insertWithId('students', [
            'school_id' => $schoolId,
            'school_class_id' => $classId,
            'class_id' => $classId,
            'full_name' => 'Parallel Student '.$this->token,
            'name' => 'Parallel Student '.$this->token,
            'student_number' => 'ST-'.Str::upper(Str::substr($this->token, -12)),
            'nis' => Str::upper(Str::substr(md5($this->token), 0, 12)),
            'gender' => $this->preferredValue('students', 'gender', ['L', 'M', 'male', 'laki-laki'], 'L'),
            'status' => Student::STATUS_ACTIVE,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['student'] = $studentId;

        $this->insertWithoutId('pickup_person_student', [
            'school_id' => $schoolId,
            'pickup_person_id' => $pickupPersonId,
            'student_id' => $studentId,
            'relationship_type' => $this->preferredValue(
                'pickup_person_student',
                'relationship_type',
                ['guardian', 'parent', 'father', 'mother', 'other'],
                'guardian',
            ),
            'is_primary' => true,
            'is_active' => true,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $attemptId = $this->insertWithId('pickup_person_face_verification_attempts', [
            'school_id' => $schoolId,
            'pickup_person_id' => $pickupPersonId,
            'verified_by_user_id' => $officerId,
            'result' => PickupPersonFaceVerificationAttempt::RESULT_MATCH,
            'similarity_score' => 0.91,
            'similarity_threshold' => 0.60,
            'candidate_margin' => 0.20,
            'candidate_count' => 1,
            'quality_score' => 0.90,
            'liveness_passed' => true,
            'live_score' => 0.95,
            'real_score' => 0.90,
            'model_name' => 'parallel-test-model',
            'model_version' => '1.0-test',
            'embedding_dimension' => 1024,
            'capture_method' => 'camera',
            'metadata' => [
                'source' => 'parallel_confirmation_test',
                'fixture_token' => $this->token,
            ],
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->ids['attempt'] = $attemptId;

        return [
            'school' => School::query()->findOrFail($schoolId),
            'officer' => User::query()->findOrFail($officerId),
            'pickup_person' => PickupPerson::query()->findOrFail($pickupPersonId),
            'student' => Student::query()->findOrFail($studentId),
            'attempt' => PickupPersonFaceVerificationAttempt::query()->findOrFail($attemptId),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function job(string $requestId, User $user, array $payload): array
    {
        return [
            'request_id' => $requestId.'-'.$this->token,
            'user_id' => (int) $user->id,
            'payload' => $payload,
            'expected_database' => DB::connection()->getDatabaseName(),
            'barrier_timeout_ms' => 20_000,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runParallelRequests(int $attemptId, array $jobs): array
    {
        $this->assertCount(2, $jobs, 'Diperlukan tepat dua job worker.');

        $directory = storage_path(
            'framework/testing/gate-pickup-parallel/'.$this->token.'-'.Str::lower(Str::random(8)),
        );
        $this->makeDirectory($directory);
        $this->runDirectories[] = $directory;

        $releasePath = $directory.DIRECTORY_SEPARATOR.'release.marker';
        $readyPaths = [];
        $resultPaths = [];
        $processes = [];

        foreach ($jobs as $index => $job) {
            $number = $index + 1;
            $jobPath = $directory.DIRECTORY_SEPARATOR."job-{$number}.json";
            $readyPath = $directory.DIRECTORY_SEPARATOR."ready-{$number}.json";
            $resultPath = $directory.DIRECTORY_SEPARATOR."result-{$number}.json";

            $this->writeJson($jobPath, $job);
            $readyPaths[] = $readyPath;
            $resultPaths[] = $resultPath;

            $process = new Process(
                [
                    PHP_BINARY,
                    base_path('tests/Support/GatePickupEventParallelWorker.php'),
                    $jobPath,
                    $resultPath,
                    $readyPath,
                    $releasePath,
                ],
                base_path(),
                $this->workerEnvironment(),
            );
            $process->setTimeout(30);
            $process->setIdleTimeout(20);
            $processes[] = $process;
        }

        $connection = DB::connection();

        try {
            $connection->beginTransaction();
            $this->lockTransactionActive = true;

            $lockedAttempt = PickupPersonFaceVerificationAttempt::query()
                ->whereKey($attemptId)
                ->lockForUpdate()
                ->first();

            $this->assertInstanceOf(
                PickupPersonFaceVerificationAttempt::class,
                $lockedAttempt,
                'Attempt fixture gagal dikunci coordinator.',
            );

            foreach ($processes as $process) {
                $process->start();
                $this->processes[] = $process;
            }

           $this->waitForReadyFiles(
    readyPaths:
        $readyPaths,

    resultPaths:
        $resultPaths,

    processes:
        $processes,

    timeoutMs:
        10_000,
);

            $readyPayloads = array_map(fn (string $path): array => $this->readJson($path), $readyPaths);
            $pids = array_values(array_unique(array_map(
                static fn (array $payload): int => (int) ($payload['pid'] ?? 0),
                $readyPayloads,
            )));
            $this->assertCount(2, $pids, 'Dua worker harus memakai PID berbeda.');

            file_put_contents($releasePath, 'release '.now()->toIso8601String(), LOCK_EX);

            // Kedua worker sekarang melewati barrier dan tertahan pada row lock attempt.
            usleep(750_000);

            foreach ($processes as $index => $process) {
                $this->assertTrue(
                    $process->isRunning(),
                    "Worker ".($index + 1)." selesai sebelum row lock dilepas.\n".$this->processDiagnostics($process),
                );
            }

            $connection->commit();
            $this->lockTransactionActive = false;

            foreach ($processes as $process) {
                $process->wait();
            }

            foreach ($processes as $index => $process) {
                $this->assertTrue(
                    $process->isSuccessful(),
                    "Worker ".($index + 1)." gagal.\n".$this->processDiagnostics($process),
                );
            }

            $results = array_map(fn (string $path): array => $this->readJson($path), $resultPaths);

            foreach ($results as $index => $result) {
                $this->assertTrue(
                    (bool) ($result['completed'] ?? false),
                    "Worker ".($index + 1)." tidak selesai.\n".$this->diagnostics($results),
                );
                $this->assertSame(
                    DB::connection()->getDatabaseName(),
                    (string) ($result['database'] ?? ''),
                    'Worker memakai database yang salah.',
                );
            }

            return array_values($results);
        } catch (Throwable $exception) {
            $this->rollbackLock();

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            throw $exception;
        } finally {
            $this->processes = [];
        }
    }

    /** @param array<int, string> $readyPaths @param array<int, Process> $processes */
    /**
 * @param array<int, string> $readyPaths
 * @param array<int, string> $resultPaths
 * @param array<int, Process> $processes
 */
private function waitForReadyFiles(
    array $readyPaths,
    array $resultPaths,
    array $processes,
    int $timeoutMs,
): void {
    $startedAt =
        hrtime(true);

    while (true) {
        $allReady =
            true;

        foreach ($readyPaths as $readyPath) {
            clearstatcache(
                true,
                $readyPath,
            );

            if (! is_file($readyPath)) {
                $allReady =
                    false;

                break;
            }
        }

        if ($allReady) {
            return;
        }

        foreach ($processes as $index => $process) {
            if ($process->isRunning()) {
                continue;
            }

            $process->wait();

            $resultDiagnostic =
                $this->workerResultDiagnostic(
                    $resultPaths[
                        $index
                    ] ?? '',
                );

            throw new RuntimeException(
                sprintf(
                    "Worker %d berhenti sebelum ready.\n%s\nRESULT FILE:\n%s",
                    $index + 1,
                    $this->processDiagnostics(
                        $process,
                    ),
                    $resultDiagnostic,
                ),
            );
        }

        $elapsedMilliseconds =
            (int) (
                (
                    hrtime(true)
                    - $startedAt
                )
                / 1_000_000
            );

        if (
            $elapsedMilliseconds
            >= $timeoutMs
        ) {
            $diagnostics = [];

            foreach ($processes as $index => $process) {
                $diagnostics[] =
                    sprintf(
                        "WORKER %d\n%s\nRESULT FILE:\n%s",
                        $index + 1,
                        $this->processDiagnostics(
                            $process,
                        ),
                        $this->workerResultDiagnostic(
                            $resultPaths[
                                $index
                            ] ?? '',
                        ),
                    );
            }

            throw new RuntimeException(
                sprintf(
                    "Dua worker tidak ready dalam %d ms.\n\n%s",
                    $timeoutMs,
                    implode(
                        "\n\n",
                        $diagnostics,
                    ),
                ),
            );
        }

        usleep(
            10_000,
        );
    }
}

private function workerResultDiagnostic(
    string $resultPath,
): string {
    if (
        trim($resultPath) === ''
        || ! is_file($resultPath)
    ) {
        return sprintf(
            'Result file belum tersedia: %s',
            $resultPath !== ''
                ? $resultPath
                : '[path kosong]',
        );
    }

    try {
        return json_encode(
            $this->readJson(
                $resultPath,
            ),
            JSON_THROW_ON_ERROR
            | JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        );
    } catch (Throwable $exception) {
        $rawContents =
            file_get_contents(
                $resultPath,
            );

        return sprintf(
            "Result file tidak dapat diparse: %s\nRAW:\n%s",
            $exception->getMessage(),
            $rawContents !== false
                ? $rawContents
                : '[tidak dapat dibaca]',
        );
    }
}

    /** @return array<string, string> */
    private function workerEnvironment(): array
    {
        $name = DB::getDefaultConnection();
        $config = (array) config('database.connections.'.$name, []);

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key', ''),
            'APP_DEBUG' => 'true',
            'DB_CONNECTION' => $name,
            'DB_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($config['port'] ?? '3306'),
            'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
            'DB_USERNAME' => (string) ($config['username'] ?? ''),
            'DB_PASSWORD' => (string) ($config['password'] ?? ''),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    /** @param array<int, array<string, mixed>> $results @return array<int, int> */
    private function statuses(array $results): array
    {
        $statuses = array_map(
            static fn (array $result): int => (int) ($result['status'] ?? 0),
            $results,
        );
        sort($statuses);

        return array_values($statuses);
    }

    /** @param array<int, array<string, mixed>> $results */
    private function diagnostics(array $results): string
    {
        try {
            return json_encode(
                $results,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable) {
            return var_export($results, true);
        }
    }

    private function processDiagnostics(Process $process): string
    {
        return sprintf(
            "Command: %s\nExit: %s\nSTDOUT:\n%s\nSTDERR:\n%s",
            $process->getCommandLine(),
            var_export($process->getExitCode(), true),
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }

    private function insertWithId(string $table, array $overrides): int
    {
        return (int) DB::table($table)->insertGetId($this->fixtureAttributes($table, $overrides));
    }

    private function insertWithoutId(string $table, array $overrides): void
    {
        DB::table($table)->insert($this->fixtureAttributes($table, $overrides));
    }

    /** @return array<string, mixed> */
    private function fixtureAttributes(string $table, array $overrides): array
    {
        $attributes = [];

        foreach ($this->columnsFor($table) as $column) {
            $name = (string) $column['COLUMN_NAME'];
            $extra = strtolower((string) ($column['EXTRA'] ?? ''));

            if (str_contains($extra, 'auto_increment') || str_contains($extra, 'generated')) {
                continue;
            }

            if (array_key_exists($name, $overrides)) {
                $attributes[$name] = $this->normalizeValue($column, $overrides[$name]);
                continue;
            }

            $nullable = strtoupper((string) ($column['IS_NULLABLE'] ?? 'NO')) === 'YES';
            $default = $column['COLUMN_DEFAULT'] ?? null;

            if ($nullable || $default !== null) {
                continue;
            }

            $attributes[$name] = $this->requiredValue($table, $column);
        }

        return $attributes;
    }

    /** @return array<int, array<string, mixed>> */
    private function columnsFor(string $table): array
    {
        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $rows = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
            .'CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table],
        );

        if ($rows === []) {
            throw new RuntimeException("Metadata tabel [{$table}] tidak ditemukan.");
        }

        return $this->columns[$table] = array_map(
            static fn (object $row): array => (array) $row,
            $rows,
        );
    }

    private function requiredValue(string $table, array $column): mixed
    {
        $name = (string) $column['COLUMN_NAME'];
        $type = strtolower((string) $column['DATA_TYPE']);
        $enum = $this->enumValues((string) $column['COLUMN_TYPE']);

        if ($enum !== []) {
            return $enum[0];
        }

        if ($name !== 'id' && str_ends_with($name, '_id')) {
            throw new RuntimeException("Fixture [{$table}] membutuhkan foreign key wajib [{$name}].");
        }

        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'bit', 'boolean'], true)) {
            return str_starts_with($name, 'is_') ? 1 : 0;
        }

        if (in_array($type, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
            return 0;
        }

        if ($type === 'date') {
            return now()->toDateString();
        }

        if ($type === 'year') {
            return (int) now()->format('Y');
        }

        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return now()->format('Y-m-d H:i:s');
        }

        if ($type === 'time') {
            return now()->format('H:i:s');
        }

        if ($type === 'json') {
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        return $this->truncate($column, $this->token.'-'.$name);
    }

    private function normalizeValue(
    array $column,
    mixed $value,
): mixed {
    if ($value === null) {
        return null;
    }

    /*
     * Query Builder tidak menjalankan cast model Eloquent.
     *
     * MariaDB dapat melaporkan kolom JSON sebagai LONGTEXT,
     * sehingga pemeriksaan DATA_TYPE === 'json' saja tidak cukup.
     * Semua array fixture harus diubah menjadi JSON sebelum
     * diteruskan ke PDO.
     */
    if (is_array($value)) {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        );
    }

    $type =
        strtolower(
            (string) (
                $column[
                    'DATA_TYPE'
                ]
                ?? ''
            ),
        );

    $columnType =
        strtolower(
            (string) (
                $column[
                    'COLUMN_TYPE'
                ]
                ?? ''
            ),
        );

    $enum =
        $this->enumValues(
            $columnType,
        );

    if ($enum !== []) {
        $normalized =
            (string) $value;

        return in_array(
            $normalized,
            $enum,
            true,
        )
            ? $normalized
            : $enum[0];
    }

    if (is_bool($value)) {
        return $value
            ? 1
            : 0;
    }

    /*
     * Object JSON-serializable juga diamankan. Carbon atau tanggal
     * pada fixture ini sudah dikirim sebagai string, sehingga blok
     * ini terutama melindungi metadata berbentuk DTO/object.
     */
    if (
        $value instanceof \JsonSerializable
    ) {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        );
    }

    if (
        is_string($value)
        && in_array(
            $type,
            [
                'char',
                'varchar',
            ],
            true,
        )
    ) {
        return $this->truncate(
            $column,
            $value,
        );
    }

    return $value;
}

    private function preferredValue(
        string $table,
        string $columnName,
        array $preferred,
        mixed $fallback,
    ): mixed {
        foreach ($this->columnsFor($table) as $column) {
            if ((string) $column['COLUMN_NAME'] !== $columnName) {
                continue;
            }

            $enum = $this->enumValues((string) $column['COLUMN_TYPE']);
            if ($enum !== []) {
                foreach ($preferred as $value) {
                    if (in_array((string) $value, $enum, true)) {
                        return (string) $value;
                    }
                }
                return $enum[0];
            }

            if ((int) ($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0) === 1) {
                foreach ($preferred as $value) {
                    if (mb_strlen((string) $value) === 1) {
                        return (string) $value;
                    }
                }
            }

            return $this->normalizeValue($column, $fallback);
        }

        return $fallback;
    }

    /** @return array<int, string> */
    private function enumValues(string $columnType): array
    {
        $lower = strtolower($columnType);
        if (! str_starts_with($lower, 'enum(') && ! str_starts_with($lower, 'set(')) {
            return [];
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $columnType, $matches);

        return array_values(array_map(
            static fn (string $value): string => stripcslashes($value),
            $matches[1] ?? [],
        ));
    }

    private function truncate(array $column, string $value): string
    {
        $max = (int) ($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        return $max > 0 && mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private function guardTestingDatabase(): void
    {
        $connection = DB::connection();
        $database = strtolower(trim((string) $connection->getDatabaseName()));

        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertTrue(str_ends_with($database, '_test'), "Database non-testing ditolak: {$database}");
        $this->assertSame(0, $connection->transactionLevel(), 'Jangan pakai DatabaseTransactions pada test ini.');
    }

    private function assertRequiredTables(): void
    {
        foreach ([
            'schools',
            'users',
            'pickup_persons',
            'school_classes',
            'students',
            'pickup_person_student',
            'pickup_person_face_verification_attempts',
            'pickup_events',
            'pickup_event_students',
        ] as $table) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($table), "Tabel [{$table}] belum tersedia.");
        }
    }

    private function assertParallelRuntime(): void
    {
        $this->assertTrue(function_exists('proc_open'), 'proc_open diperlukan.');
        $this->assertTrue(class_exists(Process::class), 'Symfony Process belum tersedia.');
        $this->assertFileExists(base_path('tests/Support/GatePickupEventParallelWorker.php'));
    }

    private function assertInnoDb(): void
    {
        foreach ([
            'pickup_person_face_verification_attempts',
            'pickup_events',
            'pickup_event_students',
        ] as $table) {
            $row = DB::selectOne(
                'SELECT ENGINE FROM information_schema.TABLES '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            );

            $this->assertSame(
                'innodb',
                strtolower((string) ($row->ENGINE ?? '')),
                "Tabel [{$table}] harus InnoDB.",
            );
        }
    }

    private function rollbackLock(): void
    {
        if (! $this->lockTransactionActive) {
            return;
        }

        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }

        $this->lockTransactionActive = false;
    }

    private function stopProcesses(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        $this->processes = [];
    }

    private function cleanupFixtures(): void
    {
        $attemptId = $this->ids['attempt'] ?? null;
        $pickupPersonId = $this->ids['pickup_person'] ?? null;
        $studentId = $this->ids['student'] ?? null;
        $classId = $this->ids['school_class'] ?? null;
        $officerId = $this->ids['officer'] ?? null;
        $schoolId = $this->ids['school'] ?? null;

        if ($attemptId !== null) {
            $eventIds = DB::table('pickup_events')
                ->where('face_verification_attempt_id', $attemptId)
                ->pluck('id')
                ->all();

            if ($eventIds !== []) {
                DB::table('pickup_event_students')->whereIn('pickup_event_id', $eventIds)->delete();
                DB::table('pickup_events')->whereIn('id', $eventIds)->delete();
            }

            DB::table('pickup_person_face_verification_attempts')->where('id', $attemptId)->delete();
        }

        if ($pickupPersonId !== null || $studentId !== null) {
            $pivot = DB::table('pickup_person_student');
            if ($pickupPersonId !== null) {
                $pivot->where('pickup_person_id', $pickupPersonId);
            }
            if ($studentId !== null) {
                $pivot->where('student_id', $studentId);
            }
            $pivot->delete();
        }

        if ($studentId !== null) {
            DB::table('students')->where('id', $studentId)->delete();
        }
        if ($classId !== null) {
            DB::table('school_classes')->where('id', $classId)->delete();
        }
        if ($pickupPersonId !== null) {
            DB::table('pickup_persons')->where('id', $pickupPersonId)->delete();
        }
        if ($officerId !== null) {
            DB::table('users')->where('id', $officerId)->delete();
        }
        if ($schoolId !== null) {
            DB::table('schools')->where('id', $schoolId)->delete();
        }

        $this->ids = [];
    }

    private function makeDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Direktori tidak dapat dibuat: {$directory}");
        }
    }

    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("File tidak dapat ditulis: {$path}");
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File JSON tidak ditemukan: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("File JSON tidak dapat dibaca: {$path}");
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Isi file bukan object JSON: {$path}");
        }

        return $decoded;
    }

    private function cleanupRunDirectories(): void
    {
        foreach ($this->runDirectories as $directory) {
            $this->deleteDirectory($directory);
        }
        $this->runDirectories = [];
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
