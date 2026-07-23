<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelPickupEventRequest;
use App\Http\Requests\CancelPickupEventStudentRequest;
use App\Http\Requests\ConfirmPickupEventRequest;
use App\Http\Requests\GatePickupEventHistoryRequest;
use App\Models\PickupEvent;
use App\Models\PickupEventStudent;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceVerificationAttempt;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class GatePickupEventController extends Controller
{
    public function index(
        GatePickupEventHistoryRequest $request,
    ): Response {
        $user = $this->authenticatedUser(
            $request,
        );

        $this->authorizeGateAccess($user);

        $schoolId = $this->schoolId($user);

        [
            $confirmedFrom,
            $confirmedTo,
        ] = $this->confirmedAtBounds(
            $schoolId,
            $request->dateFrom(),
            $request->dateTo(),
        );

        $baseQuery = PickupEvent::query()
            ->forSchool($schoolId);

        $this->applyHistoryFilters(
            $baseQuery,
            $request,
            $confirmedFrom,
            $confirmedTo,
        );

        $totalTransactions =
            (clone $baseQuery)->count();

        $confirmedTransactions =
            (clone $baseQuery)
                ->where(
                    'status',
                    PickupEvent::STATUS_CONFIRMED,
                )
                ->count();

        $cancelledTransactions =
            (clone $baseQuery)
                ->where(
                    'status',
                    PickupEvent::STATUS_CANCELLED,
                )
                ->count();

        $eventIdsQuery =
            (clone $baseQuery)
                ->select('pickup_events.id');

        $releasedStudents =
            PickupEventStudent::query()
                ->whereIn(
                    'pickup_event_id',
                    clone $eventIdsQuery,
                )
                ->where(
                    'status',
                    PickupEventStudent::STATUS_RELEASED,
                )
                ->count();

        $cancelledStudents =
            PickupEventStudent::query()
                ->whereIn(
                    'pickup_event_id',
                    clone $eventIdsQuery,
                )
                ->where(
                    'status',
                    PickupEventStudent::STATUS_CANCELLED,
                )
                ->count();

        $pickupEvents =
            (clone $baseQuery)
                ->with([
                    'confirmedBy:id,name',
                    'cancelledBy:id,name',
                ])
                ->withCount([
                    'eventStudents',
                    'releasedEventStudents',
                    'cancelledEventStudents',
                ])
                ->latestFirst()
                ->paginate(
                    $request->perPage(),
                )
                ->withQueryString();

        $pickupEvents->through(
            fn (
                PickupEvent $event,
            ): array =>
                $this->historyItemPayload(
                    $event,
                    $user,
                ),
        );

        $officers = User::query()
            ->where(
                'school_id',
                $schoolId,
            )
            ->where(
                'is_active',
                true,
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->filter(
                static fn (
                    User $officer,
                ): bool =>
                    $officer->hasRole(
                        User::ROLE_SCHOOL_ADMIN,
                        User::ROLE_GATE_OFFICER,
                    ),
            )
            ->map(
                static fn (
                    User $officer,
                ): array => [
                    'id' =>
                        (int) $officer->id,

                    'name' =>
                        (string) $officer->name,
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'gate/pickup-events/index',
            [
                'pickupEvents' =>
                    $pickupEvents,

                'summary' => [
                    'total_transactions' =>
                        $totalTransactions,

                    'confirmed_transactions' =>
                        $confirmedTransactions,

                    'cancelled_transactions' =>
                        $cancelledTransactions,

                    'released_students' =>
                        $releasedStudents,

                    'cancelled_students' =>
                        $cancelledStudents,
                ],

                'filters' => [
                    'date_from' =>
                        $request->dateFrom(),

                    'date_to' =>
                        $request->dateTo(),

                    'status' =>
                        $request->status(),

                    'verification_method' =>
                        $request
                            ->verificationMethod(),

                    'confirmed_by_user_id' =>
                        $request
                            ->confirmedByUserId(),

                    'search' =>
                        $request->searchTerm(),

                    'per_page' =>
                        $request->perPage(),
                ],

                'filterOptions' => [
                    'statuses' => [
                        [
                            'value' =>
                                PickupEvent::STATUS_CONFIRMED,

                            'label' =>
                                'Dikonfirmasi',
                        ],
                        [
                            'value' =>
                                PickupEvent::STATUS_CANCELLED,

                            'label' =>
                                'Dibatalkan',
                        ],
                    ],

                    'verification_methods' => [
                        [
                            'value' =>
                                PickupEvent::VERIFICATION_METHOD_FACE,

                            'label' =>
                                'Verifikasi Wajah',
                        ],
                        [
                            'value' =>
                                PickupEvent::VERIFICATION_METHOD_MANUAL,

                            'label' =>
                                'Verifikasi Manual',
                        ],
                    ],

                    'officers' =>
                        $officers,

                    'per_page_options' => [
                        10,
                        15,
                        25,
                        50,
                    ],
                ],
            ],
        );
    }

    public function show(
        Request $request,
        int $pickupEvent,
    ): JsonResponse {
        $user = $this->authenticatedUser(
            $request,
        );

        $this->authorizeGateAccess($user);

        $schoolId = $this->schoolId($user);

        $event = $this->findEventForSchool(
            $schoolId,
            $pickupEvent,
        );

        return response()->json([
            'pickup_event' =>
                $this->eventDetailPayload(
                    $event,
                    $user,
                ),
        ]);
    }

    public function store(
        ConfirmPickupEventRequest $request,
    ): JsonResponse {
        $user = $this->authenticatedUser(
            $request,
        );

        $this->authorizeGateAccess($user);

        $schoolId = $this->schoolId($user);

        $attemptId =
            $request->verificationAttemptId();

        $studentIds = array_values(
            array_unique(
                array_map(
                    static fn (
                        mixed $studentId,
                    ): int =>
                        (int) $studentId,
                    $request->studentIds(),
                ),
            ),
        );

        sort($studentIds);

        $notes = $this->nullableString(
            $request->notes(),
        );

        $idempotencyKey =
            $request->idempotencyKey();

        $sessionBinding =
            $this->sessionBindingHash(
                $request,
                $schoolId,
                $user,
            );

        $requestFingerprint =
            $this->requestFingerprint(
                schoolId:
                    $schoolId,
                userId:
                    (int) $user->id,
                attemptId:
                    $attemptId,
                studentIds:
                    $studentIds,
                notes:
                    $notes,
                sessionBinding:
                    $sessionBinding,
            );

        $today = $this->schoolToday(
            $schoolId,
        );

        try {
            $transactionResult =
                DB::transaction(
                    function () use (
                        $request,
                        $user,
                        $schoolId,
                        $attemptId,
                        $studentIds,
                        $notes,
                        $idempotencyKey,
                        $sessionBinding,
                        $requestFingerprint,
                        $today,
                    ): array {
                        $existingByIdempotency =
                            PickupEvent::query()
                                ->where(
                                    'idempotency_key',
                                    $idempotencyKey,
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($existingByIdempotency) {
                            $this->ensureReplayMatchesRequest(
                                event:
                                    $existingByIdempotency,
                                schoolId:
                                    $schoolId,
                                userId:
                                    (int) $user->id,
                                attemptId:
                                    $attemptId,
                                requestFingerprint:
                                    $requestFingerprint,
                                sessionBinding:
                                    $sessionBinding,
                            );

                            return [
                                'event' =>
                                    $existingByIdempotency,

                                'replayed' =>
                                    true,
                            ];
                        }

                        $attempt =
                            PickupPersonFaceVerificationAttempt::query()
                                ->whereKey($attemptId)
                                ->where(
                                    'school_id',
                                    $schoolId,
                                )
                                ->lockForUpdate()
                                ->first();

                        if (! $attempt) {
                            throw ValidationException::withMessages([
                                'face_verification_attempt_id' =>
                                    'Hasil verifikasi wajah tidak ditemukan.',
                            ]);
                        }

                        $this->validateVerificationAttempt(
                            $attempt,
                            $user,
                            $request,
                            $schoolId,
                        );

                        $existingByAttempt =
                            PickupEvent::query()
                                ->where(
                                    'face_verification_attempt_id',
                                    $attempt->id,
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($existingByAttempt) {
                            throw new ConflictHttpException(
                                'Hasil verifikasi wajah ini sudah digunakan untuk transaksi penjemputan.',
                            );
                        }

                        $pickupPerson =
                            PickupPerson::query()
                                ->whereKey(
                                    (int) $attempt
                                        ->pickup_person_id,
                                )
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
                                )
                                ->lockForUpdate()
                                ->first();

                        if (! $pickupPerson) {
                            throw ValidationException::withMessages([
                                'face_verification_attempt_id' =>
                                    'Penjemput tidak aktif atau tidak ditemukan.',
                            ]);
                        }

                        $students =
                            $this->authorizedStudents(
                                schoolId:
                                    $schoolId,
                                pickupPersonId:
                                    (int) $pickupPerson->id,
                                studentIds:
                                    $studentIds,
                                today:
                                    $today,
                            );

                        $authorizedStudentIds =
                            $students
                                ->pluck('id')
                                ->map(
                                    static fn (
                                        mixed $studentId,
                                    ): int =>
                                        (int) $studentId,
                                )
                                ->unique()
                                ->sort()
                                ->values()
                                ->all();

                        if (
                            $authorizedStudentIds
                                !== $studentIds
                        ) {
                            $invalidStudentIds =
                                array_values(
                                    array_diff(
                                        $studentIds,
                                        $authorizedStudentIds,
                                    ),
                                );

                            throw ValidationException::withMessages([
                                'student_ids' =>
                                    $invalidStudentIds === []
                                        ? 'Daftar siswa tidak valid.'
                                        : sprintf(
                                            'Siswa dengan ID %s tidak aktif, berbeda sekolah, atau tidak sah dijemput oleh penjemput ini.',
                                            implode(
                                                ', ',
                                                $invalidStudentIds,
                                            ),
                                        ),
                            ]);
                        }

                        $confirmedAt = now();

                        $event = PickupEvent::query()
                            ->create([
                                'school_id' =>
                                    $schoolId,

                                'pickup_person_id' =>
                                    $pickupPerson->id,

                                'face_verification_attempt_id' =>
                                    $attempt->id,

                                'confirmed_by_user_id' =>
                                    $user->id,

                                'cancelled_by_user_id' =>
                                    null,

                                'idempotency_key' =>
                                    $idempotencyKey,

                                'verification_method' =>
                                    PickupEvent::VERIFICATION_METHOD_FACE,

                                'status' =>
                                    PickupEvent::STATUS_CONFIRMED,

                                'pickup_person_name' =>
                                    $pickupPerson->full_name,

                                'pickup_person_phone' =>
                                    $this->nullableString(
                                        $pickupPerson->phone,
                                    ),

                                'verification_result' =>
                                    $attempt->result,

                                'similarity_score' =>
                                    $attempt->similarity_score,

                                'similarity_threshold' =>
                                    $attempt->similarity_threshold,

                                'candidate_margin' =>
                                    $attempt->candidate_margin,

                                'confirmed_at' =>
                                    $confirmedAt,

                                'cancelled_at' =>
                                    null,

                                'cancellation_reason' =>
                                    null,

                                'notes' =>
                                    $notes,

                                'ip_address' =>
                                    $this->storeIpAddress()
                                        ? $request->ip()
                                        : null,

                                'user_agent' =>
                                    $this->storeUserAgent()
                                        ? $this->nullableString(
                                            $request->userAgent(),
                                        )
                                        : null,

                                'metadata' => [
                                    'request_fingerprint' =>
                                        $requestFingerprint,

                                    'session_binding' =>
                                        $sessionBinding,

                                    'selected_student_count' =>
                                        count($studentIds),

                                    'source' =>
                                        'gate_face_verification',

                                    'verification_attempt_occurred_at' =>
                                        $attempt->occurred_at
                                            ? (string) $attempt
                                                ->occurred_at
                                            : null,
                                ],
                            ]);

                        $studentRows =
                            $students
                                ->map(
                                    function (
                                        Student $student,
                                    ) use (
                                        $confirmedAt,
                                    ): array {
                                        return [
                                            'student_id' =>
                                                $student->id,

                                            'student_name' =>
                                                $student->full_name,

                                            'student_number' =>
                                                $this->nullableString(
                                                    $student
                                                        ->student_number,
                                                ),

                                            'class_name' =>
                                                $this->studentClassName(
                                                    $student,
                                                ),

                                            'academic_year' =>
                                                $this->studentAcademicYear(
                                                    $student,
                                                ),

                                            'relationship_type' =>
                                                $this->nullableString(
                                                    $student->getAttribute(
                                                        'authorized_relationship_type',
                                                    ),
                                                ),

                                            'is_primary' =>
                                                (bool) $student
                                                    ->getAttribute(
                                                        'authorized_is_primary',
                                                    ),

                                            'status' =>
                                                PickupEventStudent::STATUS_RELEASED,

                                            'released_at' =>
                                                $confirmedAt,

                                            'cancelled_at' =>
                                                null,

                                            'cancelled_by_user_id' =>
                                                null,

                                            'cancellation_reason' =>
                                                null,
                                        ];
                                    },
                                )
                                ->all();

                        $event
                            ->eventStudents()
                            ->createMany(
                                $studentRows,
                            );

                        return [
                            'event' =>
                                $event,

                            'replayed' =>
                                false,
                        ];
                    },
                    3,
                );
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            $existingByIdempotency =
                PickupEvent::query()
                    ->where(
                        'idempotency_key',
                        $idempotencyKey,
                    )
                    ->first();

            if ($existingByIdempotency) {
                $this->ensureReplayMatchesRequest(
                    event:
                        $existingByIdempotency,
                    schoolId:
                        $schoolId,
                    userId:
                        (int) $user->id,
                    attemptId:
                        $attemptId,
                    requestFingerprint:
                        $requestFingerprint,
                    sessionBinding:
                        $sessionBinding,
                );

                $transactionResult = [
                    'event' =>
                        $existingByIdempotency,

                    'replayed' =>
                        true,
                ];
            } else {
                $existingByAttempt =
                    PickupEvent::query()
                        ->where(
                            'face_verification_attempt_id',
                            $attemptId,
                        )
                        ->first();

                if ($existingByAttempt) {
                    throw new ConflictHttpException(
                        'Hasil verifikasi wajah ini sudah digunakan untuk transaksi penjemputan.',
                        $exception,
                    );
                }

                throw new ConflictHttpException(
                    'Transaksi penjemputan sudah diproses oleh request lain.',
                    $exception,
                );
            }
        }

        /** @var PickupEvent $event */
        $event =
            $transactionResult['event'];

        $replayed =
            (bool) $transactionResult[
                'replayed'
            ];

        return response()->json(
            [
                'message' =>
                    $replayed
                        ? 'Konfirmasi yang sama sudah pernah diproses.'
                        : 'Penjemputan berhasil dikonfirmasi.',

                'replayed' =>
                    $replayed,

                'pickup_event' =>
                    $this->eventDetailPayload(
                        $event,
                        $user,
                    ),
            ],
            $replayed
                ? 200
                : 201,
        );
    }

    public function cancel(
        CancelPickupEventRequest $request,
        int $pickupEvent,
    ): JsonResponse {
        $user = $this->authenticatedUser(
            $request,
        );

        $this->authorizeGateAccess($user);

        $schoolId = $this->schoolId($user);

        $reason =
            $request->cancellationReason();

        $event = DB::transaction(
            function () use (
                $schoolId,
                $pickupEvent,
                $user,
                $reason,
            ): PickupEvent {
                $event = PickupEvent::query()
                    ->whereKey($pickupEvent)
                    ->where(
                        'school_id',
                        $schoolId,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $event) {
                    abort(
                        404,
                        'Transaksi penjemputan tidak ditemukan.',
                    );
                }

                if (! $event->canBeCancelled()) {
                    throw new ConflictHttpException(
                        'Transaksi penjemputan sudah dibatalkan atau tidak dapat dibatalkan.',
                    );
                }

                $this->authorizeCancellation(
                    $event,
                    $user,
                );

                $studentRows =
                    PickupEventStudent::query()
                        ->where(
                            'pickup_event_id',
                            $event->id,
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                $cancelledAt = now();

                foreach ($studentRows as $studentRow) {
                    if (
                        ! $studentRow
                            ->canBeCancelled()
                    ) {
                        continue;
                    }

                    $studentRow->update([
                        'status' =>
                            PickupEventStudent::STATUS_CANCELLED,

                        'cancelled_at' =>
                            $cancelledAt,

                        'cancelled_by_user_id' =>
                            $user->id,

                        'cancellation_reason' =>
                            $reason,
                    ]);
                }

                $event->update([
                    'status' =>
                        PickupEvent::STATUS_CANCELLED,

                    'cancelled_at' =>
                        $cancelledAt,

                    'cancelled_by_user_id' =>
                        $user->id,

                    'cancellation_reason' =>
                        $reason,
                ]);

                return $event;
            },
            3,
        );

        return response()->json([
            'message' =>
                'Transaksi penjemputan berhasil dibatalkan.',

            'pickup_event' =>
                $this->eventDetailPayload(
                    $event,
                    $user,
                ),
        ]);
    }

    public function cancelStudent(
        CancelPickupEventStudentRequest $request,
        int $pickupEvent,
        int $pickupEventStudent,
    ): JsonResponse {
        $user = $this->authenticatedUser(
            $request,
        );

        $this->authorizeGateAccess($user);

        $schoolId = $this->schoolId($user);

        $reason =
            $request->cancellationReason();

        $event = DB::transaction(
            function () use (
                $schoolId,
                $pickupEvent,
                $pickupEventStudent,
                $user,
                $reason,
            ): PickupEvent {
                /*
                 * Parent selalu dikunci lebih dahulu agar urutan
                 * lock konsisten dengan pembatalan seluruh event.
                 */
                $event = PickupEvent::query()
                    ->whereKey($pickupEvent)
                    ->where(
                        'school_id',
                        $schoolId,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $event) {
                    abort(
                        404,
                        'Transaksi penjemputan tidak ditemukan.',
                    );
                }

                if (! $event->canBeCancelled()) {
                    throw new ConflictHttpException(
                        'Transaksi penjemputan sudah dibatalkan atau tidak dapat diubah.',
                    );
                }

                $this->authorizeCancellation(
                    $event,
                    $user,
                );

                $studentRow =
                    PickupEventStudent::query()
                        ->whereKey(
                            $pickupEventStudent,
                        )
                        ->where(
                            'pickup_event_id',
                            $event->id,
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $studentRow) {
                    abort(
                        404,
                        'Data siswa pada transaksi tidak ditemukan.',
                    );
                }

                if (
                    ! $studentRow
                        ->canBeCancelled()
                ) {
                    throw new ConflictHttpException(
                        'Penyerahan siswa ini sudah dibatalkan.',
                    );
                }

                $cancelledAt = now();

                $studentRow->update([
                    'status' =>
                        PickupEventStudent::STATUS_CANCELLED,

                    'cancelled_at' =>
                        $cancelledAt,

                    'cancelled_by_user_id' =>
                        $user->id,

                    'cancellation_reason' =>
                        $reason,
                ]);

                $remainingReleasedStudent =
                    PickupEventStudent::query()
                        ->where(
                            'pickup_event_id',
                            $event->id,
                        )
                        ->where(
                            'status',
                            PickupEventStudent::STATUS_RELEASED,
                        )
                        ->exists();

                if (! $remainingReleasedStudent) {
                    $event->update([
                        'status' =>
                            PickupEvent::STATUS_CANCELLED,

                        'cancelled_at' =>
                            $cancelledAt,

                        'cancelled_by_user_id' =>
                            $user->id,

                        'cancellation_reason' =>
                            $reason,
                    ]);
                }

                return $event;
            },
            3,
        );

        return response()->json([
            'message' =>
                'Penyerahan siswa berhasil dibatalkan.',

            'pickup_event' =>
                $this->eventDetailPayload(
                    $event,
                    $user,
                ),
        ]);
    }

    private function validateVerificationAttempt(
        PickupPersonFaceVerificationAttempt $attempt,
        User $user,
        Request $request,
        int $schoolId,
    ): void {
        if (
            $attempt->result
                !== PickupPersonFaceVerificationAttempt::RESULT_MATCH
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Hanya hasil verifikasi wajah yang cocok yang dapat digunakan.',
            ]);
        }

        if (
            ! (bool) $attempt
                ->liveness_passed
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Verifikasi liveness pada hasil ini tidak lulus.',
            ]);
        }

        if (
            $attempt->pickup_person_id
                === null
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Hasil verifikasi tidak memiliki penjemput yang cocok.',
            ]);
        }

        if (
            (int) $attempt
                ->verified_by_user_id
                !== (int) $user->id
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Hasil verifikasi ini dibuat oleh petugas lain.',
            ]);
        }

        if (
            (int) $attempt->school_id
                !== $schoolId
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Hasil verifikasi berasal dari sekolah yang berbeda.',
            ]);
        }

        $windowSeconds = max(
            30,
            min(
                1800,
                (int) config(
                    'biometrics.security.pickup_confirmation_window_seconds',
                    300,
                ),
            ),
        );

        try {
            $occurredAt =
                $attempt->occurred_at
                    ? CarbonImmutable::parse(
                        (string) $attempt
                            ->occurred_at,
                    )
                    : null;
        } catch (Throwable) {
            $occurredAt = null;
        }

        if (
            ! $occurredAt
                instanceof CarbonImmutable
            || $occurredAt->lessThan(
                now()->subSeconds(
                    $windowSeconds,
                ),
            )
            || $occurredAt->greaterThan(
                now()->addSeconds(30),
            )
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    sprintf(
                        'Hasil verifikasi sudah kedaluwarsa atau waktunya tidak valid. Batas konfirmasi adalah %d detik.',
                        $windowSeconds,
                    ),
            ]);
        }

        if (
            ! (bool) config(
                'biometrics.security.bind_pickup_confirmation_to_session',
                true,
            )
        ) {
            return;
        }

        $storedBinding = data_get(
            $attempt->metadata,
            'security.session_binding',
        );

        $currentBinding =
            $this->sessionBindingHash(
                $request,
                $schoolId,
                $user,
            );

        if (
            ! is_string($storedBinding)
            || trim($storedBinding) === ''
            || ! hash_equals(
                $storedBinding,
                $currentBinding,
            )
        ) {
            throw ValidationException::withMessages([
                'face_verification_attempt_id' =>
                    'Hasil verifikasi berasal dari sesi yang berbeda. Jalankan verifikasi wajah ulang.',
            ]);
        }
    }

    /**
     * @param array<int> $studentIds
     *
     * @return Collection<int, Student>
     */
    private function authorizedStudents(
        int $schoolId,
        int $pickupPersonId,
        array $studentIds,
        string $today,
    ): Collection {
        return Student::query()
            ->select([
                'students.*',

                'pickup_person_student.relationship_type as authorized_relationship_type',

                'pickup_person_student.is_primary as authorized_is_primary',
            ])
            ->join(
                'pickup_person_student',
                'pickup_person_student.student_id',
                '=',
                'students.id',
            )
            ->with('schoolClass')
            ->where(
                'students.school_id',
                $schoolId,
            )
            ->where(
                'students.status',
                Student::STATUS_ACTIVE,
            )
            ->where(
                'pickup_person_student.school_id',
                $schoolId,
            )
            ->where(
                'pickup_person_student.pickup_person_id',
                $pickupPersonId,
            )
            ->where(
                'pickup_person_student.is_active',
                true,
            )
            ->whereIn(
                'students.id',
                $studentIds,
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
            ->orderBy('students.id')
            ->lockForUpdate()
            ->get();
    }

    private function ensureReplayMatchesRequest(
        PickupEvent $event,
        int $schoolId,
        int $userId,
        int $attemptId,
        string $requestFingerprint,
        string $sessionBinding,
    ): void {
        $storedFingerprint = data_get(
            $event->metadata,
            'request_fingerprint',
        );

        $storedSessionBinding = data_get(
            $event->metadata,
            'session_binding',
        );

        if (
            (int) $event->school_id
                !== $schoolId
            || (int) $event
                ->confirmed_by_user_id
                !== $userId
            || (int) $event
                ->face_verification_attempt_id
                !== $attemptId
            || ! is_string(
                $storedFingerprint,
            )
            || ! hash_equals(
                $requestFingerprint,
                $storedFingerprint,
            )
            || ! is_string(
                $storedSessionBinding,
            )
            || ! hash_equals(
                $sessionBinding,
                $storedSessionBinding,
            )
        ) {
            throw new ConflictHttpException(
                'Kunci idempotency sudah digunakan untuk request yang berbeda.',
            );
        }
    }

    /**
     * @param array<int> $studentIds
     */
    private function requestFingerprint(
        int $schoolId,
        int $userId,
        int $attemptId,
        array $studentIds,
        ?string $notes,
        string $sessionBinding,
    ): string {
        return hash(
            'sha256',
            json_encode(
                [
                    'school_id' =>
                        $schoolId,

                    'user_id' =>
                        $userId,

                    'attempt_id' =>
                        $attemptId,

                    'student_ids' =>
                        array_values($studentIds),

                    'notes_hash' =>
                        hash(
                            'sha256',
                            $notes ?? '',
                        ),

                    'session_binding' =>
                        $sessionBinding,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
    }

    private function applyHistoryFilters(
        Builder $query,
        GatePickupEventHistoryRequest $request,
        ?CarbonImmutable $confirmedFrom,
        ?CarbonImmutable $confirmedTo,
    ): Builder {
        if ($confirmedFrom !== null) {
            $query->where(
                'confirmed_at',
                '>=',
                $confirmedFrom,
            );
        }

        if ($confirmedTo !== null) {
            $query->where(
                'confirmed_at',
                '<=',
                $confirmedTo,
            );
        }

        if ($request->status() !== null) {
            $query->where(
                'status',
                $request->status(),
            );
        }

        if (
            $request->verificationMethod()
                !== null
        ) {
            $query->where(
                'verification_method',
                $request
                    ->verificationMethod(),
            );
        }

        if (
            $request->confirmedByUserId()
                !== null
        ) {
            $query->where(
                'confirmed_by_user_id',
                $request
                    ->confirmedByUserId(),
            );
        }

        $query->search(
            $request->searchTerm(),
        );

        return $query;
    }

    private function historyItemPayload(
        PickupEvent $event,
        User $viewer,
    ): array {
        return [
            'id' =>
                (int) $event->id,

            'status' =>
                (string) $event->status,

            'status_label' =>
                $event->statusLabel(),

            'verification_method' =>
                (string) $event
                    ->verification_method,

            'verification_method_label' =>
                $event
                    ->verificationMethodLabel(),

            'pickup_person_name' =>
                (string) $event
                    ->pickup_person_name,

            'pickup_person_phone' =>
                $event->pickup_person_phone,

            'confirmed_at' =>
                $event->confirmed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $event->cancelled_at
                    ?->toIso8601String(),

            'confirmed_by' =>
                $event->confirmedBy
                    ? [
                        'id' =>
                            (int) $event
                                ->confirmedBy
                                ->id,

                        'name' =>
                            (string) $event
                                ->confirmedBy
                                ->name,
                    ]
                    : null,

            'cancelled_by' =>
                $event->cancelledBy
                    ? [
                        'id' =>
                            (int) $event
                                ->cancelledBy
                                ->id,

                        'name' =>
                            (string) $event
                                ->cancelledBy
                                ->name,
                    ]
                    : null,

            'student_count' =>
                (int) $event
                    ->event_students_count,

            'released_student_count' =>
                (int) $event
                    ->released_event_students_count,

            'cancelled_student_count' =>
                (int) $event
                    ->cancelled_event_students_count,

            'can_cancel' =>
                $this->userCanCancelEvent(
                    $event,
                    $viewer,
                ),

            'url' => sprintf(
                '/gate/pickup-events/%d',
                $event->id,
            ),
        ];
    }

    private function eventDetailPayload(
        PickupEvent $event,
        User $viewer,
    ): array {
        $event->load([
            'confirmedBy:id,name',
            'cancelledBy:id,name',

            'faceVerificationAttempt' => static function (
                BelongsTo $query,
            ): void {
                $query->select([
                    'id',
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
                    'occurred_at',
                ]);
            },

            'eventStudents' => static function (
                HasMany $query,
            ): void {
                $query
                    ->with('cancelledBy:id,name')
                    ->orderBy('student_name')
                    ->orderBy('id');
            },
        ]);

        $canCancelEvent =
            $this->userCanCancelEvent(
                $event,
                $viewer,
            );

        return [
            'id' =>
                (int) $event->id,

            'idempotency_key' =>
                (string) $event
                    ->idempotency_key,

            'status' =>
                (string) $event->status,

            'status_label' =>
                $event->statusLabel(),

            'verification_method' =>
                (string) $event
                    ->verification_method,

            'verification_method_label' =>
                $event
                    ->verificationMethodLabel(),

            'verification_result' =>
                (string) $event
                    ->verification_result,

            'similarity_score' =>
                $event->similarity_score,

            'similarity_threshold' =>
                $event
                    ->similarity_threshold,

            'candidate_margin' =>
                $event->candidate_margin,

            'confirmed_at' =>
                $event->confirmed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $event->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $event
                    ->cancellation_reason,

            'notes' =>
                $event->notes,

            'can_cancel' =>
                $canCancelEvent,

            'pickup_person' => [
                'id' =>
                    $event->pickup_person_id
                        !== null
                            ? (int) $event
                                ->pickup_person_id
                            : null,

                'full_name' =>
                    (string) $event
                        ->pickup_person_name,

                'phone' =>
                    $event
                        ->pickup_person_phone,
            ],

            'confirmed_by' =>
                $event->confirmedBy
                    ? [
                        'id' =>
                            (int) $event
                                ->confirmedBy
                                ->id,

                        'name' =>
                            (string) $event
                                ->confirmedBy
                                ->name,
                    ]
                    : null,

            'cancelled_by' =>
                $event->cancelledBy
                    ? [
                        'id' =>
                            (int) $event
                                ->cancelledBy
                                ->id,

                        'name' =>
                            (string) $event
                                ->cancelledBy
                                ->name,
                    ]
                    : null,

            'verification_attempt' =>
                $event
                    ->faceVerificationAttempt
                    ? [
                        'id' =>
                            (int) $event
                                ->faceVerificationAttempt
                                ->id,

                        'result' =>
                            $event
                                ->faceVerificationAttempt
                                ->result,

                        'similarity_score' =>
                            $event
                                ->faceVerificationAttempt
                                ->similarity_score,

                        'similarity_threshold' =>
                            $event
                                ->faceVerificationAttempt
                                ->similarity_threshold,

                        'candidate_margin' =>
                            $event
                                ->faceVerificationAttempt
                                ->candidate_margin,

                        'quality_score' =>
                            $event
                                ->faceVerificationAttempt
                                ->quality_score,

                        'liveness_passed' =>
                            (bool) $event
                                ->faceVerificationAttempt
                                ->liveness_passed,

                        'live_score' =>
                            $event
                                ->faceVerificationAttempt
                                ->live_score,

                        'real_score' =>
                            $event
                                ->faceVerificationAttempt
                                ->real_score,

                        'model_name' =>
                            (string) $event
                                ->faceVerificationAttempt
                                ->model_name,

                        'model_version' =>
                            $event
                                ->faceVerificationAttempt
                                ->model_version,

                        'occurred_at' =>
                            $event
                                ->faceVerificationAttempt
                                ->occurred_at
                                ?->toIso8601String(),
                    ]
                    : null,

            'students' =>
                $event
                    ->eventStudents
                    ->map(
                        static fn (
                            PickupEventStudent $item,
                        ): array => [
                            'id' =>
                                (int) $item->id,

                            'student_id' =>
                                $item->student_id
                                    !== null
                                        ? (int) $item
                                            ->student_id
                                        : null,

                            'student_name' =>
                                (string) $item
                                    ->student_name,

                            'student_number' =>
                                $item
                                    ->student_number,

                            'class_name' =>
                                $item->class_name,

                            'academic_year' =>
                                $item
                                    ->academic_year,

                            'relationship_type' =>
                                $item
                                    ->relationship_type,

                            'is_primary' =>
                                (bool) $item
                                    ->is_primary,

                            'status' =>
                                (string) $item
                                    ->status,

                            'status_label' =>
                                $item->statusLabel(),

                            'released_at' =>
                                $item->released_at
                                    ?->toIso8601String(),

                            'cancelled_at' =>
                                $item->cancelled_at
                                    ?->toIso8601String(),

                            'cancellation_reason' =>
                                $item
                                    ->cancellation_reason,

                            'cancelled_by' =>
                                $item->cancelledBy
                                    ? [
                                        'id' =>
                                            (int) $item
                                                ->cancelledBy
                                                ->id,

                                        'name' =>
                                            (string) $item
                                                ->cancelledBy
                                                ->name,
                                    ]
                                    : null,

                            'can_cancel' =>
                                $canCancelEvent
                                && $item
                                    ->canBeCancelled(),
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function findEventForSchool(
        int $schoolId,
        int $pickupEventId,
    ): PickupEvent {
        $event = PickupEvent::query()
            ->whereKey($pickupEventId)
            ->where(
                'school_id',
                $schoolId,
            )
            ->first();

        if (! $event) {
            abort(
                404,
                'Transaksi penjemputan tidak ditemukan.',
            );
        }

        return $event;
    }

    /**
     * @return array{
     *     0: CarbonImmutable|null,
     *     1: CarbonImmutable|null
     * }
     */
    private function confirmedAtBounds(
        int $schoolId,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $schoolTimezone =
            $this->schoolTimezone(
                $schoolId,
            );

        $applicationTimezone =
            (string) config(
                'app.timezone',
                'UTC',
            );

        try {
            $from = $dateFrom !== null
                ? CarbonImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    "{$dateFrom} 00:00:00",
                    $schoolTimezone,
                )?->setTimezone(
                    $applicationTimezone,
                )
                : null;

            $to = $dateTo !== null
                ? CarbonImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    "{$dateTo} 23:59:59",
                    $schoolTimezone,
                )?->setTimezone(
                    $applicationTimezone,
                )
                : null;
        } catch (Throwable) {
            $from = null;
            $to = null;
        }

        return [
            $from,
            $to,
        ];
    }

    private function schoolToday(
        int $schoolId,
    ): string {
        return now(
            $this->schoolTimezone(
                $schoolId,
            ),
        )->toDateString();
    }

    private function schoolTimezone(
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
            return (string) config(
                'app.timezone',
                'UTC',
            );
        }

        try {
            new \DateTimeZone(
                trim($timezone),
            );

            return trim($timezone);
        } catch (Throwable) {
            return (string) config(
                'app.timezone',
                'UTC',
            );
        }
    }

    private function studentClassName(
        Student $student,
    ): ?string {
        $schoolClass =
            $student->schoolClass;

        if (! $schoolClass) {
            return null;
        }

        return $this->firstNonEmptyString([
            $schoolClass->getAttribute(
                'name',
            ),

            $schoolClass->getAttribute(
                'class_name',
            ),

            $schoolClass->getAttribute(
                'label',
            ),
        ]);
    }

    private function studentAcademicYear(
        Student $student,
    ): ?string {
        $schoolClass =
            $student->schoolClass;

        if (! $schoolClass) {
            return null;
        }

        return $this->firstNonEmptyString([
            $schoolClass->getAttribute(
                'academic_year',
            ),

            $schoolClass->getAttribute(
                'school_year',
            ),

            $schoolClass->getAttribute(
                'year',
            ),
        ]);
    }

    /**
     * @param array<mixed> $values
     */
    private function firstNonEmptyString(
        array $values,
    ): ?string {
        foreach ($values as $value) {
            $normalized =
                $this->nullableString(
                    $value,
                );

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function authorizeCancellation(
        PickupEvent $event,
        User $user,
    ): void {
        if (
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            )
        ) {
            return;
        }

        abort_unless(
            $user->hasRole(
                User::ROLE_GATE_OFFICER,
            ),
            403,
            'Akun tidak memiliki izin membatalkan transaksi.',
        );

        abort_unless(
            (int) $event
                ->confirmed_by_user_id
                === (int) $user->id,
            403,
            'Petugas gerbang hanya dapat membatalkan transaksi yang dikonfirmasi sendiri.',
        );

        $windowSeconds =
            $this->gateCancellationWindowSeconds();

        try {
            $confirmedAt =
                $event->confirmed_at
                    ? CarbonImmutable::parse(
                        (string) $event
                            ->confirmed_at,
                    )
                    : null;
        } catch (Throwable) {
            $confirmedAt = null;
        }

        abort_unless(
            $confirmedAt
                instanceof CarbonImmutable,
            409,
            'Waktu transaksi tidak valid sehingga pembatalan tidak dapat diproses.',
        );

        abort_if(
            $confirmedAt->lessThan(
                now()->subSeconds(
                    $windowSeconds,
                ),
            ),
            403,
            sprintf(
                'Batas pembatalan oleh petugas gerbang adalah %d menit. Hubungi administrator sekolah.',
                (int) ceil(
                    $windowSeconds / 60,
                ),
            ),
        );
    }

    private function userCanCancelEvent(
        PickupEvent $event,
        User $user,
    ): bool {
        if (! $event->canBeCancelled()) {
            return false;
        }

        if (
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            )
        ) {
            return true;
        }

        if (
            ! $user->hasRole(
                User::ROLE_GATE_OFFICER,
            )
            || (int) $event
                ->confirmed_by_user_id
                !== (int) $user->id
        ) {
            return false;
        }

        try {
            $confirmedAt =
                $event->confirmed_at
                    ? CarbonImmutable::parse(
                        (string) $event
                            ->confirmed_at,
                    )
                    : null;
        } catch (Throwable) {
            return false;
        }

        return (
            $confirmedAt
                instanceof CarbonImmutable
            && ! $confirmedAt->lessThan(
                now()->subSeconds(
                    $this
                        ->gateCancellationWindowSeconds(),
                ),
            )
        );
    }

    private function gateCancellationWindowSeconds(): int
    {
        return max(
            60,
            min(
                86400,
                (int) config(
                    'biometrics.security.gate_cancellation_window_seconds',
                    900,
                ),
            ),
        );
    }

    private function sessionBindingHash(
    Request $request,
    int $schoolId,
    User $user,
): string {
    $applicationKey =
        trim(
            (string) config(
                'app.key',
                '',
            ),
        );

    $hashKey =
        $applicationKey !== ''
            ? $applicationKey
            : 'schoolsafe-session-binding';

    $bindingEnabled =
        (bool) config(
            'biometrics.security.bind_pickup_confirmation_to_session',
            true,
        );

    /*
     * Ketika session binding dimatikan, gunakan komponen
     * deterministik. Request identik tetap menghasilkan fingerprint
     * yang sama walaupun Laravel membuat ID session baru pada
     * request Feature Test berikutnya.
     */
    $sessionComponent =
        $bindingEnabled
            ? trim(
                $request
                    ->session()
                    ->getId(),
            )
            : 'session-binding-disabled';

    return hash_hmac(
        'sha256',
        implode(
            '|',
            [
                $sessionComponent,
                (string) $schoolId,
                (string) $user->id,
            ],
        ),
        $hashKey,
    );
}

    private function storeIpAddress(): bool
    {
        return (bool) config(
            'biometrics.audit.store_ip_address',
            config(
                'biometrics.audit.store_ip',
                true,
            ),
        );
    }

    private function storeUserAgent(): bool
    {
        return (bool) config(
            'biometrics.audit.store_user_agent',
            true,
        );
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

    private function authorizeGateAccess(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_GATE_OFFICER,
            ),
            403,
            'Akun tidak memiliki izin mengelola transaksi gerbang.',
        );
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
}