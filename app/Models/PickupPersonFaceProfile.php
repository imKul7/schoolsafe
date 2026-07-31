<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupPersonFaceProfile extends Model
{
    public const STATUS_REGISTERED = 'registered';

    public const STATUS_NEEDS_UPDATE = 'needs_update';

    public const STATUS_REVOKED = 'revoked';

    /**
     * Kolom yang boleh diisi secara massal.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'pickup_person_id',
        'registered_by_user_id',
        'embedding',
        'embedding_dimension',
        'model_name',
        'model_version',
        'quality_score',
        'liveness_passed',
        'capture_method',
        'photo_sha256',
        'status',
        'registration_revision',
        'consent_version',
        'consented_at',
        'registered_at',
        'invalidated_at',
        'revoked_at',
        'metadata',
    ];

    /**
     * Embedding tidak boleh ikut ter-serialize ke frontend.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'embedding',
    ];

    /**
     * Casting atribut.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            /*
             * Laravel mengenkripsi array embedding sebelum
             * menyimpannya ke database.
             */
            'embedding' => 'encrypted:array',

            'embedding_dimension' => 'integer',

            'quality_score' => 'decimal:4',

            'liveness_passed' => 'boolean',

            'registration_revision' => 'integer',

            'consented_at' => 'datetime',

            'registered_at' => 'datetime',

            'invalidated_at' => 'datetime',

            'revoked_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    /**
     * Penjemput pemilik profil wajah.
     */
    public function pickupPerson(): BelongsTo
    {
        return $this->belongsTo(
            PickupPerson::class,
        );
    }

    /**
     * Sekolah pemilik profil wajah.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(
            School::class,
        );
    }

    /**
     * Pengguna yang melakukan registrasi.
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registered_by_user_id',
        );
    }
}
