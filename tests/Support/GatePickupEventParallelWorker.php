<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

define(
    'LARAVEL_START',
    microtime(true),
);

/**
 * @param  array<string, mixed>  $payload
 */
function writeJsonAtomically(
    string $path,
    array $payload,
): void {
    $directory = dirname($path);

    if (
        ! is_dir($directory)
        && ! mkdir(
            $directory,
            0777,
            true,
        )
        && ! is_dir($directory)
    ) {
        throw new RuntimeException(
            sprintf(
                'Direktori output tidak dapat dibuat: %s',
                $directory,
            ),
        );
    }

    $temporaryPath =
        sprintf(
            '%s.tmp.%d.%s',
            $path,
            getmypid(),
            bin2hex(
                random_bytes(4),
            ),
        );

    $encoded = json_encode(
        $payload,
        JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE,
    );

    if (
        file_put_contents(
            $temporaryPath,
            $encoded,
            LOCK_EX,
        ) === false
    ) {
        throw new RuntimeException(
            sprintf(
                'File sementara tidak dapat ditulis: %s',
                $temporaryPath,
            ),
        );
    }

    if (
        file_exists($path)
        && ! unlink($path)
    ) {
        @unlink($temporaryPath);

        throw new RuntimeException(
            sprintf(
                'File output lama tidak dapat dihapus: %s',
                $path,
            ),
        );
    }

    if (
        ! rename(
            $temporaryPath,
            $path,
        )
    ) {
        @unlink($temporaryPath);

        throw new RuntimeException(
            sprintf(
                'File sementara tidak dapat dipindahkan ke: %s',
                $path,
            ),
        );
    }
}

function waitForReleaseBarrier(
    string $releasePath,
    int $timeoutMilliseconds,
): void {
    $startedAt =
        hrtime(true);

    while (true) {
        clearstatcache(
            true,
            $releasePath,
        );

        if (is_file($releasePath)) {
            return;
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
            >= $timeoutMilliseconds
        ) {
            throw new RuntimeException(
                sprintf(
                    'Worker menunggu release barrier melebihi %d ms.',
                    $timeoutMilliseconds,
                ),
            );
        }

        usleep(
            10_000,
        );
    }
}

/**
 * @return array<string, mixed>
 */
function readJobFile(
    string $jobPath,
): array {
    if (! is_file($jobPath)) {
        throw new RuntimeException(
            sprintf(
                'Job file tidak ditemukan: %s',
                $jobPath,
            ),
        );
    }

    $contents =
        file_get_contents(
            $jobPath,
        );

    if ($contents === false) {
        throw new RuntimeException(
            sprintf(
                'Job file tidak dapat dibaca: %s',
                $jobPath,
            ),
        );
    }

    $decoded = json_decode(
        $contents,
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (! is_array($decoded)) {
        throw new RuntimeException(
            'Isi job worker harus berupa object JSON.',
        );
    }

    return $decoded;
}

/*
|--------------------------------------------------------------------------
| Argument worker
|--------------------------------------------------------------------------
|
| 1. job file
| 2. result file
| 3. ready marker
| 4. release barrier
|
*/

if ($argc !== 5) {
    fwrite(
        STDERR,
        sprintf(
            'Penggunaan: php %s <job.json> <result.json> <ready.json> <release.marker>%s',
            $argv[0] ?? 'GatePickupEventParallelWorker.php',
            PHP_EOL,
        ),
    );

    exit(64);
}

$jobPath =
    (string) $argv[1];

$resultPath =
    (string) $argv[2];

$readyPath =
    (string) $argv[3];

$releasePath =
    (string) $argv[4];

$request = null;
$response = null;
$kernel = null;
$requestStartedAt = null;

try {
    $environment =
        trim(
            (string) getenv(
                'APP_ENV',
            ),
        );

    if ($environment !== 'testing') {
        throw new RuntimeException(
            sprintf(
                'Worker hanya boleh berjalan pada APP_ENV=testing. Environment saat ini: %s',
                $environment !== ''
                    ? $environment
                    : '[kosong]',
            ),
        );
    }

    $basePath =
        dirname(
            __DIR__,
            2,
        );

    $autoloadPath =
        $basePath
        .DIRECTORY_SEPARATOR
        .'vendor'
        .DIRECTORY_SEPARATOR
        .'autoload.php';

    $bootstrapPath =
        $basePath
        .DIRECTORY_SEPARATOR
        .'bootstrap'
        .DIRECTORY_SEPARATOR
        .'app.php';

    if (! is_file($autoloadPath)) {
        throw new RuntimeException(
            sprintf(
                'Autoload Composer tidak ditemukan: %s',
                $autoloadPath,
            ),
        );
    }

    if (! is_file($bootstrapPath)) {
        throw new RuntimeException(
            sprintf(
                'Bootstrap Laravel tidak ditemukan: %s',
                $bootstrapPath,
            ),
        );
    }

    require $autoloadPath;

    $app =
        require $bootstrapPath;

    /** @var Kernel $kernel */
    $kernel =
        $app->make(
            Kernel::class,
        );

    $kernel->bootstrap();

    if (! $app->environment('testing')) {
        throw new RuntimeException(
            sprintf(
                'Laravel worker tidak berada pada environment testing. Environment: %s',
                $app->environment(),
            ),
        );
    }

    /*
     * Tiap worker menggunakan session memory miliknya sendiri.
     * Session binding bukan sasaran pengujian 6C-2.
     */
    config()->set(
        'session.driver',
        'array',
    );

    config()->set(
        'cache.default',
        'array',
    );

    config()->set(
        'queue.default',
        'sync',
    );

    config()->set(
        'biometrics.security.bind_pickup_confirmation_to_session',
        false,
    );

    $job =
        readJobFile(
            $jobPath,
        );

    $requestId =
        trim(
            (string) (
                $job['request_id']
                ?? ''
            ),
        );

    if ($requestId === '') {
        throw new RuntimeException(
            'Job worker tidak memiliki request_id.',
        );
    }

    $userId =
        (int) (
            $job['user_id']
            ?? 0
        );

    if ($userId <= 0) {
        throw new RuntimeException(
            'Job worker tidak memiliki user_id yang valid.',
        );
    }

    $payload =
        $job['payload']
        ?? null;

    if (! is_array($payload)) {
        throw new RuntimeException(
            'Job worker tidak memiliki payload request yang valid.',
        );
    }

    $barrierTimeoutMilliseconds =
        max(
            1_000,
            min(
                60_000,
                (int) (
                    $job[
                        'barrier_timeout_ms'
                    ]
                    ?? 15_000
                ),
            ),
        );

    $connection =
        DB::connection();

    $connection->reconnect();

    $databaseName =
        trim(
            (string) $connection
                ->getDatabaseName(),
        );

    if (
        $databaseName === ''
        || ! str_ends_with(
            strtolower($databaseName),
            '_test',
        )
    ) {
        throw new RuntimeException(
            sprintf(
                'Worker menolak database non-testing: %s',
                $databaseName !== ''
                    ? $databaseName
                    : '[kosong]',
            ),
        );
    }

    $expectedDatabase =
        trim(
            (string) (
                $job[
                    'expected_database'
                ]
                ?? ''
            ),
        );

    if (
        $expectedDatabase !== ''
        && strcasecmp(
            $databaseName,
            $expectedDatabase,
        ) !== 0
    ) {
        throw new RuntimeException(
            sprintf(
                'Database worker [%s] berbeda dari database yang diharapkan [%s].',
                $databaseName,
                $expectedDatabase,
            ),
        );
    }

    $user =
        User::query()
            ->whereKey(
                $userId,
            )
            ->where(
                'is_active',
                true,
            )
            ->first();

    if (! $user instanceof User) {
        throw new RuntimeException(
            sprintf(
                'User worker tidak ditemukan atau tidak aktif: %d',
                $userId,
            ),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Siapkan request sebelum membuat SessionGuard
    |--------------------------------------------------------------------------
    |
    | SessionGuard membutuhkan request aktif dari service container.
    | Karena itu request harus dibuat dan didaftarkan sebelum guard web
    | di-resolve.
    |
    */

    $requestBody =
        json_encode(
            $payload,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE,
        );

    $server = [
        'HTTP_ACCEPT' => 'application/json',

        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',

        'CONTENT_TYPE' => 'application/json',

        'CONTENT_LENGTH' => (string) strlen(
            $requestBody,
        ),

        'REMOTE_ADDR' => '127.0.0.1',

        'HTTP_HOST' => 'localhost',

        'SERVER_NAME' => 'localhost',

        'SERVER_PORT' => '80',

        'HTTP_USER_AGENT' => sprintf(
            'SchoolSafe Parallel Worker/%s',
            $requestId,
        ),
    ];

    $request =
        Request::create(
            '/gate/pickup-events',
            'POST',
            [],
            [],
            [],
            $server,
            $requestBody,
        );

    /*
     * Daftarkan request pada container sebelum Auth::guard('web')
     * di-resolve. Ini mencegah SessionGuard menerima request null.
     */
    $app->instance(
        'request',
        $request,
    );

    $app->instance(
        Request::class,
        $request,
    );

    /*
     * Resolver request menjadi lapisan tambahan bagi controller.
     */
    $request->setUserResolver(
        static fn (): User => $user,
    );

    Auth::shouldUse(
        'web',
    );

    $guard =
        Auth::guard(
            'web',
        );

    $guard->setRequest(
        $request,
    );

    $guard->setUser(
        $user,
    );

    /*
     * Worker baru dinyatakan ready setelah request, database, dan
     * autentikasi guard web siap dipakai.
     */
    writeJsonAtomically(
        $readyPath,
        [
            'ready' => true,

            'request_id' => $requestId,

            'pid' => getmypid(),

            'environment' => $app->environment(),

            'database' => $databaseName,

            'connection_name' => $connection->getName(),

            'ready_at' => now()->toIso8601String(),
        ],
    );

    waitForReleaseBarrier(
        $releasePath,
        $barrierTimeoutMilliseconds,
    );

    $requestStartedAt =
        hrtime(true);

    $response =
        $kernel->handle(
            $request,
        );

    $durationMilliseconds =
        (int) (
            (
                hrtime(true)
                - $requestStartedAt
            )
            / 1_000_000
        );

    $body =
        (string) $response
            ->getContent();

    $json = null;

    if ($body !== '') {
        try {
            $decodedBody =
                json_decode(
                    $body,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );

            if (is_array($decodedBody)) {
                $json =
                    $decodedBody;
            }
        } catch (JsonException) {
            $json = null;
        }
    }

    writeJsonAtomically(
        $resultPath,
        [
            'completed' => true,

            'request_id' => $requestId,

            'pid' => getmypid(),

            'environment' => $app->environment(),

            'database' => $databaseName,

            'status' => $response
                ->getStatusCode(),

            'json' => $json,

            'body' => $body,

            'duration_ms' => $durationMilliseconds,

            'completed_at' => now()->toIso8601String(),
        ],
    );

    $kernel->terminate(
        $request,
        $response,
    );

    exit(0);
} catch (Throwable $exception) {
    if (
        $kernel instanceof Kernel
        && $request instanceof Request
        && $response !== null
    ) {
        try {
            $kernel->terminate(
                $request,
                $response,
            );
        } catch (Throwable) {
            // Jangan menutupi exception utama.
        }
    }

    $durationMilliseconds =
        $requestStartedAt !== null
            ? (int) (
                (
                    hrtime(true)
                    - $requestStartedAt
                )
                / 1_000_000
            )
            : null;

    $failurePayload = [
        'completed' => false,

        'pid' => getmypid(),

        'exception_class' => $exception::class,

        'exception_message' => $exception->getMessage(),

        'exception_file' => $exception->getFile(),

        'exception_line' => $exception->getLine(),

        'duration_ms' => $durationMilliseconds,

        'failed_at' => date(
            DATE_ATOM,
        ),
    ];

    /*
|--------------------------------------------------------------------------
| Selalu tampilkan exception ke STDERR
|--------------------------------------------------------------------------
|
| Coordinator Symfony Process membaca STDERR melalui getErrorOutput().
| Result file tetap ditulis sebagai bukti terstruktur, tetapi exception
| juga harus terlihat ketika worker berhenti sebelum membuat ready marker.
|
*/

    try {
        fwrite(
            STDERR,
            json_encode(
                $failurePayload,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            )
            .PHP_EOL,
        );
    } catch (Throwable $stderrException) {
        fwrite(
            STDERR,
            sprintf(
                '%s: %s%sGagal mengubah exception worker menjadi JSON: %s%s',
                $exception::class,
                $exception->getMessage(),
                PHP_EOL,
                $stderrException->getMessage(),
                PHP_EOL,
            ),
        );
    }

    try {
        writeJsonAtomically(
            $resultPath,
            $failurePayload,
        );
    } catch (Throwable $writeException) {
        fwrite(
            STDERR,
            sprintf(
                '%s: %s%sGagal menulis result file: %s%s',
                $exception::class,
                $exception->getMessage(),
                PHP_EOL,
                $writeException->getMessage(),
                PHP_EOL,
            ),
        );
    }

    exit(1);
}
