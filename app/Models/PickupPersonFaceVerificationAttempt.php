<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PickupPersonFaceVerificationAttempt extends Model
{
    public function pickupEvent(): HasOne
    {
    return $this->hasOne(
        PickupEvent::class,
        'face_verification_attempt_id',
    );
    }
    public const RESULT_MATCH = 'match';

    public const RESULT_NO_MATCH = 'no_match';

    public const RESULT_AMBIGUOUS = 'ambiguous';

    public const RESULT_NO_CANDIDATES = 'no_candidates';

    public const RESULT_LOW_QUALITY = 'low_quality';

    public const RESULT_LIVENESS_FAILED =
        'liveness_failed';

    public const RESULT_MODEL_MISMATCH =
        'model_mismatch';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'school_id',
        'pickup_person_id',
        'verified_by_user_id',
        'result',
        'similarity_score',
        'similarity_threshold',
        'candidate_margin',
        'candidate_count',
        'quality_score',
        'liveness_passed',
        'live_score',
        'real_score',
        'model_name',
        'model_version',
        'embedding_dimension',
        'capture_method',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'similarity_score' =>
                'decimal:4',

            'similarity_threshold' =>
                'decimal:4',

            'candidate_margin' =>
                'decimal:4',

            'candidate_count' =>
                'integer',

            'quality_score' =>
                'decimal:4',

            'liveness_passed' =>
                'boolean',

            'live_score' =>
                'decimal:4',

            'real_score' =>
                'decimal:4',

            'embedding_dimension' =>
                'integer',

            'metadata' =>
                'array',

            'occurred_at' =>
                'datetime',
        ];
    }

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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'verified_by_user_id',
        );
    }
}