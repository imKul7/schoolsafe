<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status siswa
    |--------------------------------------------------------------------------
    */

    public const STATUS_ACTIVE =
        'active';

    public const STATUS_INACTIVE =
        'inactive';

    public const STATUS_GRADUATED =
        'graduated';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_GRADUATED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Jenis kelamin
    |--------------------------------------------------------------------------
    */

    public const GENDER_MALE =
        'male';

    public const GENDER_FEMALE =
        'female';

    public const GENDERS = [
        self::GENDER_MALE,
        self::GENDER_FEMALE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'school_id',
        'school_class_id',
        'student_number',
        'nisn',
        'full_name',
        'gender',
        'date_of_birth',
        'photo_path',
        'status',
        'notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',

            'school_class_id' => 'integer',

            'date_of_birth' => 'date',

            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi sekolah dan kelas
    |--------------------------------------------------------------------------
    */

    /**
     * Sekolah tempat siswa terdaftar.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(
            School::class,
        );
    }

    /**
     * Kelas siswa.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(
            SchoolClass::class,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi penjemput yang diizinkan
    |--------------------------------------------------------------------------
    */

    /**
     * Seluruh orang yang pernah atau masih terdaftar
     * sebagai penjemput siswa.
     */
    public function pickupPersons(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                PickupPerson::class,
                'pickup_person_student',
                'student_id',
                'pickup_person_id',
            )
            ->withPivot([
                'school_id',
                'relationship_type',
                'is_primary',
                'is_active',
                'valid_from',
                'valid_until',
            ])
            ->withTimestamps();
    }

    /**
     * Penjemput yang relasinya aktif dan masih berada
     * dalam periode berlaku.
     */
    public function activePickupPersons(): BelongsToMany
    {
        return $this
            ->pickupPersons()
            ->wherePivot(
                'is_active',
                true,
            )
            ->where(
                'pickup_persons.is_active',
                true,
            )
            ->where(
                function (
                    Builder $query,
                ): void {
                    $query
                        ->whereNull(
                            'pickup_person_student.valid_from',
                        )
                        ->orWhereDate(
                            'pickup_person_student.valid_from',
                            '<=',
                            now()->toDateString(),
                        );
                },
            )
            ->where(
                function (
                    Builder $query,
                ): void {
                    $query
                        ->whereNull(
                            'pickup_person_student.valid_until',
                        )
                        ->orWhereDate(
                            'pickup_person_student.valid_until',
                            '>=',
                            now()->toDateString(),
                        );
                },
            );
    }

    /**
     * Penjemput utama yang aktif dan masih berada
     * dalam periode berlaku.
     */
    public function primaryPickupPersons(): BelongsToMany
    {
        return $this
            ->activePickupPersons()
            ->wherePivot(
                'is_primary',
                true,
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi riwayat penjemputan
    |--------------------------------------------------------------------------
    */

    /**
     * Seluruh detail transaksi penjemputan siswa.
     *
     * Relasi ini mengarah langsung ke tabel
     * pickup_event_students.
     */
    public function pickupEventStudents(): HasMany
    {
        return $this->hasMany(
            PickupEventStudent::class,
        );
    }

    /**
     * Seluruh transaksi penjemputan siswa.
     */
    public function pickupEvents(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                PickupEvent::class,
                'pickup_event_students',
                'student_id',
                'pickup_event_id',
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
                'cancellation_reason',
            ])
            ->withTimestamps();
    }

    /**
     * Transaksi penjemputan yang berstatus diserahkan.
     */
    public function releasedPickupEvents(): BelongsToMany
    {
        return $this
            ->pickupEvents()
            ->wherePivot(
                'status',
                PickupEventStudent::STATUS_RELEASED,
            );
    }

    /**
     * Transaksi penjemputan siswa yang dibatalkan.
     */
    public function cancelledPickupEvents(): BelongsToMany
    {
        return $this
            ->pickupEvents()
            ->wherePivot(
                'status',
                PickupEventStudent::STATUS_CANCELLED,
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Membatasi siswa berdasarkan tenant sekolah.
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

    /**
     * Membatasi siswa berdasarkan kelas.
     */
    public function scopeForClass(
        Builder $query,
        int $schoolClassId,
    ): Builder {
        return $query->where(
            'school_class_id',
            $schoolClassId,
        );
    }

    /**
     * Membatasi siswa berdasarkan status.
     */
    public function scopeWithStatus(
        Builder $query,
        string $status,
    ): Builder {
        if (
            ! in_array(
                $status,
                self::STATUSES,
                true,
            )
        ) {
            return $query;
        }

        return $query->where(
            'status',
            $status,
        );
    }

    /**
     * Scope siswa aktif.
     */
    public function scopeActive(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ACTIVE,
        );
    }

    /**
     * Scope siswa tidak aktif.
     */
    public function scopeInactive(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_INACTIVE,
        );
    }

    /**
     * Scope siswa yang sudah lulus.
     */
    public function scopeGraduated(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_GRADUATED,
        );
    }

    /**
     * Scope siswa laki-laki.
     */
    public function scopeMale(
        Builder $query,
    ): Builder {
        return $query->where(
            'gender',
            self::GENDER_MALE,
        );
    }

    /**
     * Scope siswa perempuan.
     */
    public function scopeFemale(
        Builder $query,
    ): Builder {
        return $query->where(
            'gender',
            self::GENDER_FEMALE,
        );
    }

    /**
     * Scope pencarian siswa berdasarkan nama,
     * nomor siswa, atau NISN.
     */
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
                        'full_name',
                        'like',
                        "%{$search}%",
                    )
                    ->orWhere(
                        'student_number',
                        'like',
                        "%{$search}%",
                    )
                    ->orWhere(
                        'nisn',
                        'like',
                        "%{$search}%",
                    );
            },
        );
    }

    /**
     * Mengurutkan siswa berdasarkan nama.
     */
    public function scopeAlphabetical(
        Builder $query,
    ): Builder {
        return $query
            ->orderBy('full_name')
            ->orderBy('id');
    }

    /**
     * Mengurutkan siswa terbaru.
     */
    public function scopeLatestFirst(
        Builder $query,
    ): Builder {
        return $query->orderByDesc(
            'id',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status
            === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status
            === self::STATUS_INACTIVE;
    }

    public function isGraduated(): bool
    {
        return $this->status
            === self::STATUS_GRADUATED;
    }

    public function isMale(): bool
    {
        return $this->gender
            === self::GENDER_MALE;
    }

    public function isFemale(): bool
    {
        return $this->gender
            === self::GENDER_FEMALE;
    }

    /*
    |--------------------------------------------------------------------------
    | Penjemput helpers
    |--------------------------------------------------------------------------
    */

    public function hasActivePickupPerson(): bool
    {
        return $this
            ->activePickupPersons()
            ->exists();
    }

    public function hasPrimaryPickupPerson(): bool
    {
        return $this
            ->primaryPickupPersons()
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Riwayat penjemputan helpers
    |--------------------------------------------------------------------------
    */

    public function hasPickupHistory(): bool
    {
        return $this
            ->pickupEventStudents()
            ->exists();
    }

    public function hasReleasedPickupHistory(): bool
    {
        return $this
            ->pickupEventStudents()
            ->where(
                'status',
                PickupEventStudent::STATUS_RELEASED,
            )
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
            self::STATUS_ACTIVE => 'Aktif',

            self::STATUS_INACTIVE => 'Tidak Aktif',

            self::STATUS_GRADUATED => 'Lulus',

            default => 'Tidak Diketahui',
        };
    }

    public function genderLabel(): string
    {
        return match ($this->gender) {
            self::GENDER_MALE => 'Laki-laki',

            self::GENDER_FEMALE => 'Perempuan',

            default => 'Tidak Diketahui',
        };
    }
}
