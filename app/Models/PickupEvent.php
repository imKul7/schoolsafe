<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupEvent extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Metode verifikasi
    |--------------------------------------------------------------------------
    */

    public const VERIFICATION_METHOD_FACE =
        'face';

    public const VERIFICATION_METHOD_MANUAL =
        'manual';

    public const VERIFICATION_METHODS = [
        self::VERIFICATION_METHOD_FACE,
        self::VERIFICATION_METHOD_MANUAL,
    ];

    /*
    |--------------------------------------------------------------------------
    | Status transaksi
    |--------------------------------------------------------------------------
    */

    public const STATUS_CONFIRMED =
        'confirmed';

    public const STATUS_CANCELLED =
        'cancelled';

    public const STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'school_id',
        'pickup_person_id',
        'face_verification_attempt_id',
        'confirmed_by_user_id',
        'cancelled_by_user_id',
        'idempotency_key',
        'verification_method',
        'status',
        'pickup_person_name',
        'pickup_person_phone',
        'verification_result',
        'similarity_score',
        'similarity_threshold',
        'candidate_margin',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',

            'pickup_person_id' => 'integer',

            'face_verification_attempt_id' => 'integer',

            'confirmed_by_user_id' => 'integer',

            'cancelled_by_user_id' => 'integer',

            'similarity_score' => 'float',

            'similarity_threshold' => 'float',

            'candidate_margin' => 'float',

            'confirmed_at' => 'datetime',

            'cancelled_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi utama
    |--------------------------------------------------------------------------
    */

    public function school(): BelongsTo
    {
        return $this->belongsTo(
            School::class,
        );
    }

    public function pickupPerson(): BelongsTo
    {
        return $this->belongsTo(
            PickupPerson::class,
        );
    }

    public function faceVerificationAttempt(): BelongsTo
    {
        return $this->belongsTo(
            PickupPersonFaceVerificationAttempt::class,
            'face_verification_attempt_id',
        );
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'confirmed_by_user_id',
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi detail siswa
    |--------------------------------------------------------------------------
    */

    public function eventStudents(): HasMany
    {
        return $this->hasMany(
            PickupEventStudent::class,
        );
    }

    public function releasedEventStudents(): HasMany
    {
        return $this
            ->eventStudents()
            ->where(
                'status',
                PickupEventStudent::STATUS_RELEASED,
            );
    }

    public function cancelledEventStudents(): HasMany
    {
        return $this
            ->eventStudents()
            ->where(
                'status',
                PickupEventStudent::STATUS_CANCELLED,
            );
    }

    public function students(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Student::class,
                'pickup_event_students',
                'pickup_event_id',
                'student_id',
            )
            ->using(
                PickupEventStudent::class,
            )
            ->withPivot([
                'id',
                'student_name',
                'student_number',
                'class_name',
                'academic_year',
                'relationship_type',
                'is_primary',
                'status',
                'released_at',
                'cancelled_at',
                'cancelled_by_user_id',
                'cancellation_reason',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForSchool(
        Builder $query,
        int $schoolId,
    ): Builder {
        return $query->where(
            'school_id',
            $schoolId,
        );
    }

    public function scopeConfirmed(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_CONFIRMED,
        );
    }

    public function scopeCancelled(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_CANCELLED,
        );
    }

    public function scopeFaceVerified(
        Builder $query,
    ): Builder {
        return $query->where(
            'verification_method',
            self::VERIFICATION_METHOD_FACE,
        );
    }

    public function scopeManualVerified(
        Builder $query,
    ): Builder {
        return $query->where(
            'verification_method',
            self::VERIFICATION_METHOD_MANUAL,
        );
    }

    public function scopeConfirmedByUser(
        Builder $query,
        int $userId,
    ): Builder {
        return $query->where(
            'confirmed_by_user_id',
            $userId,
        );
    }

    public function scopeSearch(
        Builder $query,
        ?string $search,
    ): Builder {
        $search =
            trim(
                (string) $search,
            );

        if ($search === '') {
            return $query;
        }

        return $query->where(
            function (
                Builder $query,
            ) use (
                $search,
            ): void {
                $query
                    ->where(
                        'pickup_person_name',
                        'like',
                        "%{$search}%",
                    )
                    ->orWhere(
                        'pickup_person_phone',
                        'like',
                        "%{$search}%",
                    )
                    ->orWhereHas(
                        'eventStudents',
                        function (
                            Builder $studentQuery,
                        ) use (
                            $search,
                        ): void {
                            $studentQuery
                                ->where(
                                    'student_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'student_number',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );

                if (ctype_digit($search)) {
                    $query->orWhereKey(
                        (int) $search,
                    );
                }
            },
        );
    }

    public function scopeLatestFirst(
        Builder $query,
    ): Builder {
        return $query
            ->orderByDesc(
                'confirmed_at',
            )
            ->orderByDesc(
                'id',
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isConfirmed(): bool
    {
        return $this->status
            === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === self::STATUS_CANCELLED;
    }

    public function wasFaceVerified(): bool
    {
        return $this->verification_method
            === self::VERIFICATION_METHOD_FACE;
    }

    public function wasManuallyVerified(): bool
    {
        return $this->verification_method
            === self::VERIFICATION_METHOD_MANUAL;
    }

    public function canBeCancelled(): bool
    {
        return
            $this->isConfirmed()
            && $this->cancelled_at === null;
    }

    public function hasReleasedStudents(): bool
    {
        return $this
            ->releasedEventStudents()
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'Dikonfirmasi',

            self::STATUS_CANCELLED => 'Dibatalkan',

            default => 'Tidak Diketahui',
        };
    }

    public function verificationMethodLabel(): string
    {
        return match (
            $this->verification_method
        ) {
            self::VERIFICATION_METHOD_FACE => 'Verifikasi Wajah',

            self::VERIFICATION_METHOD_MANUAL => 'Verifikasi Manual',

            default => 'Tidak Diketahui',
        };
    }
}
