<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public function confirmedPickupEvents(): HasMany
    {
        return $this->hasMany(
            PickupEvent::class,
            'confirmed_by_user_id',
        );
    }

    use HasFactory;
    use Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SCHOOL_ADMIN = 'school_admin';

    public const ROLE_GATE_OFFICER = 'gate_officer';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_PARENT = 'parent';

    /**
     * @var list<string>
     */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_SCHOOL_ADMIN,
        self::ROLE_GATE_OFFICER,
        self::ROLE_TEACHER,
        self::ROLE_PARENT,
    ];

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'role',
        'phone',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isSchoolAdmin(): bool
    {
        return $this->role === self::ROLE_SCHOOL_ADMIN;
    }

    public function isGateOfficer(): bool
    {
        return $this->role === self::ROLE_GATE_OFFICER;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isParent(): bool
    {
        return $this->role === self::ROLE_PARENT;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
