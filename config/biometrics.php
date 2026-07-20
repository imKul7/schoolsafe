<?php

declare(strict_types=1);

return [
    /*
     * Nilai kualitas minimal agar registrasi diterima.
     * Rentang nilai 0 sampai 1.
     */
    'minimum_quality_score' =>
        (float) env(
            'BIOMETRIC_MIN_QUALITY_SCORE',
            0.75,
        ),

    /*
     * Versi teks persetujuan biometrik.
     */
    'consent_version' =>
        (string) env(
            'BIOMETRIC_CONSENT_VERSION',
            'v1',
        ),

    /*
     * Batas dimensi embedding untuk mencegah payload
     * tidak wajar.
     */
    'minimum_embedding_dimension' =>
        64,

    'maximum_embedding_dimension' =>
        2048,
];