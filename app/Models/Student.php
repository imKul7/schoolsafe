<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_GRADUATED = 'graduated';

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

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

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'school_class_id' => 'integer',
            'date_of_birth' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Sekolah tempat siswa terdaftar.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Kelas siswa.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Seluruh orang yang pernah atau masih terdaftar sebagai penjemput.
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
     * Penjemput yang relasinya masih aktif.
     */
    public function activePickupPersons(): BelongsToMany
    {
        return $this
            ->pickupPersons()
            ->wherePivot('is_active', true);
    }

    /**
     * Penjemput utama siswa.
     */
    public function primaryPickupPersons(): BelongsToMany
    {
        return $this
            ->pickupPersons()
            ->wherePivot('is_primary', true)
            ->wherePivot('is_active', true);
    }

    /**
     * Scope siswa berdasarkan sekolah.
     */
    public function scopeForSchool(
        Builder $query,
        int $schoolId,
    ): Builder {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope siswa aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_ACTIVE,
        );
    }

    /**
     * Scope pencarian siswa.
     */
    public function scopeSearch(
        Builder $query,
        ?string $search,
    ): Builder {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(
            function (Builder $query) use ($search): void {
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isGraduated(): bool
    {
        return $this->status === self::STATUS_GRADUATED;
    }

    public function isMale(): bool
    {
        return $this->gender === self::GENDER_MALE;
    }

    public function isFemale(): bool
    {
        return $this->gender === self::GENDER_FEMALE;
    }

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
}