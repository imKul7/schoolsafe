<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Helper konfigurasi
|--------------------------------------------------------------------------
*/

$clampFloat = static function (
    mixed $value,
    float $minimum,
    float $maximum,
): float {
    $number = (float) $value;

    if (! is_finite($number)) {
        return $minimum;
    }

    return max(
        $minimum,
        min($maximum, $number),
    );
};

$clampInteger = static function (
    mixed $value,
    int $minimum,
    int $maximum,
): int {
    return max(
        $minimum,
        min($maximum, (int) $value),
    );
};

$boolean = static function (
    mixed $value,
    bool $default,
): bool {
    $parsed = filter_var(
        $value,
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE,
    );

    return is_bool($parsed)
        ? $parsed
        : $default;
};

$nonEmptyString = static function (
    mixed $value,
    string $default,
): string {
    $normalized = trim((string) $value);

    return $normalized !== ''
        ? $normalized
        : $default;
};

/*
|--------------------------------------------------------------------------
| Nilai bersama
|--------------------------------------------------------------------------
*/

$minimumEmbeddingDimension = $clampInteger(
    env('BIOMETRIC_MIN_EMBEDDING_DIMENSION', 64),
    1,
    4096,
);

$maximumEmbeddingDimension = $clampInteger(
    env('BIOMETRIC_MAX_EMBEDDING_DIMENSION', 2048),
    $minimumEmbeddingDimension,
    8192,
);

$blinkMinimumMilliseconds = $clampInteger(
    env('BIOMETRIC_BLINK_MIN_MS', 60),
    30,
    1000,
);

$blinkMaximumMilliseconds = $clampInteger(
    env('BIOMETRIC_BLINK_MAX_MS', 900),
    $blinkMinimumMilliseconds,
    2000,
);

$storeIpAddress = $boolean(
    env('BIOMETRIC_AUDIT_STORE_IP', true),
    true,
);

$storeUserAgent = $boolean(
    env('BIOMETRIC_AUDIT_STORE_USER_AGENT', true),
    true,
);

return [
    /*
    |--------------------------------------------------------------------------
    | Identitas dan kualitas model
    |--------------------------------------------------------------------------
    */

    'consent_version' => $nonEmptyString(
        env('BIOMETRIC_CONSENT_VERSION', 'v1'),
        'v1',
    ),

    'model_name' => $nonEmptyString(
        env('BIOMETRIC_MODEL_NAME', 'human-hse-faceres'),
        'human-hse-faceres',
    ),

    'minimum_quality_score' => $clampFloat(
        env('BIOMETRIC_MIN_QUALITY_SCORE', 0.75),
        0.0,
        1.0,
    ),

    'minimum_embedding_dimension' => $minimumEmbeddingDimension,

    'maximum_embedding_dimension' => $maximumEmbeddingDimension,

    /*
    |--------------------------------------------------------------------------
    | Pencocokan wajah
    |--------------------------------------------------------------------------
    */

    'matching' => [
        'minimum_similarity' => $clampFloat(
            env('BIOMETRIC_MIN_SIMILARITY', 0.60),
            0.0,
            1.0,
        ),

        'minimum_margin' => $clampFloat(
            env('BIOMETRIC_MIN_MARGIN', 0.05),
            0.0,
            1.0,
        ),

        'maximum_candidates' => $clampInteger(
            env('BIOMETRIC_MAX_CANDIDATES', 2000),
            1,
            10000,
        ),

        'probe_samples' => $clampInteger(
            env('BIOMETRIC_PROBE_SAMPLES', 3),
            1,
            10,
        ),

        'probe_delay_milliseconds' => $clampInteger(
            env('BIOMETRIC_PROBE_DELAY_MS', 550),
            250,
            2000,
        ),

        'minimum_frame_quality' => $clampFloat(
            env('BIOMETRIC_MIN_FRAME_QUALITY', 0.50),
            0.0,
            1.0,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Liveness dan anti-spoofing
    |--------------------------------------------------------------------------
    */

    'liveness' => [
        'minimum_live_score' => $clampFloat(
            env('BIOMETRIC_MIN_LIVE_SCORE', 0.80),
            0.0,
            1.0,
        ),

        'minimum_real_score' => $clampFloat(
            env('BIOMETRIC_MIN_REAL_SCORE', 0.80),
            0.0,
            1.0,
        ),

        'minimum_passed_sample_ratio' => $clampFloat(
            env('BIOMETRIC_MIN_LIVENESS_SAMPLE_RATIO', 0.67),
            0.0,
            1.0,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge liveness
    |--------------------------------------------------------------------------
    */

    'challenge' => [
        'ttl_seconds' => $clampInteger(
            env('BIOMETRIC_CHALLENGE_TTL_SECONDS', 45),
            15,
            180,
        ),

        'blink_min_ms' => $blinkMinimumMilliseconds,

        'blink_max_ms' => $blinkMaximumMilliseconds,

        'head_turn_yaw_delta' => $clampFloat(
            env('BIOMETRIC_HEAD_TURN_YAW_DELTA', 0.18),
            0.05,
            1.50,
        ),

        'center_yaw_tolerance' => $clampFloat(
            env('BIOMETRIC_CENTER_YAW_TOLERANCE', 0.10),
            0.03,
            0.75,
        ),

        'required_center_frames' => $clampInteger(
            env('BIOMETRIC_REQUIRED_CENTER_FRAMES', 2),
            1,
            10,
        ),

        'maximum_duration_ms' => $clampInteger(
            env('BIOMETRIC_CHALLENGE_MAX_DURATION_MS', 30000),
            5000,
            60000,
        ),

        'frame_interval_ms' => $clampInteger(
            env('BIOMETRIC_CHALLENGE_FRAME_INTERVAL_MS', 100),
            50,
            1000,
        ),

        'blink_close_ratio' => $clampFloat(
            env('BIOMETRIC_BLINK_CLOSE_RATIO', 0.78),
            0.40,
            0.98,
        ),

        'baseline_frames' => $clampInteger(
            env('BIOMETRIC_CHALLENGE_BASELINE_FRAMES', 4),
            2,
            20,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hardening gerbang
    |--------------------------------------------------------------------------
    */

    'security' => [
        'cooldown_seconds' => $clampInteger(
            env('BIOMETRIC_VERIFY_COOLDOWN_SECONDS', 5),
            1,
            60,
        ),

        'maximum_failed_attempts' => $clampInteger(
            env('BIOMETRIC_MAX_FAILED_ATTEMPTS', 5),
            1,
            100,
        ),

        'failed_attempt_decay_seconds' => $clampInteger(
            env('BIOMETRIC_FAILED_ATTEMPT_DECAY_SECONDS', 300),
            30,
            86400,
        ),

        'challenge_rate_limit_per_minute' => $clampInteger(
            env('BIOMETRIC_CHALLENGE_RATE_LIMIT', 20),
            1,
            300,
        ),

        'verification_rate_limit_per_minute' => $clampInteger(
            env('BIOMETRIC_VERIFICATION_RATE_LIMIT', 30),
            1,
            300,
        ),

        'bind_pickup_confirmation_to_session' => $boolean(
            env(
                'BIOMETRIC_BIND_CONFIRMATION_TO_SESSION',
                true,
            ),
            true,
        ),

        'pickup_confirmation_window_seconds' => $clampInteger(
            env(
                'BIOMETRIC_PICKUP_CONFIRMATION_WINDOW_SECONDS',
                300,
            ),
            30,
            1800,
        ),

        'gate_cancellation_window_seconds' => $clampInteger(
            env(
                'BIOMETRIC_GATE_CANCELLATION_WINDOW_SECONDS',
                900,
            ),
            60,
            86400,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit dan retensi
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'retention_days' => $clampInteger(
            env('BIOMETRIC_AUDIT_RETENTION_DAYS', 365),
            1,
            3650,
        ),

        /*
         * Dua nama disediakan untuk kompatibilitas kode lama.
         */
        'store_ip' => $storeIpAddress,

        'store_ip_address' => $storeIpAddress,

        'store_user_agent' => $storeUserAgent,
    ],
];
