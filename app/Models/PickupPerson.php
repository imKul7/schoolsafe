<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickupPerson extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const FACE_NOT_REGISTERED = 'not_registered';

    public const FACE_REGISTERED = 'registered';

    public const FACE_NEEDS_UPDATE = 'needs_update';

    protected $table = 'pickup_persons';

    protected $fillable = [
        'school_id',
        'full_name',
        'identity_number',
        'phone',
        'email',
        'address',
        'photo_path',
        'face_status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Student::class,
                'pickup_person_student',
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

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasRegisteredFace(): bool
    {
        return $this->face_status === self::FACE_REGISTERED;
    }
}