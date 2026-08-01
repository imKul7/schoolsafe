<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PickupEvent;
use App\Models\PickupEventStudent;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class GatePickupEventParallelCancellationTest extends TestCase
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

        $this->token =
            'parallel-cancel-'
            .Str::lower(
                Str::random(16),
            );
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

    public function test_parallel_event_cancellations_allow_only_one_success(): void
    {
        $fixture =
            $this->createConfirmedEventFixture();

        /** @var User $officer */
        $officer =
            $fixture['officer'];

        /** @var PickupEvent $event */
        $event =
            $fixture['event'];

        /** @var PickupEventStudent $eventStudent */
        $eventStudent =
            $fixture['event_student'];

        $reasonA =
            'Pembatalan paralel A';

        $reasonB =
            'Pembatalan paralel B';

        $path =
            "/gate/pickup-events/{$event->id}/cancel";

        $results =
            $this->runParallelCancellations(
                eventId: (int) $event->id,

                jobs: [
                    $this->job(
                        requestId: 'event-cancel-a',

                        user: $officer,

                        path: $path,

                        payload: [
                            'reason' => $reasonA,
                        ],
                    ),

                    $this->job(
                        requestId: 'event-cancel-b',

                        user: $officer,

                        path: $path,

                        payload: [
                            'reason' => $reasonB,
                        ],
                    ),
                ],
            );

        $this->assertSame(
            [
                200,
                409,
            ],
            $this->statuses(
                $results,
            ),
            $this->diagnostics(
                $results,
            ),
        );

        $successfulResults =
            array_values(
                array_filter(
                    $results,
                    static fn (
                        array $result,
                    ): bool => (int) (
                        $result['status']
                        ?? 0
                    ) === 200,
                ),
            );

        $conflictResults =
            array_values(
                array_filter(
                    $results,
                    static fn (
                        array $result,
                    ): bool => (int) (
                        $result['status']
                        ?? 0
                    ) === 409,
                ),
            );

        $this->assertCount(
            1,
            $successfulResults,
            $this->diagnostics(
                $results,
            ),
        );

        $this->assertCount(
            1,
            $conflictResults,
            $this->diagnostics(
                $results,
            ),
        );

        $this->assertSame(
            PickupEvent::STATUS_CANCELLED,
            (string) data_get(
                $successfulResults[0],
                'json.pickup_event.status',
                '',
            ),
            $this->diagnostics(
                $results,
            ),
        );

        $event->refresh();
        $eventStudent->refresh();

        $this->assertCancelledAuditIsConsistent(
            event: $event,

            eventStudent: $eventStudent,

            officer: $officer,

            allowedReasons: [
                $reasonA,
                $reasonB,
            ],
        );
    }

    public function test_parallel_event_and_single_student_cancellation_allow_only_one_success(): void
    {
        $fixture =
            $this->createConfirmedEventFixture();

        /** @var User $officer */
        $officer =
            $fixture['officer'];

        /** @var PickupEvent $event */
        $event =
            $fixture['event'];

        /** @var PickupEventStudent $eventStudent */
        $eventStudent =
            $fixture['event_student'];

        $eventReason =
            'Pembatalan seluruh transaksi';

        $studentReason =
            'Pembatalan siswa tunggal';

        $results =
            $this->runParallelCancellations(
                eventId: (int) $event->id,

                jobs: [
                    $this->job(
                        requestId: 'whole-event-cancel',

                        user: $officer,

                        path: "/gate/pickup-events/{$event->id}/cancel",

                        payload: [
                            'reason' => $eventReason,
                        ],
                    ),

                    $this->job(
                        requestId: 'single-student-cancel',

                        user: $officer,

                        path: "/gate/pickup-events/{$event->id}/students/{$eventStudent->id}/cancel",

                        payload: [
                            'reason' => $studentReason,
                        ],
                    ),
                ],
            );

        $this->assertSame(
            [
                200,
                409,
            ],
            $this->statuses(
                $results,
            ),
            $this->diagnostics(
                $results,
            ),
        );

        $successfulResults =
            array_values(
                array_filter(
                    $results,
                    static fn (
                        array $result,
                    ): bool => (int) (
                        $result['status']
                        ?? 0
                    ) === 200,
                ),
            );

        $this->assertCount(
            1,
            $successfulResults,
            $this->diagnostics(
                $results,
            ),
        );

        $this->assertSame(
            PickupEvent::STATUS_CANCELLED,
            (string) data_get(
                $successfulResults[0],
                'json.pickup_event.status',
                '',
            ),
            $this->diagnostics(
                $results,
            ),
        );

        $event->refresh();
        $eventStudent->refresh();

        $this->assertCancelledAuditIsConsistent(
            event: $event,

            eventStudent: $eventStudent,

            officer: $officer,

            allowedReasons: [
                $eventReason,
                $studentReason,
            ],
        );
    }

    /**
     * @return array{
     *     school: School,
     *     officer: User,
     *     event: PickupEvent,
     *     event_student: PickupEventStudent
     * }
     */
    private function createConfirmedEventFixture(): array
    {
        $now =
            now()->format(
                'Y-m-d H:i:s',
            );

        $schoolId =
            $this->insertWithId(
                'schools',
                [
                    'name' => 'Parallel Cancellation School '
                        .$this->token,

                    'code' => 'PCS-'
                        .Str::upper(
                            Str::substr(
                                md5(
                                    $this->token,
                                ),
                                0,
                                10,
                            ),
                        ),

                    'slug' => $this->token,

                    'email' => $this->token
                        .'@school.test',

                    'timezone' => 'Asia/Jakarta',

                    'is_active' => true,

                    'status' => 'active',

                    'created_at' => $now,

                    'updated_at' => $now,
                ],
            );

        $this->ids['school'] =
            $schoolId;

        $officerId =
            $this->insertWithId(
                'users',
                [
                    'school_id' => $schoolId,

                    'name' => 'Parallel Cancellation Officer '
                        .$this->token,

                    'email' => $this->token
                        .'@officer.test',

                    'password' => password_hash(
                        'TestPassword123!',
                        PASSWORD_BCRYPT,
                    ),

                    'role' => User::ROLE_GATE_OFFICER,

                    'roles' => [
                        User::ROLE_GATE_OFFICER,
                    ],

                    'is_active' => true,

                    'email_verified_at' => $now,

                    'created_at' => $now,

                    'updated_at' => $now,
                ],
            );

        $this->ids['officer'] =
            $officerId;

        $eventId =
            $this->insertWithId(
                'pickup_events',
                [
                    'school_id' => $schoolId,

                    'pickup_person_id' => null,

                    'face_verification_attempt_id' => null,

                    'confirmed_by_user_id' => $officerId,

                    'cancelled_by_user_id' => null,

                    'idempotency_key' => (string) Str::uuid(),

                    'verification_method' => PickupEvent::VERIFICATION_METHOD_MANUAL,

                    'status' => PickupEvent::STATUS_CONFIRMED,

                    'pickup_person_name' => 'Penjemput Parallel '
                        .$this->token,

                    'pickup_person_phone' => '080000000000',

                    'verification_result' => PickupPersonFaceVerificationAttempt::RESULT_MATCH,

                    'similarity_score' => null,

                    'similarity_threshold' => null,

                    'candidate_margin' => null,

                    'confirmed_at' => $now,

                    'cancelled_at' => null,

                    'cancellation_reason' => null,

                    'notes' => 'Fixture pembatalan paralel '
                        .$this->token,

                    'ip_address' => '127.0.0.1',

                    'user_agent' => 'SchoolSafe Parallel Cancellation Test',

                    'metadata' => [
                        'source' => 'parallel_cancellation_test',

                        'fixture_token' => $this->token,
                    ],

                    'created_at' => $now,

                    'updated_at' => $now,
                ],
            );

        $this->ids['event'] =
            $eventId;

        $eventStudentId =
            $this->insertWithId(
                'pickup_event_students',
                [
                    'pickup_event_id' => $eventId,

                    'student_id' => null,

                    'student_name' => 'Siswa Parallel '
                        .$this->token,

                    'student_number' => 'PAR-CANCEL-001',

                    'class_name' => 'Kelas Parallel',

                    'academic_year' => '2026/2027',

                    'relationship_type' => $this->preferredValue(
                        'pickup_event_students',
                        'relationship_type',
                        [
                            'guardian',
                            'parent',
                            'father',
                            'mother',
                            'other',
                        ],
                        'guardian',
                    ),

                    'is_primary' => true,

                    'status' => PickupEventStudent::STATUS_RELEASED,

                    'released_at' => $now,

                    'cancelled_at' => null,

                    'cancelled_by_user_id' => null,

                    'cancellation_reason' => null,

                    'created_at' => $now,

                    'updated_at' => $now,
                ],
            );

        $this->ids['event_student'] =
            $eventStudentId;

        return [
            'school' => School::query()
                ->findOrFail(
                    $schoolId,
                ),

            'officer' => User::query()
                ->findOrFail(
                    $officerId,
                ),

            'event' => PickupEvent::query()
                ->findOrFail(
                    $eventId,
                ),

            'event_student' => PickupEventStudent::query()
                ->findOrFail(
                    $eventStudentId,
                ),
        ];
    }

    /**
     * @param  array<int, string>  $allowedReasons
     */
    private function assertCancelledAuditIsConsistent(
        PickupEvent $event,
        PickupEventStudent $eventStudent,
        User $officer,
        array $allowedReasons,
    ): void {
        $this->assertSame(
            PickupEvent::STATUS_CANCELLED,
            (string) $event->status,
        );

        $this->assertSame(
            PickupEventStudent::STATUS_CANCELLED,
            (string) $eventStudent->status,
        );

        $this->assertSame(
            (int) $officer->id,
            (int) $event->cancelled_by_user_id,
        );

        $this->assertSame(
            (int) $officer->id,
            (int) $eventStudent->cancelled_by_user_id,
        );

        $this->assertContains(
            (string) $event->cancellation_reason,
            $allowedReasons,
        );

        $this->assertSame(
            (string) $event->cancellation_reason,
            (string) $eventStudent->cancellation_reason,
        );

        $this->assertNotNull(
            $event->cancelled_at,
        );

        $this->assertNotNull(
            $eventStudent->cancelled_at,
        );

        $this->assertSame(
            $event->cancelled_at
                ?->format(
                    'Y-m-d H:i:s',
                ),
            $eventStudent->cancelled_at
                ?->format(
                    'Y-m-d H:i:s',
                ),
        );

        $this->assertSame(
            0,
            PickupEventStudent::query()
                ->where(
                    'pickup_event_id',
                    $event->id,
                )
                ->where(
                    'status',
                    PickupEventStudent::STATUS_RELEASED,
                )
                ->count(),
        );

        $this->assertSame(
            1,
            PickupEventStudent::query()
                ->where(
                    'pickup_event_id',
                    $event->id,
                )
                ->where(
                    'status',
                    PickupEventStudent::STATUS_CANCELLED,
                )
                ->count(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function job(
        string $requestId,
        User $user,
        string $path,
        array $payload,
    ): array {
        return [
            'request_id' => $requestId
                .'-'
                .$this->token,

            'user_id' => (int) $user->id,

            'method' => 'PATCH',

            'path' => $path,

            'payload' => $payload,

            'expected_database' => DB::connection()
                ->getDatabaseName(),

            'barrier_timeout_ms' => 20_000,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function runParallelCancellations(
        int $eventId,
        array $jobs,
    ): array {
        $this->assertCount(
            2,
            $jobs,
            'Diperlukan tepat dua cancellation worker.',
        );

        $directory =
            storage_path(
                'framework/testing/gate-pickup-parallel-cancellation/'
                .$this->token
                .'-'
                .Str::lower(
                    Str::random(8),
                ),
            );

        $this->makeDirectory(
            $directory,
        );

        $this->runDirectories[] =
            $directory;

        $releasePath =
            $directory
            .DIRECTORY_SEPARATOR
            .'release.marker';

        $readyPaths = [];
        $resultPaths = [];
        $processes = [];

        foreach ($jobs as $index => $job) {
            $number =
                $index + 1;

            $jobPath =
                $directory
                .DIRECTORY_SEPARATOR
                ."job-{$number}.json";

            $readyPath =
                $directory
                .DIRECTORY_SEPARATOR
                ."ready-{$number}.json";

            $resultPath =
                $directory
                .DIRECTORY_SEPARATOR
                ."result-{$number}.json";

            $this->writeJson(
                $jobPath,
                $job,
            );

            $readyPaths[] =
                $readyPath;

            $resultPaths[] =
                $resultPath;

            $process =
                new Process(
                    [
                        PHP_BINARY,
                        base_path(
                            'tests/Support/GatePickupEventParallelCancellationWorker.php',
                        ),
                        $jobPath,
                        $resultPath,
                        $readyPath,
                        $releasePath,
                    ],
                    base_path(),
                    $this->workerEnvironment(),
                );

            $process->setTimeout(
                30,
            );

            $process->setIdleTimeout(
                20,
            );

            $processes[] =
                $process;
        }

        $connection =
            DB::connection();

        try {
            $connection->beginTransaction();
            $this->lockTransactionActive =
                true;

            $lockedEvent =
                PickupEvent::query()
                    ->whereKey(
                        $eventId,
                    )
                    ->lockForUpdate()
                    ->first();

            $this->assertInstanceOf(
                PickupEvent::class,
                $lockedEvent,
                'Event fixture gagal dikunci coordinator.',
            );

            foreach ($processes as $process) {
                $process->start();
                $this->processes[] =
                    $process;
            }

            $this->waitForReadyFiles(
                readyPaths: $readyPaths,

                resultPaths: $resultPaths,

                processes: $processes,

                timeoutMs: 10_000,
            );

            $readyPayloads =
                array_map(
                    fn (
                        string $path,
                    ): array => $this->readJson(
                        $path,
                    ),
                    $readyPaths,
                );

            $pids =
                array_values(
                    array_unique(
                        array_map(
                            static fn (
                                array $payload,
                            ): int => (int) (
                                $payload['pid']
                                ?? 0
                            ),
                            $readyPayloads,
                        ),
                    ),
                );

            $this->assertCount(
                2,
                $pids,
                'Dua worker harus memakai PID berbeda.',
            );

            $this->assertNotSame(
                $pids[0],
                $pids[1],
                'Dua worker harus berjalan pada proses berbeda.',
            );

            if (
                file_put_contents(
                    $releasePath,
                    'release '
                    .now()->toIso8601String(),
                    LOCK_EX,
                ) === false
            ) {
                throw new RuntimeException(
                    'Release marker tidak dapat ditulis.',
                );
            }

            /*
             * Kedua worker sudah melewati barrier dan akan tertahan
             * pada row lock parent event milik coordinator.
             */
            usleep(
                750_000,
            );

            foreach ($processes as $index => $process) {
                $this->assertTrue(
                    $process->isRunning(),
                    'Worker '
                    .($index + 1)
                    ." selesai sebelum row lock dilepas.\n"
                    .$this->processDiagnostics(
                        $process,
                    ),
                );
            }

            $connection->commit();
            $this->lockTransactionActive =
                false;

            foreach ($processes as $process) {
                $process->wait();
            }

            foreach ($processes as $index => $process) {
                $this->assertTrue(
                    $process->isSuccessful(),
                    'Worker '
                    .($index + 1)
                    ." gagal.\n"
                    .$this->processDiagnostics(
                        $process,
                    ),
                );
            }

            $results =
                array_map(
                    fn (
                        string $path,
                    ): array => $this->readJson(
                        $path,
                    ),
                    $resultPaths,
                );

            foreach ($results as $index => $result) {
                $this->assertTrue(
                    (bool) (
                        $result['completed']
                        ?? false
                    ),
                    'Worker '
                    .($index + 1)
                    ." tidak selesai.\n"
                    .$this->diagnostics(
                        $results,
                    ),
                );

                $this->assertSame(
                    DB::connection()
                        ->getDatabaseName(),
                    (string) (
                        $result['database']
                        ?? ''
                    ),
                    'Worker memakai database yang salah.',
                );
            }

            return array_values(
                $results,
            );
        } catch (Throwable $exception) {
            $this->rollbackLock();

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(
                        1,
                    );
                }
            }

            throw $exception;
        } finally {
            $this->processes = [];
        }
    }

    /**
     * @param  array<int, string>  $readyPaths
     * @param  array<int, string>  $resultPaths
     * @param  array<int, Process>  $processes
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

                throw new RuntimeException(
                    sprintf(
                        "Worker %d berhenti sebelum ready.\n%s\nRESULT FILE:\n%s",
                        $index + 1,
                        $this->processDiagnostics(
                            $process,
                        ),
                        $this->workerResultDiagnostic(
                            $resultPaths[
                                $index
                            ] ?? '',
                        ),
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
            trim(
                $resultPath,
            ) === ''
            || ! is_file(
                $resultPath,
            )
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
        $connectionName =
            DB::getDefaultConnection();

        $connectionConfig =
            (array) config(
                'database.connections.'
                .$connectionName,
                [],
            );

        return [
            'APP_ENV' => 'testing',

            'APP_KEY' => (string) config(
                'app.key',
                '',
            ),

            'APP_DEBUG' => 'true',

            'DB_CONNECTION' => $connectionName,

            'DB_HOST' => (string) (
                $connectionConfig['host']
                ?? '127.0.0.1'
            ),

            'DB_PORT' => (string) (
                $connectionConfig['port']
                ?? '3306'
            ),

            'DB_DATABASE' => (string) DB::connection()
                ->getDatabaseName(),

            'DB_USERNAME' => (string) (
                $connectionConfig['username']
                ?? ''
            ),

            'DB_PASSWORD' => (string) (
                $connectionConfig['password']
                ?? ''
            ),

            'CACHE_STORE' => 'array',

            'SESSION_DRIVER' => 'array',

            'QUEUE_CONNECTION' => 'sync',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, int>
     */
    private function statuses(
        array $results,
    ): array {
        $statuses =
            array_map(
                static fn (
                    array $result,
                ): int => (int) (
                    $result['status']
                    ?? 0
                ),
                $results,
            );

        sort(
            $statuses,
        );

        return array_values(
            $statuses,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function diagnostics(
        array $results,
    ): string {
        try {
            return json_encode(
                $results,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable) {
            return var_export(
                $results,
                true,
            );
        }
    }

    private function processDiagnostics(
        Process $process,
    ): string {
        return sprintf(
            "Command: %s\nExit: %s\nSTDOUT:\n%s\nSTDERR:\n%s",
            $process->getCommandLine(),
            var_export(
                $process->getExitCode(),
                true,
            ),
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }

    private function insertWithId(
        string $table,
        array $overrides,
    ): int {
        return (int) DB::table(
            $table,
        )->insertGetId(
            $this->fixtureAttributes(
                $table,
                $overrides,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureAttributes(
        string $table,
        array $overrides,
    ): array {
        $attributes = [];

        foreach (
            $this->columnsFor(
                $table,
            ) as $column
        ) {
            $name =
                (string) $column[
                    'COLUMN_NAME'
                ];

            $extra =
                strtolower(
                    (string) (
                        $column['EXTRA']
                        ?? ''
                    ),
                );

            if (
                str_contains(
                    $extra,
                    'auto_increment',
                )
                || str_contains(
                    $extra,
                    'generated',
                )
            ) {
                continue;
            }

            if (
                array_key_exists(
                    $name,
                    $overrides,
                )
            ) {
                $attributes[$name] =
                    $this->normalizeValue(
                        $column,
                        $overrides[$name],
                    );

                continue;
            }

            $nullable =
                strtoupper(
                    (string) (
                        $column['IS_NULLABLE']
                        ?? 'NO'
                    ),
                ) === 'YES';

            $default =
                $column['COLUMN_DEFAULT']
                ?? null;

            if (
                $nullable
                || $default !== null
            ) {
                continue;
            }

            $attributes[$name] =
                $this->requiredValue(
                    $table,
                    $column,
                );
        }

        return $attributes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columnsFor(
        string $table,
    ): array {
        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $rows =
            DB::select(
                'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
                .'CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [
                    $table,
                ],
            );

        if ($rows === []) {
            throw new RuntimeException(
                "Metadata tabel [{$table}] tidak ditemukan.",
            );
        }

        return $this->columns[$table] =
            array_map(
                static fn (
                    object $row,
                ): array => (array) $row,
                $rows,
            );
    }

    private function requiredValue(
        string $table,
        array $column,
    ): mixed {
        $name =
            (string) $column[
                'COLUMN_NAME'
            ];

        $type =
            strtolower(
                (string) $column[
                    'DATA_TYPE'
                ],
            );

        $enum =
            $this->enumValues(
                (string) $column[
                    'COLUMN_TYPE'
                ],
            );

        if ($enum !== []) {
            return $enum[0];
        }

        if (
            in_array(
                $type,
                [
                    'tinyint',
                    'smallint',
                    'mediumint',
                    'int',
                    'integer',
                    'bigint',
                    'bit',
                    'boolean',
                ],
                true,
            )
        ) {
            return str_starts_with(
                $name,
                'is_',
            )
                ? 1
                : 0;
        }

        if (
            in_array(
                $type,
                [
                    'decimal',
                    'numeric',
                    'float',
                    'double',
                    'real',
                ],
                true,
            )
        ) {
            return 0;
        }

        if ($type === 'date') {
            return now()->toDateString();
        }

        if ($type === 'year') {
            return (int) now()->format(
                'Y',
            );
        }

        if (
            in_array(
                $type,
                [
                    'datetime',
                    'timestamp',
                ],
                true,
            )
        ) {
            return now()->format(
                'Y-m-d H:i:s',
            );
        }

        if ($type === 'time') {
            return now()->format(
                'H:i:s',
            );
        }

        if ($type === 'json') {
            return json_encode(
                [],
                JSON_THROW_ON_ERROR,
            );
        }

        return $this->truncate(
            $column,
            $this->token
            .'-'
            .$table
            .'-'
            .$name,
        );
    }

    private function normalizeValue(
        array $column,
        mixed $value,
    ): mixed {
        if ($value === null) {
            return null;
        }

        /*
         * Query Builder tidak menjalankan cast Eloquent. MariaDB juga
         * dapat melaporkan JSON sebagai LONGTEXT, jadi semua array harus
         * diubah menjadi JSON sebelum dikirim ke PDO.
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
                    $column['DATA_TYPE']
                    ?? ''
                ),
            );

        $columnType =
            strtolower(
                (string) (
                    $column['COLUMN_TYPE']
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
        foreach (
            $this->columnsFor(
                $table,
            ) as $column
        ) {
            if (
                (string) $column[
                    'COLUMN_NAME'
                ] !== $columnName
            ) {
                continue;
            }

            $enum =
                $this->enumValues(
                    (string) $column[
                        'COLUMN_TYPE'
                    ],
                );

            if ($enum !== []) {
                foreach ($preferred as $value) {
                    if (
                        in_array(
                            (string) $value,
                            $enum,
                            true,
                        )
                    ) {
                        return (string) $value;
                    }
                }

                return $enum[0];
            }

            if (
                (int) (
                    $column[
                        'CHARACTER_MAXIMUM_LENGTH'
                    ] ?? 0
                ) === 1
            ) {
                foreach ($preferred as $value) {
                    if (
                        mb_strlen(
                            (string) $value,
                        ) === 1
                    ) {
                        return (string) $value;
                    }
                }
            }

            return $this->normalizeValue(
                $column,
                $fallback,
            );
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function enumValues(
        string $columnType,
    ): array {
        $lower =
            strtolower(
                $columnType,
            );

        if (
            ! str_starts_with(
                $lower,
                'enum(',
            )
            && ! str_starts_with(
                $lower,
                'set(',
            )
        ) {
            return [];
        }

        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            $columnType,
            $matches,
        );

        return array_values(
            array_map(
                static fn (
                    string $value,
                ): string => stripcslashes(
                    $value,
                ),
                $matches[1]
                ?? [],
            ),
        );
    }

    private function truncate(
        array $column,
        string $value,
    ): string {
        $maximumLength =
            (int) (
                $column[
                    'CHARACTER_MAXIMUM_LENGTH'
                ] ?? 0
            );

        return $maximumLength > 0
            && mb_strlen(
                $value,
            ) > $maximumLength
                ? mb_substr(
                    $value,
                    0,
                    $maximumLength,
                )
                : $value;
    }

    private function guardTestingDatabase(): void
    {
        $connection =
            DB::connection();

        $database =
            strtolower(
                trim(
                    (string) $connection
                        ->getDatabaseName(),
                ),
            );

        $this->assertSame(
            'testing',
            app()->environment(),
        );

        $this->assertSame(
            'mysql',
            $connection->getDriverName(),
        );

        $this->assertTrue(
            str_ends_with(
                $database,
                '_test',
            ),
            "Database non-testing ditolak: {$database}",
        );

        $this->assertSame(
            0,
            $connection->transactionLevel(),
            'Jangan memakai DatabaseTransactions pada test paralel ini.',
        );
    }

    private function assertRequiredTables(): void
    {
        foreach (
            [
                'schools',
                'users',
                'pickup_events',
                'pickup_event_students',
            ] as $table
        ) {
            $this->assertTrue(
                DB::getSchemaBuilder()
                    ->hasTable(
                        $table,
                    ),
                "Tabel [{$table}] belum tersedia.",
            );
        }
    }

    private function assertParallelRuntime(): void
    {
        $this->assertTrue(
            function_exists(
                'proc_open',
            ),
            'proc_open diperlukan untuk menjalankan worker paralel.',
        );

        $this->assertTrue(
            class_exists(
                Process::class,
            ),
            'Symfony Process belum tersedia.',
        );

        $this->assertFileExists(
            base_path(
                'tests/Support/GatePickupEventParallelCancellationWorker.php',
            ),
        );
    }

    private function assertInnoDb(): void
    {
        foreach (
            [
                'pickup_events',
                'pickup_event_students',
            ] as $table
        ) {
            $row =
                DB::selectOne(
                    'SELECT ENGINE FROM information_schema.TABLES '
                    .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [
                        $table,
                    ],
                );

            $this->assertSame(
                'innodb',
                strtolower(
                    (string) (
                        $row->ENGINE
                        ?? ''
                    ),
                ),
                "Tabel [{$table}] harus menggunakan InnoDB.",
            );
        }
    }

    private function rollbackLock(): void
    {
        if (! $this->lockTransactionActive) {
            return;
        }

        if (
            DB::connection()
                ->transactionLevel() > 0
        ) {
            DB::connection()
                ->rollBack();
        }

        $this->lockTransactionActive =
            false;
    }

    private function stopProcesses(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(
                    1,
                );
            }
        }

        $this->processes = [];
    }

    private function cleanupFixtures(): void
    {
        $eventStudentId =
            $this->ids[
                'event_student'
            ] ?? null;

        $eventId =
            $this->ids[
                'event'
            ] ?? null;

        $officerId =
            $this->ids[
                'officer'
            ] ?? null;

        $schoolId =
            $this->ids[
                'school'
            ] ?? null;

        if ($eventStudentId !== null) {
            DB::table(
                'pickup_event_students',
            )
                ->where(
                    'id',
                    $eventStudentId,
                )
                ->delete();
        }

        if ($eventId !== null) {
            DB::table(
                'pickup_event_students',
            )
                ->where(
                    'pickup_event_id',
                    $eventId,
                )
                ->delete();

            DB::table(
                'pickup_events',
            )
                ->where(
                    'id',
                    $eventId,
                )
                ->delete();
        }

        if ($officerId !== null) {
            DB::table(
                'users',
            )
                ->where(
                    'id',
                    $officerId,
                )
                ->delete();
        }

        if ($schoolId !== null) {
            DB::table(
                'schools',
            )
                ->where(
                    'id',
                    $schoolId,
                )
                ->delete();
        }

        $this->ids = [];
    }

    private function makeDirectory(
        string $directory,
    ): void {
        if (
            ! is_dir(
                $directory,
            )
            && ! mkdir(
                $directory,
                0777,
                true,
            )
            && ! is_dir(
                $directory,
            )
        ) {
            throw new RuntimeException(
                "Direktori tidak dapat dibuat: {$directory}",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(
        string $path,
        array $payload,
    ): void {
        $json =
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            );

        if (
            file_put_contents(
                $path,
                $json,
                LOCK_EX,
            ) === false
        ) {
            throw new RuntimeException(
                "File tidak dapat ditulis: {$path}",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(
        string $path,
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                "File JSON tidak ditemukan: {$path}",
            );
        }

        $contents =
            file_get_contents(
                $path,
            );

        if ($contents === false) {
            throw new RuntimeException(
                "File JSON tidak dapat dibaca: {$path}",
            );
        }

        $decoded =
            json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

        if (! is_array($decoded)) {
            throw new RuntimeException(
                "Isi file bukan object JSON: {$path}",
            );
        }

        return $decoded;
    }

    private function cleanupRunDirectories(): void
    {
        foreach (
            $this->runDirectories as $directory
        ) {
            $this->deleteDirectory(
                $directory,
            );
        }

        $this->runDirectories = [];
    }

    private function deleteDirectory(
        string $directory,
    ): void {
        if (! is_dir($directory)) {
            return;
        }

        $items =
            scandir(
                $directory,
            );

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (
                $item === '.'
                || $item === '..'
            ) {
                continue;
            }

            $path =
                $directory
                .DIRECTORY_SEPARATOR
                .$item;

            if (is_dir($path)) {
                $this->deleteDirectory(
                    $path,
                );
            } else {
                @unlink(
                    $path,
                );
            }
        }

        @rmdir(
            $directory,
        );
    }
}
