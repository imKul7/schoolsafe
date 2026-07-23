<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\VerifyPickupPersonFaceRequest;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceProfile;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GateFaceVerificationController extends Controller
{
    private const ACTION_BLINK = 'blink';

    private const ACTION_TURN_HEAD = 'turn_head';

    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        $this->authorizeGateVerification($user);

        return Inertia::render('gate/face-verification', [
            'verificationConfig' => [
                'minimum_quality_score' => (float) config(
                    'biometrics.minimum_quality_score',
                    0.75,
                ),

                'minimum_similarity' => (float) config(
                    'biometrics.matching.minimum_similarity',
                    0.60,
                ),

                'minimum_margin' => (float) config(
                    'biometrics.matching.minimum_margin',
                    0.05,
                ),

                'probe_samples' => (int) config(
                    'biometrics.matching.probe_samples',
                    3,
                ),

                'probe_delay_milliseconds' => (int) config(
                    'biometrics.matching.probe_delay_milliseconds',
                    550,
                ),

                'minimum_frame_quality' => (float) config(
                    'biometrics.matching.minimum_frame_quality',
                    0.50,
                ),

                'challenge' => [
                    'blink_min_ms' => (int) config(
                        'biometrics.challenge.blink_min_ms',
                        60,
                    ),

                    'blink_max_ms' => (int) config(
                        'biometrics.challenge.blink_max_ms',
                        900,
                    ),

                    'head_turn_yaw_delta' => (float) config(
                        'biometrics.challenge.head_turn_yaw_delta',
                        0.18,
                    ),

                    'center_yaw_tolerance' => (float) config(
                        'biometrics.challenge.center_yaw_tolerance',
                        0.10,
                    ),

                    'required_center_frames' => (int) config(
                        'biometrics.challenge.required_center_frames',
                        2,
                    ),

                    'maximum_duration_ms' => (int) config(
                        'biometrics.challenge.maximum_duration_ms',
                        30000,
                    ),

                    'frame_interval_ms' => (int) config(
                        'biometrics.challenge.frame_interval_ms',
                        100,
                    ),

                    'blink_close_ratio' => (float) config(
                        'biometrics.challenge.blink_close_ratio',
                        0.78,
                    ),

                    'baseline_frames' => (int) config(
                        'biometrics.challenge.baseline_frames',
                        4,
                    ),
                ],

                'security' => [
                    'cooldown_seconds' => (int) config(
                        'biometrics.security.cooldown_seconds',
                        5,
                    ),
                ],
            ],
        ]);
    }

    public function challenge(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $this->authorizeGateVerification($user);

        $schoolId = $this->schoolId($user);

        $this->assertVerificationIsNotLocked(
            $schoolId,
            $user,
            $request,
        );

        $this->assertEndpointRateLimit(
            key: $this->challengeRateLimitKey(
                $schoolId,
                $user,
                $request,
            ),
            maximumAttempts: $this->clampInteger(
                (int) config(
                    'biometrics.security.challenge_rate_limit_per_minute',
                    20,
                ),
                1,
                300,
            ),
            message:
                'Terlalu banyak permintaan challenge. Tunggu sebelum mencoba kembali.',
        );

        $ttlSeconds = $this->clampInteger(
            (int) config(
                'biometrics.challenge.ttl_seconds',
                45,
            ),
            15,
            180,
        );

        $challengeId = (string) Str::uuid();

        $sequence = random_int(0, 1) === 0
            ? [
                self::ACTION_BLINK,
                self::ACTION_TURN_HEAD,
            ]
            : [
                self::ACTION_TURN_HEAD,
                self::ACTION_BLINK,
            ];

        $activeChallengeKey = $this->activeChallengeCacheKey(
            $schoolId,
            $user,
            $request,
        );

        $previousChallengeId = Cache::get(
            $activeChallengeKey,
        );

        if (
            is_string($previousChallengeId)
            && $previousChallengeId !== ''
        ) {
            Cache::forget(
                $this->challengeCacheKey(
                    $previousChallengeId,
                ),
            );
        }

        $issuedAt = now();

        $expiresAt = $issuedAt
            ->copy()
            ->addSeconds($ttlSeconds);

        Cache::put(
            $this->challengeCacheKey($challengeId),
            [
                'id' => $challengeId,
                'school_id' => $schoolId,
                'user_id' => (int) $user->id,
                'session_id' => $request->session()->getId(),
                'sequence' => $sequence,
                'issued_at' => $issuedAt->toISOString(),
                'expires_at' => $expiresAt->toISOString(),
            ],
            $expiresAt,
        );

        Cache::put(
            $activeChallengeKey,
            $challengeId,
            $expiresAt,
        );

        return response()->json([
            'id' => $challengeId,
            'sequence' => $sequence,
            'expires_in' => $ttlSeconds,
        ]);
    }

    public function verify(
        VerifyPickupPersonFaceRequest $request,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);

        $this->authorizeGateVerification($user);

        $schoolId = $this->schoolId($user);

        $this->assertVerificationIsNotLocked(
            $schoolId,
            $user,
            $request,
        );

        $this->assertEndpointRateLimit(
            key: $this->verificationRateLimitKey(
                $schoolId,
                $user,
                $request,
            ),
            maximumAttempts: $this->clampInteger(
                (int) config(
                    'biometrics.security.verification_rate_limit_per_minute',
                    30,
                ),
                1,
                300,
            ),
            message:
                'Terlalu banyak permintaan verifikasi. Tunggu sebelum mencoba kembali.',
        );

        $this->acquireVerificationCooldown(
            $schoolId,
            $user,
            $request,
        );

        $validated = $request->validated();

        /** @var array<int, mixed> $rawEmbedding */
        $rawEmbedding = $validated['embedding'];

        $embedding = collect($rawEmbedding)
            ->map(
                static fn (mixed $value): float =>
                    (float) $value,
            )
            ->values()
            ->all();

        $this->assertFiniteEmbedding($embedding);

        $qualityScore = (float) (
            $validated['quality_score']
            ?? 0
        );

        $livenessPassed = (bool) (
            $validated['liveness_passed']
            ?? false
        );

        $liveScore = array_key_exists(
            'live_score',
            $validated,
        ) && $validated['live_score'] !== null
            ? (float) $validated['live_score']
            : null;

        $realScore = array_key_exists(
            'real_score',
            $validated,
        ) && $validated['real_score'] !== null
            ? (float) $validated['real_score']
            : null;

        $modelName = trim(
            (string) (
                $validated['model_name']
                ?? ''
            ),
        );

        $modelVersion = isset(
            $validated['model_version'],
        )
            ? $this->nullableString(
                $validated['model_version'],
            )
            : null;

        $captureMethod = trim(
            (string) (
                $validated['capture_method']
                ?? 'camera'
            ),
        );

        $clientMetadata = isset(
            $validated['metadata'],
        ) && is_array($validated['metadata'])
            ? $validated['metadata']
            : null;

        $minimumQualityScore = $this->clampFloat(
            (float) config(
                'biometrics.minimum_quality_score',
                0.75,
            ),
            0.0,
            1.0,
        );

        $minimumSimilarity = $this->clampFloat(
            (float) config(
                'biometrics.matching.minimum_similarity',
                0.60,
            ),
            0.0,
            1.0,
        );

        $minimumMargin = $this->clampFloat(
            (float) config(
                'biometrics.matching.minimum_margin',
                0.05,
            ),
            0.0,
            1.0,
        );

        $minimumLiveScore = $this->clampFloat(
            (float) config(
                'biometrics.liveness.minimum_live_score',
                0.80,
            ),
            0.0,
            1.0,
        );

        $minimumRealScore = $this->clampFloat(
            (float) config(
                'biometrics.liveness.minimum_real_score',
                0.80,
            ),
            0.0,
            1.0,
        );

        $expectedModelName = trim(
            (string) config(
                'biometrics.model_name',
                'human-hse-faceres',
            ),
        );

        try {
            $challengeSummary =
                $this->consumeAndValidateChallenge(
                    $validated,
                    $schoolId,
                    $user,
                    $request,
                );
        } catch (
            ValidationException $exception
        ) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_LIVENESS_FAILED,
                similarityThreshold:
                    $minimumSimilarity,
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    false,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $this->buildAuditMetadata(
                        $clientMetadata,
                        null,
                        'challenge_validation_failed',
                        $request,
                        $schoolId,
                        $user,
                    ),
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            throw $exception;
        }

        $auditMetadata =
            $this->buildAuditMetadata(
                $clientMetadata,
                $challengeSummary,
                null,
                $request,
                $schoolId,
                $user,
            );

        if ($modelName !== $expectedModelName) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_MODEL_MISMATCH,
                similarityThreshold:
                    $minimumSimilarity,
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    $livenessPassed,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            throw ValidationException::withMessages([
                'model_name' =>
                    'Model biometrik tidak sesuai dengan model sekolah.',
            ]);
        }

        if ($qualityScore < $minimumQualityScore) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_LOW_QUALITY,
                similarityThreshold:
                    $minimumSimilarity,
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    $livenessPassed,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            throw ValidationException::withMessages([
                'quality_score' => sprintf(
                    'Kualitas wajah minimal %.0f%%.',
                    $minimumQualityScore * 100,
                ),
            ]);
        }

        if (
            ! $livenessPassed
            || $liveScore === null
            || $realScore === null
            || $liveScore < $minimumLiveScore
            || $realScore < $minimumRealScore
        ) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_LIVENESS_FAILED,
                similarityThreshold:
                    $minimumSimilarity,
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    false,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            throw ValidationException::withMessages([
                'liveness_passed' =>
                    'Pemeriksaan liveness atau anti-spoofing belum lulus.',
            ]);
        }

        $maximumCandidates = $this->clampInteger(
            (int) config(
                'biometrics.matching.maximum_candidates',
                2000,
            ),
            1,
            10000,
        );

        $profiles =
            PickupPersonFaceProfile::query()
                ->where('school_id', $schoolId)
                ->where(
                    'status',
                    PickupPersonFaceProfile::STATUS_REGISTERED,
                )
                ->where('model_name', $modelName)
                ->where(
                    'embedding_dimension',
                    count($embedding),
                )
                ->whereNotNull('embedding')
                ->whereHas(
                    'pickupPerson',
                    function (
                        Builder $query,
                    ) use (
                        $schoolId,
                    ): void {
                        $query
                            ->where(
                                'school_id',
                                $schoolId,
                            )
                            ->where(
                                'is_active',
                                true,
                            )
                            ->where(
                                'face_status',
                                PickupPerson::FACE_REGISTERED,
                            );
                    },
                )
                ->with([
                    'pickupPerson' =>
                        static function (
                            Builder $query,
                        ): void {
                            $query->select([
                                'id',
                                'school_id',
                                'full_name',
                                'phone',
                                'photo_path',
                                'face_status',
                                'is_active',
                            ]);
                        },
                ])
                ->orderBy('id')
                ->limit($maximumCandidates)
                ->get();

        $ranked = $profiles
            ->map(
                function (
                    PickupPersonFaceProfile $profile,
                ) use (
                    $embedding,
                ): ?array {
                    try {
                        $storedEmbedding =
                            $profile->embedding;
                    } catch (Throwable) {
                        return null;
                    }

                    if (
                        ! is_array($storedEmbedding)
                        || count($storedEmbedding)
                            !== count($embedding)
                    ) {
                        return null;
                    }

                    $normalizedStoredEmbedding =
                        collect($storedEmbedding)
                            ->map(
                                static fn (
                                    mixed $value,
                                ): float =>
                                    (float) $value,
                            )
                            ->values()
                            ->all();

                    if (
                        ! $this->embeddingIsFinite(
                            $normalizedStoredEmbedding,
                        )
                    ) {
                        return null;
                    }

                    return [
                        'profile' => $profile,
                        'similarity' =>
                            $this->cosineSimilarity(
                                $embedding,
                                $normalizedStoredEmbedding,
                            ),
                    ];
                },
            )
            ->filter(
                static fn (mixed $value): bool =>
                    is_array($value),
            )
            ->sortByDesc('similarity')
            ->values();

        if ($ranked->isEmpty()) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_NO_CANDIDATES,
                similarityThreshold:
                    $minimumSimilarity,
                candidateCount:
                    $profiles->count(),
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    true,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            return $this->unmatchedResponse(
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_NO_CANDIDATES,
                message:
                    'Belum ada profil wajah aktif yang dapat dibandingkan.',
                threshold:
                    $minimumSimilarity,
            );
        }

        /** @var array{
         *     profile: PickupPersonFaceProfile,
         *     similarity: float
         * } $best
         */
        $best = $ranked->first();

        /** @var array{
         *     profile: PickupPersonFaceProfile,
         *     similarity: float
         * }|null $second
         */
        $second = $ranked->get(1);

        $bestSimilarity =
            (float) $best['similarity'];

        $margin = $second !== null
            ? max(
                0.0,
                $bestSimilarity
                    - (float) $second['similarity'],
            )
            : null;

        if ($bestSimilarity < $minimumSimilarity) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_NO_MATCH,
                similarityScore:
                    $bestSimilarity,
                similarityThreshold:
                    $minimumSimilarity,
                candidateMargin:
                    $margin,
                candidateCount:
                    $ranked->count(),
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    true,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            return $this->unmatchedResponse(
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_NO_MATCH,
                message:
                    'Wajah tidak cocok dengan penjemput terdaftar.',
                threshold:
                    $minimumSimilarity,
                similarity:
                    $bestSimilarity,
                margin:
                    $margin,
            );
        }

        if (
            $margin !== null
            && $margin < $minimumMargin
        ) {
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_AMBIGUOUS,
                similarityScore:
                    $bestSimilarity,
                similarityThreshold:
                    $minimumSimilarity,
                candidateMargin:
                    $margin,
                candidateCount:
                    $ranked->count(),
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    true,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

            $this->registerVerificationFailure(
                $schoolId,
                $user,
                $request,
            );

            return $this->unmatchedResponse(
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_AMBIGUOUS,
                message:
                    'Dua kandidat memiliki skor terlalu dekat. Lakukan verifikasi manual.',
                threshold:
                    $minimumSimilarity,
                similarity:
                    $bestSimilarity,
                margin:
                    $margin,
            );
        }

        /** @var PickupPersonFaceProfile $bestProfile */
        $bestProfile = $best['profile'];

        $pickupPerson =
            $bestProfile->pickupPerson;

        abort_unless(
            $pickupPerson instanceof PickupPerson,
            404,
            'Data penjemput tidak ditemukan.',
        );

        abort_unless(
            (int) $pickupPerson->school_id
                === $schoolId,
            404,
            'Data penjemput tidak ditemukan.',
        );

        $today = $this->schoolToday(
            $schoolId,
        );

        $pickupPerson->load([
            'students' =>
                function (
                    Builder $query,
                ) use (
                    $schoolId,
                    $today,
                ): void {
                    $query
                        ->where(
                            'students.school_id',
                            $schoolId,
                        )
                        ->where(
                            'pickup_person_student.school_id',
                            $schoolId,
                        )
                        ->where(
                            'pickup_person_student.is_active',
                            true,
                        )
                        ->where(
                            'students.status',
                            Student::STATUS_ACTIVE,
                        )
                        ->where(
                            function (
                                Builder $query,
                            ) use (
                                $today,
                            ): void {
                                $query
                                    ->whereNull(
                                        'pickup_person_student.valid_from',
                                    )
                                    ->orWhereDate(
                                        'pickup_person_student.valid_from',
                                        '<=',
                                        $today,
                                    );
                            },
                        )
                        ->where(
                            function (
                                Builder $query,
                            ) use (
                                $today,
                            ): void {
                                $query
                                    ->whereNull(
                                        'pickup_person_student.valid_until',
                                    )
                                    ->orWhereDate(
                                        'pickup_person_student.valid_until',
                                        '>=',
                                        $today,
                                    );
                            },
                        )
                        ->with([
                            'schoolClass:id,name,grade_level,academic_year',
                        ])
                        ->orderBy(
                            'students.full_name',
                        )
                        ->orderBy(
                            'students.id',
                        );
                },
        ]);

        $verificationAttempt =
            $this->recordAttempt(
                request: $request,
                schoolId: $schoolId,
                user: $user,
                result:
                    PickupPersonFaceVerificationAttempt::RESULT_MATCH,
                pickupPerson:
                    $pickupPerson,
                similarityScore:
                    $bestSimilarity,
                similarityThreshold:
                    $minimumSimilarity,
                candidateMargin:
                    $margin,
                candidateCount:
                    $ranked->count(),
                qualityScore:
                    $qualityScore,
                livenessPassed:
                    true,
                liveScore:
                    $liveScore,
                realScore:
                    $realScore,
                modelName:
                    $modelName,
                modelVersion:
                    $modelVersion,
                embeddingDimension:
                    count($embedding),
                captureMethod:
                    $captureMethod,
                metadata:
                    $auditMetadata,
            );

        RateLimiter::clear(
            $this->verificationFailureKey(
                $schoolId,
                $user,
                $request,
            ),
        );

        return response()->json([
            'matched' => true,
            'result' =>
                PickupPersonFaceVerificationAttempt::RESULT_MATCH,
            'message' =>
                'Wajah cocok dengan penjemput terdaftar.',
            'similarity' => $bestSimilarity,
            'threshold' => $minimumSimilarity,
            'margin' => $margin,
            'verification_attempt_id' =>
                (int) $verificationAttempt->id,

            'pickup_person' => [
                'id' =>
                    (int) $pickupPerson->id,

                'full_name' =>
                    (string) $pickupPerson->full_name,

                'phone' =>
                    $this->nullableString(
                        $pickupPerson->phone,
                    ),

                'photo_url' =>
                    $this->photoUrl(
                        $pickupPerson->photo_path,
                    ),

                'students' =>
                    $pickupPerson
                        ->students
                        ->map(
                            fn (
                                Student $student,
                            ): array => [
                                'id' =>
                                    (int) $student->id,

                                'full_name' =>
                                    (string) $student->full_name,

                                'student_number' =>
                                    $this->nullableString(
                                        $student->student_number,
                                    ),

                                'class_name' =>
                                    $this->nullableString(
                                        $student
                                            ->schoolClass
                                            ?->name,
                                    ),

                                'academic_year' =>
                                    $this->nullableString(
                                        $student
                                            ->schoolClass
                                            ?->academic_year,
                                    ),

                                'relationship_type' =>
                                    (string) $student
                                        ->pivot
                                        ->relationship_type,

                                'is_primary' =>
                                    (bool) $student
                                        ->pivot
                                        ->is_primary,
                            ],
                        )
                        ->values()
                        ->all(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    private function consumeAndValidateChallenge(
        array $validated,
        int $schoolId,
        User $user,
        Request $request,
    ): array {
        $challengeId = trim(
            (string) (
                $validated['challenge_id']
                ?? ''
            ),
        );

        $activeChallengeKey =
            $this->activeChallengeCacheKey(
                $schoolId,
                $user,
                $request,
            );

        $activeChallengeId =
            Cache::get($activeChallengeKey);

        if (
            ! is_string($activeChallengeId)
            || $challengeId === ''
            || ! hash_equals(
                $activeChallengeId,
                $challengeId,
            )
        ) {
            if ($challengeId !== '') {
                Cache::forget(
                    $this->challengeCacheKey(
                        $challengeId,
                    ),
                );
            }

            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge tidak aktif atau telah digantikan oleh challenge baru.',
            ]);
        }

        $challenge = Cache::pull(
            $this->challengeCacheKey(
                $challengeId,
            ),
        );

        Cache::forget($activeChallengeKey);

        if (! is_array($challenge)) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge tidak ditemukan, telah digunakan, atau sudah kedaluwarsa.',
            ]);
        }

        if (
            ! isset($challenge['id'])
            || ! is_string($challenge['id'])
            || ! hash_equals(
                $challenge['id'],
                $challengeId,
            )
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Identitas challenge tidak valid.',
            ]);
        }

        if (
            (int) (
                $challenge['school_id']
                ?? 0
            ) !== $schoolId
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge berasal dari sekolah yang berbeda.',
            ]);
        }

        if (
            (int) (
                $challenge['user_id']
                ?? 0
            ) !== (int) $user->id
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge dibuat oleh pengguna yang berbeda.',
            ]);
        }

        $sessionId =
            $request->session()->getId();

        if (
            ! isset($challenge['session_id'])
            || ! is_string(
                $challenge['session_id'],
            )
            || ! hash_equals(
                $challenge['session_id'],
                $sessionId,
            )
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge berasal dari sesi yang berbeda.',
            ]);
        }

        try {
            $expiration =
                isset($challenge['expires_at'])
                && is_string(
                    $challenge['expires_at'],
                )
                    ? CarbonImmutable::parse(
                        $challenge['expires_at'],
                    )
                    : null;
        } catch (Throwable) {
            $expiration = null;
        }

        if (
            ! $expiration
                instanceof CarbonImmutable
            || now()->greaterThanOrEqualTo(
                $expiration,
            )
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Challenge sudah kedaluwarsa. Jalankan verifikasi ulang.',
            ]);
        }

        $expectedSequence =
            $challenge['sequence']
            ?? null;

        if (
            ! is_array($expectedSequence)
            || count($expectedSequence) !== 2
            || array_values(
                array_unique($expectedSequence),
            ) !== array_values(
                $expectedSequence,
            )
            || array_diff(
                $expectedSequence,
                [
                    self::ACTION_BLINK,
                    self::ACTION_TURN_HEAD,
                ],
            ) !== []
        ) {
            throw ValidationException::withMessages([
                'challenge_id' =>
                    'Urutan challenge dari server tidak valid.',
            ]);
        }

        $evidence =
            $validated['challenge_evidence']
            ?? null;

        if (! is_array($evidence)) {
            throw ValidationException::withMessages([
                'challenge_evidence' =>
                    'Bukti challenge tidak tersedia.',
            ]);
        }

        $completedActions =
            $evidence['completed_actions']
            ?? null;

        if (
            ! is_array($completedActions)
            || array_values($completedActions)
                !== array_values($expectedSequence)
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.completed_actions' =>
                    'Urutan challenge yang diselesaikan tidak sesuai.',
            ]);
        }

        $blinkMinMs = $this->clampInteger(
            (int) config(
                'biometrics.challenge.blink_min_ms',
                60,
            ),
            10,
            2000,
        );

        $blinkMaxMs = $this->clampInteger(
            (int) config(
                'biometrics.challenge.blink_max_ms',
                900,
            ),
            $blinkMinMs,
            3000,
        );

        $blinkDurationMs =
            (int) (
                $evidence['blink_duration_ms']
                ?? 0
            );

        if (
            $blinkDurationMs < $blinkMinMs
            || $blinkDurationMs > $blinkMaxMs
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.blink_duration_ms' =>
                    sprintf(
                        'Durasi kedipan harus berada di antara %d dan %d milidetik.',
                        $blinkMinMs,
                        $blinkMaxMs,
                    ),
            ]);
        }

        $headTurnYawDelta = $this->clampFloat(
            (float) config(
                'biometrics.challenge.head_turn_yaw_delta',
                0.18,
            ),
            0.03,
            1.50,
        );

        $maximumYawDelta =
            (float) (
                $evidence['maximum_yaw_delta']
                ?? 0
            );

        if (
            ! is_finite($maximumYawDelta)
            || $maximumYawDelta
                < $headTurnYawDelta
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.maximum_yaw_delta' =>
                    'Gerakan kepala belum mencapai batas minimum.',
            ]);
        }

        if (
            ! (bool) (
                $evidence['returned_to_center']
                ?? false
            )
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.returned_to_center' =>
                    'Wajah belum kembali menghadap lurus ke kamera.',
            ]);
        }

        $maximumDurationMs = $this->clampInteger(
            (int) config(
                'biometrics.challenge.maximum_duration_ms',
                30000,
            ),
            5000,
            60000,
        );

        $durationMs =
            (int) (
                $evidence['duration_ms']
                ?? 0
            );

        if (
            $durationMs <= 0
            || $durationMs > $maximumDurationMs
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.duration_ms' =>
                    'Durasi challenge tidak valid atau telah melewati batas.',
            ]);
        }

        $sampleCount =
            (int) (
                $evidence['sample_count']
                ?? 0
            );

        if (
            $sampleCount <= 0
            || $sampleCount > 10000
        ) {
            throw ValidationException::withMessages([
                'challenge_evidence.sample_count' =>
                    'Jumlah sampel challenge tidak valid.',
            ]);
        }

        return [
            'challenge_id_hash' =>
                hash('sha256', $challengeId),

            'sequence' =>
                array_values($expectedSequence),

            'completed_actions' =>
                array_values($completedActions),

            'blink_duration_ms' =>
                $blinkDurationMs,

            'maximum_yaw_delta' =>
                round($maximumYawDelta, 4),

            'returned_to_center' =>
                true,

            'duration_ms' =>
                $durationMs,

            'sample_count' =>
                $sampleCount,
        ];
    }

    private function unmatchedResponse(
        string $result,
        string $message,
        float $threshold,
        ?float $similarity = null,
        ?float $margin = null,
    ): JsonResponse {
        return response()->json([
            'matched' => false,
            'result' => $result,
            'message' => $message,
            'similarity' => $similarity,
            'threshold' => $threshold,
            'margin' => $margin,
            'verification_attempt_id' => null,
            'pickup_person' => null,
        ]);
    }

    /**
     * @param array<int, float> $first
     * @param array<int, float> $second
     */
    private function cosineSimilarity(
        array $first,
        array $second,
    ): float {
        if (
            $first === []
            || count($first)
                !== count($second)
        ) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $firstNorm = 0.0;
        $secondNorm = 0.0;

        foreach ($first as $index => $value) {
            $secondValue = $second[$index];

            $dotProduct +=
                $value * $secondValue;

            $firstNorm +=
                $value * $value;

            $secondNorm +=
                $secondValue * $secondValue;
        }

        if (
            $firstNorm <= 0.0
            || $secondNorm <= 0.0
        ) {
            return 0.0;
        }

        $similarity =
            $dotProduct
            / (
                sqrt($firstNorm)
                * sqrt($secondNorm)
            );

        return round(
            max(
                0.0,
                min(1.0, $similarity),
            ),
            4,
        );
    }

    /**
     * @param array<int, float> $embedding
     */
    private function assertFiniteEmbedding(
        array $embedding,
    ): void {
        if (
            ! $this->embeddingIsFinite(
                $embedding,
            )
        ) {
            throw ValidationException::withMessages([
                'embedding' =>
                    'Descriptor wajah mengandung nilai yang tidak valid.',
            ]);
        }
    }

    /**
     * @param array<int, float> $embedding
     */
    private function embeddingIsFinite(
        array $embedding,
    ): bool {
        if ($embedding === []) {
            return false;
        }

        foreach ($embedding as $value) {
            if (! is_finite($value)) {
                return false;
            }
        }

        return true;
    }

    private function recordAttempt(
        Request $request,
        int $schoolId,
        User $user,
        string $result,
        float $similarityThreshold,
        ?PickupPerson $pickupPerson = null,
        ?float $similarityScore = null,
        ?float $candidateMargin = null,
        int $candidateCount = 0,
        ?float $qualityScore = null,
        bool $livenessPassed = false,
        ?float $liveScore = null,
        ?float $realScore = null,
        string $modelName = '',
        ?string $modelVersion = null,
        int $embeddingDimension = 0,
        string $captureMethod = 'camera',
        ?array $metadata = null,
    ): PickupPersonFaceVerificationAttempt {
        $storeIp = (bool) config(
            'biometrics.audit.store_ip_address',
            config(
                'biometrics.audit.store_ip',
                true,
            ),
        );

        $storeUserAgent = (bool) config(
            'biometrics.audit.store_user_agent',
            true,
        );

        return PickupPersonFaceVerificationAttempt::query()
            ->create([
                'school_id' => $schoolId,
                'pickup_person_id' =>
                    $pickupPerson?->id,
                'verified_by_user_id' =>
                    $user->id,
                'result' => $result,
                'similarity_score' =>
                    $similarityScore,
                'similarity_threshold' =>
                    $similarityThreshold,
                'candidate_margin' =>
                    $candidateMargin,
                'candidate_count' =>
                    max(0, $candidateCount),
                'quality_score' =>
                    $qualityScore,
                'liveness_passed' =>
                    $livenessPassed,
                'live_score' =>
                    $liveScore,
                'real_score' =>
                    $realScore,
                'model_name' =>
                    $modelName,
                'model_version' =>
                    $modelVersion,
                'embedding_dimension' =>
                    max(0, $embeddingDimension),
                'capture_method' =>
                    $captureMethod !== ''
                        ? $captureMethod
                        : 'camera',
                'ip_address' =>
                    $storeIp
                        ? $request->ip()
                        : null,
                'user_agent' =>
                    $storeUserAgent
                        ? $request->userAgent()
                        : null,
                'metadata' =>
                    $metadata,
                'occurred_at' =>
                    now(),
            ]);
    }

    /**
     * @param array<string, mixed>|null $clientMetadata
     * @param array<string, mixed>|null $challengeSummary
     *
     * @return array<string, mixed>
     */
    private function buildAuditMetadata(
        ?array $clientMetadata,
        ?array $challengeSummary,
        ?string $failureReason,
        Request $request,
        int $schoolId,
        User $user,
    ): array {
        $metadata = $this->sanitizeMetadata(
            $clientMetadata ?? [],
        );

        if ($challengeSummary !== null) {
            $metadata['challenge'] =
                $challengeSummary;
        }

        if ($failureReason !== null) {
            $metadata['failure_reason'] =
                $failureReason;
        }

        $metadata['security'] = [
            'session_binding' =>
                $this->sessionBindingHash(
                    $request,
                    $schoolId,
                    $user,
                ),

            'verified_by_user_id' =>
                (int) $user->id,

            'school_id' =>
                $schoolId,
        ];

        return $metadata;
    }

    /**
     * @param array<mixed> $metadata
     *
     * @return array<mixed>
     */
    private function sanitizeMetadata(
        array $metadata,
        int $depth = 0,
    ): array {
        if ($depth >= 5) {
            return [];
        }

        $sanitized = [];
        $processed = 0;

        foreach ($metadata as $key => $value) {
            if ($processed >= 100) {
                break;
            }

            $normalizedKey = strtolower(
                trim((string) $key),
            );

            if (
                str_contains(
                    $normalizedKey,
                    'embedding',
                )
                || str_contains(
                    $normalizedKey,
                    'descriptor',
                )
                || str_contains(
                    $normalizedKey,
                    'vector',
                )
            ) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] =
                    $this->sanitizeMetadata(
                        $value,
                        $depth + 1,
                    );

                $processed++;

                continue;
            }

            if (
                is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
                || $value === null
            ) {
                $sanitized[$key] =
                    is_string($value)
                        ? Str::limit(
                            $value,
                            1000,
                            '',
                        )
                        : $value;

                $processed++;
            }
        }

        return $sanitized;
    }

    private function assertVerificationIsNotLocked(
        int $schoolId,
        User $user,
        Request $request,
    ): void {
        $key = $this->verificationFailureKey(
            $schoolId,
            $user,
            $request,
        );

        if (
            ! RateLimiter::tooManyAttempts(
                $key,
                $this->maximumFailedAttempts(),
            )
        ) {
            return;
        }

        $retryAfter = max(
            1,
            RateLimiter::availableIn($key),
        );

        throw new HttpResponseException(
            response()->json(
                [
                    'message' =>
                        'Terlalu banyak verifikasi gagal. Tunggu sebelum mencoba kembali.',

                    'retry_after' =>
                        $retryAfter,
                ],
                429,
            ),
        );
    }

    private function registerVerificationFailure(
        int $schoolId,
        User $user,
        Request $request,
    ): void {
        $key = $this->verificationFailureKey(
            $schoolId,
            $user,
            $request,
        );

        $attempts = RateLimiter::hit(
            $key,
            $this->failedAttemptDecaySeconds(),
        );

        if (
            $attempts
                < $this->maximumFailedAttempts()
        ) {
            return;
        }

        $retryAfter = max(
            1,
            RateLimiter::availableIn($key),
        );

        throw new HttpResponseException(
            response()->json(
                [
                    'message' =>
                        'Batas verifikasi gagal telah tercapai. Akun gerbang dikunci sementara.',

                    'retry_after' =>
                        $retryAfter,
                ],
                429,
            ),
        );
    }

    private function acquireVerificationCooldown(
        int $schoolId,
        User $user,
        Request $request,
    ): void {
        $cooldownSeconds =
            $this->clampInteger(
                (int) config(
                    'biometrics.security.cooldown_seconds',
                    5,
                ),
                1,
                300,
            );

        $key = $this->verificationCooldownKey(
            $schoolId,
            $user,
            $request,
        );

        $expiresAt = now()
            ->addSeconds($cooldownSeconds)
            ->timestamp;

        if (
            Cache::add(
                $key,
                $expiresAt,
                $cooldownSeconds,
            )
        ) {
            return;
        }

        $storedExpiration = (int) Cache::get(
            $key,
            $expiresAt,
        );

        $retryAfter = max(
            1,
            $storedExpiration
                - now()->timestamp,
        );

        throw new HttpResponseException(
            response()->json(
                [
                    'message' =>
                        'Verifikasi sebelumnya masih diproses. Tunggu beberapa detik.',

                    'retry_after' =>
                        $retryAfter,
                ],
                429,
            ),
        );
    }

    private function assertEndpointRateLimit(
        string $key,
        int $maximumAttempts,
        string $message,
    ): void {
        if (
            RateLimiter::tooManyAttempts(
                $key,
                $maximumAttempts,
            )
        ) {
            throw new HttpResponseException(
                response()->json(
                    [
                        'message' => $message,
                        'retry_after' => max(
                            1,
                            RateLimiter::availableIn(
                                $key,
                            ),
                        ),
                    ],
                    429,
                ),
            );
        }

        RateLimiter::hit($key, 60);
    }

    private function maximumFailedAttempts(): int
    {
        return $this->clampInteger(
            (int) config(
                'biometrics.security.maximum_failed_attempts',
                5,
            ),
            1,
            100,
        );
    }

    private function failedAttemptDecaySeconds(): int
    {
        return $this->clampInteger(
            (int) config(
                'biometrics.security.failed_attempt_decay_seconds',
                300,
            ),
            30,
            86400,
        );
    }

    private function challengeCacheKey(
        string $challengeId,
    ): string {
        return sprintf(
            'gate-face-challenge:%s',
            $challengeId,
        );
    }

    private function activeChallengeCacheKey(
        int $schoolId,
        User $user,
        Request $request,
    ): string {
        return sprintf(
            'gate-face-active-challenge:%d:%d:%s',
            $schoolId,
            $user->id,
            hash(
                'sha256',
                $request->session()->getId(),
            ),
        );
    }

    private function verificationCooldownKey(
        int $schoolId,
        User $user,
        Request $request,
    ): string {
        return sprintf(
            'gate-face-cooldown:%d:%d:%s',
            $schoolId,
            $user->id,
            sha1((string) $request->ip()),
        );
    }

    private function verificationFailureKey(
        int $schoolId,
        User $user,
        Request $request,
    ): string {
        return sprintf(
            'gate-face-failures:%d:%d:%s',
            $schoolId,
            $user->id,
            sha1((string) $request->ip()),
        );
    }

    private function challengeRateLimitKey(
        int $schoolId,
        User $user,
        Request $request,
    ): string {
        return sprintf(
            'gate-face-challenge-rate:%d:%d:%s',
            $schoolId,
            $user->id,
            sha1((string) $request->ip()),
        );
    }

    private function verificationRateLimitKey(
        int $schoolId,
        User $user,
        Request $request,
    ): string {
        return sprintf(
            'gate-face-verification-rate:%d:%d:%s',
            $schoolId,
            $user->id,
            sha1((string) $request->ip()),
        );
    }

    private function sessionBindingHash(
        Request $request,
        int $schoolId,
        User $user,
    ): string {
        $applicationKey = (string) config(
            'app.key',
            'schoolsafe-session-binding',
        );

        return hash_hmac(
            'sha256',
            implode('|', [
                $request->session()->getId(),
                (string) $schoolId,
                (string) $user->id,
            ]),
            $applicationKey !== ''
                ? $applicationKey
                : 'schoolsafe-session-binding',
        );
    }

    private function schoolToday(
        int $schoolId,
    ): string {
        $school = School::query()
            ->find($schoolId);

        $timezone = $school
            ? $school->getAttribute(
                'timezone',
            )
            : null;

        if (
            ! is_string($timezone)
            || trim($timezone) === ''
        ) {
            $timezone = (string) config(
                'app.timezone',
                'UTC',
            );
        }

        try {
            return now(
                trim($timezone),
            )->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function authenticatedUser(
        Request $request,
    ): User {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Silakan masuk untuk melanjutkan.',
        );

        abort_unless(
            (bool) $user->is_active,
            403,
            'Akun Anda sedang tidak aktif.',
        );

        return $user;
    }

    private function schoolId(
        User $user,
    ): int {
        abort_if(
            $user->school_id === null
            || (int) $user->school_id <= 0,
            403,
            'Akun belum terhubung dengan sekolah.',
        );

        return (int) $user->school_id;
    }

    private function authorizeGateVerification(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_GATE_OFFICER,
            ),
            403,
            'Hanya administrator dan petugas gerbang yang dapat melakukan verifikasi wajah.',
        );
    }

    private function photoUrl(
        ?string $photoPath,
    ): ?string {
        if (
            $photoPath === null
            || trim($photoPath) === ''
        ) {
            return null;
        }

        return Storage::disk('public')
            ->url($photoPath);
    }

    private function nullableString(
        mixed $value,
    ): ?string {
        if (
            ! is_string($value)
            && ! is_numeric($value)
        ) {
            return null;
        }

        $normalized = trim(
            (string) $value,
        );

        return $normalized !== ''
            ? $normalized
            : null;
    }

    private function clampInteger(
        int $value,
        int $minimum,
        int $maximum,
    ): int {
        return min(
            $maximum,
            max($minimum, $value),
        );
    }

    private function clampFloat(
        float $value,
        float $minimum,
        float $maximum,
    ): float {
        if (! is_finite($value)) {
            return $minimum;
        }

        return min(
            $maximum,
            max($minimum, $value),
        );
    }
}