<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PickupEventStudent extends Pivot
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Status penyerahan siswa
    |--------------------------------------------------------------------------
    */

    public const STATUS_RELEASED =
        'released';

    public const STATUS_CANCELLED =
        'cancelled';

    public const STATUSES = [
        self::STATUS_RELEASED,
        self::STATUS_CANCELLED,
    ];

    protected $table =
        'pickup_event_students';

    public $incrementing =
        true;

    protected $keyType =
        'int';

    protected $fillable = [
        'pickup_event_id',
        'student_id',
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
    ];

    protected function casts(): array
    {
        return [
            'pickup_event_id' => 'integer',

            'student_id' => 'integer',

            'cancelled_by_user_id' => 'integer',

            'is_primary' => 'boolean',

            'released_at' => 'datetime',

            'cancelled_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    public function pickupEvent(): BelongsTo
    {
        return $this->belongsTo(
            PickupEvent::class,
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(
            Student::class,
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
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent(
        Builder $query,
        int $pickupEventId,
    ): Builder {
        return $query->where(
            'pickup_event_id',
            $pickupEventId,
        );
    }

    public function scopeForStudent(
        Builder $query,
        int $studentId,
    ): Builder {
        return $query->where(
            'student_id',
            $studentId,
        );
    }

    public function scopeReleased(
        Builder $query,
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_RELEASED,
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

    public function scopeLatestFirst(
        Builder $query,
    ): Builder {
        return $query
            ->orderByDesc(
                'released_at',
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

    public function isReleased(): bool
    {
        return $this->status
            === self::STATUS_RELEASED;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === self::STATUS_CANCELLED;
    }

    public function canBeCancelled(): bool
    {
        return
            $this->isReleased()
            && $this->cancelled_at === null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RELEASED => 'Diserahkan',

            self::STATUS_CANCELLED => 'Dibatalkan',

            default => 'Tidak Diketahui',
        };
    }
}
