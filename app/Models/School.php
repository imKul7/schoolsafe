<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'npsn',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'logo_path',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pickupEvents(): HasMany
    {
    return $this->hasMany(
        PickupEvent::class,
    );
    }

    /**
     * Seluruh pengguna yang terhubung dengan sekolah.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Seluruh kelas yang dimiliki sekolah.
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * Seluruh siswa yang terdaftar di sekolah.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Seluruh orang yang terdaftar sebagai penjemput.
     */
    public function pickupPersons(): HasMany
    {
        return $this->hasMany(PickupPerson::class);
    }

    /**
     * Kelas sekolah yang masih aktif.
     */
    public function activeSchoolClasses(): HasMany
    {
        return $this
            ->schoolClasses()
            ->where('is_active', true);
    }

    /**
     * Siswa yang masih aktif.
     */
    public function activeStudents(): HasMany
    {
        return $this
            ->students()
            ->where('status', Student::STATUS_ACTIVE);
    }

    /**
     * Penjemput yang masih aktif.
     */
    public function activePickupPersons(): HasMany
    {
        return $this
            ->pickupPersons()
            ->where('is_active', true);
    }

    /**
     * Scope untuk mengambil sekolah aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pencarian sekolah.
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
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            },
        );
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasActiveClasses(): bool
    {
        return $this
            ->activeSchoolClasses()
            ->exists();
    }

    public function hasActiveStudents(): bool
    {
        return $this
            ->activeStudents()
            ->exists();
    }

    public function hasActivePickupPersons(): bool
    {
        return $this
            ->activePickupPersons()
            ->exists();
    }
}