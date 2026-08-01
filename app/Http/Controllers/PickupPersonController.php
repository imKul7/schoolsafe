<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegisterPickupPersonFaceRequest;
use App\Http\Requests\StorePickupPersonRequest;
use App\Http\Requests\UpdatePickupPersonRequest;
use App\Http\Requests\UploadPickupPersonPhotoRequest;
use App\Models\PickupPerson;
use App\Models\PickupPersonFaceProfile;
use App\Models\Student;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PickupPersonController extends Controller
{
    /**
     * Menampilkan daftar penjemput milik sekolah pengguna.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        $this->authorizeView($user);

        $schoolId = $this->schoolId($user);

        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'face_status' => [
                'nullable',
                Rule::in([
                    PickupPerson::FACE_NOT_REGISTERED,
                    PickupPerson::FACE_REGISTERED,
                    PickupPerson::FACE_NEEDS_UPDATE,
                ]),
            ],
        ]);

        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        $status = (string) (
            $filters['status'] ?? ''
        );

        $faceStatus = (string) (
            $filters['face_status'] ?? ''
        );

        $pickupPersons = PickupPerson::query()
            ->where('school_id', $schoolId)
            ->with([
                'students' => function ($query): void {
                    $query
                        ->where(
                            'pickup_person_student.is_active',
                            true,
                        )
                        ->select([
                            'students.id',
                            'students.full_name',
                            'students.student_number',
                        ])
                        ->orderBy(
                            'students.full_name',
                        );
                },
            ])
            ->when(
                $search !== '',
                function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        function (
                            Builder $query,
                        ) use ($search): void {
                            $query
                                ->where(
                                    'full_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'identity_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $status === 'active',
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    true,
                ),
            )
            ->when(
                $status === 'inactive',
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    false,
                ),
            )
            ->when(
                $faceStatus !== '',
                fn (Builder $query): Builder => $query->where(
                    'face_status',
                    $faceStatus,
                ),
            )
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString()
            ->through(
                fn (
                    PickupPerson $pickupPerson,
                ): array => $this->pickupPersonListItem(
                    $pickupPerson,
                ),
            );

        $baseQuery = PickupPerson::query()
            ->where('school_id', $schoolId);

        return Inertia::render(
            'pickup-persons/index',
            [
                'pickupPersons' => $pickupPersons,

                'stats' => [
                    'total' => (clone $baseQuery)->count(),

                    'active' => (clone $baseQuery)
                        ->where(
                            'is_active',
                            true,
                        )
                        ->count(),

                    'face_registered' => (clone $baseQuery)
                        ->where(
                            'face_status',
                            PickupPerson::FACE_REGISTERED,
                        )
                        ->count(),
                ],

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'face_status' => $faceStatus,
                ],

                'permissions' => $this->permissions($user),
            ],
        );
    }

    /**
     * Menampilkan daftar penjemput yang telah diarsipkan.
     */
    public function archive(
        Request $request,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->authorizeArchive($user);

        $schoolId = $this->schoolId($user);

        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        $pickupPersons = PickupPerson::query()
            ->onlyTrashed()
            ->where('school_id', $schoolId)
            ->when(
                $search !== '',
                function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        function (
                            Builder $query,
                        ) use ($search): void {
                            $query
                                ->where(
                                    'full_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'identity_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(
                fn (
                    PickupPerson $pickupPerson,
                ): array => $this->archivedPickupPersonListItem(
                    $pickupPerson,
                ),
            );

        return Inertia::render(
            'pickup-persons/archive',
            [
                'pickupPersons' => $pickupPersons,

                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    /**
     * Menampilkan halaman tambah penjemput.
     */
    public function create(
        Request $request,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        return Inertia::render(
            'pickup-persons/create',
            [
                'students' => $this->studentOptions(
                    $schoolId,
                ),
            ],
        );
    }

    /**
     * Menyimpan data penjemput baru.
     */
    public function store(
        StorePickupPersonRequest $request,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        $pickupPerson = DB::transaction(
            function () use (
                $request,
                $schoolId,
            ): PickupPerson {
                $validated = $request->validated();

                /** @var array<int, array<string, mixed>> $studentLinks */
                $studentLinks = $validated['students'] ?? [];

                /*
                 * Field berikut dikelola sistem dan tidak boleh
                 * ditentukan langsung dari form.
                 */
                unset(
                    $validated['students'],
                    $validated['school_id'],
                    $validated['photo_path'],
                    $validated['face_status'],
                );

                $this->assertStudentLinksBelongToSchool(
                    $studentLinks,
                    $schoolId,
                );

                $pickupPerson = PickupPerson::create([
                    ...$validated,
                    'school_id' => $schoolId,
                    'photo_path' => null,
                    'face_status' => PickupPerson::FACE_NOT_REGISTERED,
                ]);

                $this->syncStudents(
                    $pickupPerson,
                    $studentLinks,
                    $schoolId,
                );

                return $pickupPerson;
            },
            3,
        );

        return redirect()
            ->route(
                'pickup-persons.show',
                $pickupPerson,
            )
            ->with(
                'success',
                'Data penjemput berhasil ditambahkan.',
            );
    }

    /**
     * Menampilkan detail penjemput.
     */
    public function show(
        Request $request,
        PickupPerson $pickupPerson,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeView($user);

        $pickupPerson->load([
            'faceProfile',

            'students' => function ($query): void {
                $query
                    ->with([
                        'schoolClass:id,name,grade_level,academic_year',
                    ])
                    ->orderBy(
                        'students.full_name',
                    );
            },
        ]);

        return Inertia::render(
            'pickup-persons/show',
            [
                'pickupPerson' => $this->pickupPersonDetail(
                    $pickupPerson,
                ),

                'permissions' => $this->permissions($user),

                'biometricConfig' => [
                    'minimum_quality_score' => (float) config(
                        'biometrics.minimum_quality_score',
                        0.75,
                    ),

                    'consent_version' => (string) config(
                        'biometrics.consent_version',
                        'v1',
                    ),
                ],
            ],
        );
    }

    /**
     * Menampilkan halaman edit penjemput.
     */
    public function edit(
        Request $request,
        PickupPerson $pickupPerson,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        $pickupPerson->load([
            'students' => function ($query): void {
                $query
                    ->select([
                        'students.id',
                        'students.full_name',
                        'students.student_number',
                    ])
                    ->orderBy(
                        'students.full_name',
                    );
            },
        ]);

        $selectedStudentIds = $pickupPerson
            ->students
            ->pluck('id')
            ->map(
                fn ($id): int => (int) $id,
            )
            ->all();

        return Inertia::render(
            'pickup-persons/edit',
            [
                'pickupPerson' => [
                    'id' => $pickupPerson->id,

                    'photo_path' => $pickupPerson->photo_path,

                    'photo_url' => $this->pickupPersonPhotoUrl(
                        $pickupPerson->photo_path,
                    ),

                    'full_name' => $pickupPerson->full_name,

                    'identity_number' => $pickupPerson->identity_number
                        ?? '',

                    'phone' => $pickupPerson->phone,

                    'email' => $pickupPerson->email
                        ?? '',

                    'address' => $pickupPerson->address
                        ?? '',

                    'face_status' => $pickupPerson->face_status,

                    'is_active' => (bool) $pickupPerson->is_active,

                    'notes' => $pickupPerson->notes
                        ?? '',

                    'students' => $pickupPerson
                        ->students
                        ->map(
                            fn (
                                Student $student,
                            ): array => [
                                'student_id' => (string) $student->id,

                                'relationship_type' => (string) $student->pivot
                                    ->relationship_type,

                                'is_primary' => (bool) $student->pivot
                                    ->is_primary,

                                'is_active' => (bool) $student->pivot
                                    ->is_active,

                                'valid_from' => $this->formatDateValue(
                                    $student->pivot
                                        ->valid_from,
                                ) ?? '',

                                'valid_until' => $this->formatDateValue(
                                    $student->pivot
                                        ->valid_until,
                                ) ?? '',
                            ],
                        )
                        ->values()
                        ->all(),
                ],

                'students' => $this->studentOptions(
                    $schoolId,
                    $selectedStudentIds,
                ),
            ],
        );
    }

    /**
     * Memperbarui data penjemput.
     */
    public function update(
        UpdatePickupPersonRequest $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        DB::transaction(
            function () use (
                $request,
                $pickupPerson,
                $schoolId,
            ): void {
                $validated = $request->validated();

                /** @var array<int, array<string, mixed>> $studentLinks */
                $studentLinks = $validated['students'] ?? [];

                /*
                 * school_id, foto, dan status biometrik hanya boleh
                 * diubah oleh alur backend khusus.
                 */
                unset(
                    $validated['students'],
                    $validated['school_id'],
                    $validated['photo_path'],
                    $validated['face_status'],
                );

                $this->assertStudentLinksBelongToSchool(
                    $studentLinks,
                    $schoolId,
                );

                $lockedPickupPerson = PickupPerson::query()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPerson->id);

                $lockedPickupPerson->update(
                    $validated,
                );

                $this->syncStudents(
                    $lockedPickupPerson,
                    $studentLinks,
                    $schoolId,
                );
            },
            3,
        );

        return redirect()
            ->route(
                'pickup-persons.show',
                $pickupPerson,
            )
            ->with(
                'success',
                'Data penjemput berhasil diperbarui.',
            );
    }

    /**
     * Mengaktifkan atau menonaktifkan penjemput.
     */
    public function toggleStatus(
        Request $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeManage($user);

        $newStatus = ! (bool) $pickupPerson->is_active;

        $pickupPerson->update([
            'is_active' => $newStatus,
        ]);

        $message = $newStatus
            ? 'Penjemput berhasil diaktifkan.'
            : 'Penjemput berhasil dinonaktifkan.';

        return back()->with(
            'success',
            $message,
        );
    }

    /**
     * Mengunggah atau mengganti foto penjemput.
     */
    public function uploadPhoto(
        UploadPickupPersonPhotoRequest $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        $photo = $request->file('photo');

        abort_unless(
            $photo instanceof UploadedFile,
            422,
            'Foto penjemput tidak ditemukan.',
        );

        $directory = $this->pickupPersonPhotoDirectory(
            $schoolId,
            (int) $pickupPerson->id,
        );

        $newPhotoPath = $photo->store(
            $directory,
            'public',
        );

        if (
            ! is_string($newPhotoPath)
            || trim($newPhotoPath) === ''
        ) {
            throw new RuntimeException(
                'Foto penjemput gagal disimpan.',
            );
        }

        try {
            /** @var array{old_photo_path: string|null, was_replacement: bool} $result */
            $result = DB::transaction(
                function () use (
                    $pickupPerson,
                    $schoolId,
                    $newPhotoPath,
                ): array {
                    $lockedPickupPerson = PickupPerson::query()
                        ->where('school_id', $schoolId)
                        ->lockForUpdate()
                        ->findOrFail($pickupPerson->id);

                    $oldPhotoPath = $lockedPickupPerson->photo_path;

                    $profile = PickupPersonFaceProfile::query()
                        ->where('school_id', $schoolId)
                        ->where(
                            'pickup_person_id',
                            $lockedPickupPerson->id,
                        )
                        ->lockForUpdate()
                        ->first();

                    $hasRegisteredProfile =
                        $profile !== null
                        && $profile->status
                            === PickupPersonFaceProfile::STATUS_REGISTERED;

                    $newFaceStatus = (
                        $hasRegisteredProfile
                        || $lockedPickupPerson->face_status
                            === PickupPerson::FACE_REGISTERED
                        || $lockedPickupPerson->face_status
                            === PickupPerson::FACE_NEEDS_UPDATE
                    )
                        ? PickupPerson::FACE_NEEDS_UPDATE
                        : PickupPerson::FACE_NOT_REGISTERED;

                    $lockedPickupPerson->update([
                        'photo_path' => $newPhotoPath,
                        'face_status' => $newFaceStatus,
                    ]);

                    if (
                        $profile !== null
                        && $profile->status
                            !== PickupPersonFaceProfile::STATUS_REVOKED
                    ) {
                        $this->invalidateFaceProfileModel(
                            $profile,
                        );
                    }

                    return [
                        'old_photo_path' => is_string($oldPhotoPath)
                                ? $oldPhotoPath
                                : null,
                        'was_replacement' => is_string($oldPhotoPath)
                            && trim($oldPhotoPath) !== '',
                    ];
                },
                3,
            );
        } catch (Throwable $exception) {
            Storage::disk('public')
                ->delete($newPhotoPath);

            throw $exception;
        }

        $oldPhotoPath = $result['old_photo_path'];

        if (
            is_string($oldPhotoPath)
            && trim($oldPhotoPath) !== ''
            && $oldPhotoPath !== $newPhotoPath
        ) {
            Storage::disk('public')
                ->delete($oldPhotoPath);
        }

        return back()->with(
            'success',
            $result['was_replacement']
                ? 'Foto penjemput berhasil diperbarui.'
                : 'Foto penjemput berhasil disimpan.',
        );
    }

    /**
     * Menghapus foto penjemput.
     */
    public function deletePhoto(
        Request $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeManage($user);

        $schoolId = $this->schoolId($user);

        /** @var array{photo_path: string|null, deleted: bool} $result */
        $result = DB::transaction(
            function () use (
                $pickupPerson,
                $schoolId,
            ): array {
                $lockedPickupPerson = PickupPerson::query()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPerson->id);

                $oldPhotoPath = $lockedPickupPerson->photo_path;

                if (
                    ! is_string($oldPhotoPath)
                    || trim($oldPhotoPath) === ''
                ) {
                    return [
                        'photo_path' => null,
                        'deleted' => false,
                    ];
                }

                $lockedPickupPerson->update([
                    'photo_path' => null,
                    'face_status' => PickupPerson::FACE_NOT_REGISTERED,
                ]);

                $profile = PickupPersonFaceProfile::query()
                    ->where('school_id', $schoolId)
                    ->where(
                        'pickup_person_id',
                        $lockedPickupPerson->id,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($profile) {
                    $this->revokeFaceProfileModel(
                        $profile,
                    );
                }

                return [
                    'photo_path' => $oldPhotoPath,
                    'deleted' => true,
                ];
            },
            3,
        );

        if (! $result['deleted']) {
            return back()->with(
                'info',
                'Penjemput belum memiliki foto.',
            );
        }

        $oldPhotoPath = $result['photo_path'];

        if (
            is_string($oldPhotoPath)
            && trim($oldPhotoPath) !== ''
        ) {
            Storage::disk('public')
                ->delete($oldPhotoPath);
        }

        return back()->with(
            'success',
            'Foto penjemput berhasil dihapus.',
        );
    }

    /**
     * Mendaftarkan profil biometrik wajah penjemput.
     */
    public function registerFace(
        RegisterPickupPersonFaceRequest $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeBiometric($user);

        $schoolId = $this->schoolId($user);

        $validated = $request->validated();

        $qualityScore = (float) (
            $validated['quality_score']
            ?? 0
        );

        $minimumQualityScore = (float) config(
            'biometrics.minimum_quality_score',
            0.75,
        );

        if ($qualityScore < $minimumQualityScore) {
            throw ValidationException::withMessages([
                'quality_score' => sprintf(
                    'Kualitas wajah minimal %.0f%%. Ambil ulang foto dengan pencahayaan yang lebih baik.',
                    $minimumQualityScore * 100,
                ),
            ]);
        }

        if (
            ! (bool) (
                $validated['liveness_passed']
                ?? false
            )
        ) {
            throw ValidationException::withMessages([
                'liveness_passed' => 'Pemeriksaan keaslian wajah belum berhasil.',
            ]);
        }

        $modelName = trim(
            (string) (
                $validated['model_name']
                ?? ''
            ),
        );

        $expectedModelName = (string) config(
            'biometrics.model_name',
            'human-hse-faceres',
        );

        if ($modelName !== $expectedModelName) {
            throw ValidationException::withMessages([
                'model_name' => 'Model biometrik tidak sesuai dengan konfigurasi aplikasi.',
            ]);
        }

        /** @var array<int, mixed> $rawEmbedding */
        $rawEmbedding = $validated['embedding'];

        $embedding = collect($rawEmbedding)
            ->map(
                fn (mixed $value): float => (float) $value,
            )
            ->values()
            ->all();

        foreach ($embedding as $value) {
            if (! is_finite($value)) {
                throw ValidationException::withMessages([
                    'embedding' => 'Data biometrik mengandung nilai yang tidak valid.',
                ]);
            }
        }

        $minimumDimension = (int) config(
            'biometrics.minimum_embedding_dimension',
            64,
        );

        $maximumDimension = (int) config(
            'biometrics.maximum_embedding_dimension',
            2048,
        );

        $embeddingDimension = count($embedding);

        if (
            $embeddingDimension < $minimumDimension
            || $embeddingDimension > $maximumDimension
        ) {
            throw ValidationException::withMessages([
                'embedding' => 'Dimensi data biometrik tidak sesuai konfigurasi aplikasi.',
            ]);
        }

        DB::transaction(
            function () use (
                $pickupPerson,
                $schoolId,
                $user,
                $validated,
                $embedding,
                $embeddingDimension,
                $modelName,
                $qualityScore,
            ): void {
                $lockedPickupPerson = PickupPerson::query()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPerson->id);

                $photoPath = trim(
                    (string) $lockedPickupPerson->photo_path,
                );

                if (
                    $photoPath === ''
                    || ! Storage::disk('public')
                        ->exists($photoPath)
                ) {
                    throw ValidationException::withMessages([
                        'face' => 'Foto penjemput belum tersedia atau tidak ditemukan.',
                    ]);
                }

                try {
                    $photoContents = Storage::disk('public')
                        ->get($photoPath);
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        'face' => 'Foto penjemput gagal dibaca. Unggah ulang foto lalu coba kembali.',
                    ]);
                }

                $photoSha256 = hash(
                    'sha256',
                    $photoContents,
                );

                $profile = PickupPersonFaceProfile::query()
                    ->where('school_id', $schoolId)
                    ->where(
                        'pickup_person_id',
                        $lockedPickupPerson->id,
                    )
                    ->lockForUpdate()
                    ->first();

                $revision = $profile
                    ? (
                        (int) $profile
                            ->registration_revision
                        + 1
                    )
                    : 1;

                $profileData = [
                    'school_id' => $schoolId,
                    'pickup_person_id' => $lockedPickupPerson->id,
                    'registered_by_user_id' => $user->id,
                    'embedding' => $embedding,
                    'embedding_dimension' => $embeddingDimension,
                    'model_name' => $modelName,
                    'model_version' => trim(
                        (string) $validated['model_version'],
                    ),
                    'quality_score' => $qualityScore,
                    'liveness_passed' => true,
                    'capture_method' => (string) $validated['capture_method'],
                    'photo_sha256' => $photoSha256,
                    'status' => PickupPersonFaceProfile::STATUS_REGISTERED,
                    'registration_revision' => $revision,
                    'consent_version' => (string) config(
                        'biometrics.consent_version',
                        'v1',
                    ),
                    'consented_at' => now(),
                    'registered_at' => now(),
                    'invalidated_at' => null,
                    'revoked_at' => null,
                    'metadata' => $validated['metadata']
                        ?? null,
                ];

                if ($profile) {
                    $profile->update(
                        $profileData,
                    );
                } else {
                    PickupPersonFaceProfile::create(
                        $profileData,
                    );
                }

                $lockedPickupPerson->update([
                    'face_status' => PickupPerson::FACE_REGISTERED,
                ]);
            },
            3,
        );

        return back()->with(
            'success',
            'Wajah penjemput berhasil diregistrasikan.',
        );
    }

    /**
     * Mencabut registrasi biometrik wajah penjemput.
     */
    public function revokeFace(
        Request $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeBiometric($user);

        $schoolId = $this->schoolId($user);

        $profileFound = DB::transaction(
            function () use (
                $pickupPerson,
                $schoolId,
            ): bool {
                $lockedPickupPerson = PickupPerson::query()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPerson->id);

                $profile = PickupPersonFaceProfile::query()
                    ->where('school_id', $schoolId)
                    ->where(
                        'pickup_person_id',
                        $lockedPickupPerson->id,
                    )
                    ->lockForUpdate()
                    ->first();

                $lockedPickupPerson->update([
                    'face_status' => PickupPerson::FACE_NOT_REGISTERED,
                ]);

                if (! $profile) {
                    return false;
                }

                $this->revokeFaceProfileModel(
                    $profile,
                );

                return true;
            },
            3,
        );

        return back()->with(
            $profileFound
                ? 'success'
                : 'info',
            $profileFound
                ? 'Registrasi wajah berhasil dicabut.'
                : 'Penjemput belum memiliki registrasi wajah.',
        );
    }

    /**
     * Mengarsipkan penjemput dengan soft delete.
     *
     * Profil biometrik dicabut agar data arsip tidak dapat
     * digunakan dalam proses verifikasi gerbang.
     */
    public function destroy(
        Request $request,
        PickupPerson $pickupPerson,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureBelongsToSchool(
            $user,
            $pickupPerson,
        );

        $this->authorizeArchive($user);

        $schoolId = $this->schoolId($user);

        $name = DB::transaction(
            function () use (
                $pickupPerson,
                $schoolId,
            ): string {
                $lockedPickupPerson = PickupPerson::query()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPerson->id);

                $profile = PickupPersonFaceProfile::query()
                    ->where('school_id', $schoolId)
                    ->where(
                        'pickup_person_id',
                        $lockedPickupPerson->id,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($profile) {
                    $this->revokeFaceProfileModel(
                        $profile,
                    );
                }

                $lockedPickupPerson->update([
                    'face_status' => PickupPerson::FACE_NOT_REGISTERED,
                ]);

                $name = $lockedPickupPerson->full_name;

                $lockedPickupPerson->delete();

                return $name;
            },
            3,
        );

        return redirect()
            ->route('pickup-persons.index')
            ->with(
                'success',
                "Data {$name} berhasil dipindahkan ke arsip.",
            );
    }

    /**
     * Memulihkan data penjemput dari arsip.
     */
    public function restore(
        Request $request,
        int $pickupPersonId,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->authorizeArchive($user);

        $schoolId = $this->schoolId($user);

        $pickupPerson = DB::transaction(
            function () use (
                $pickupPersonId,
                $schoolId,
            ): PickupPerson {
                $pickupPerson = PickupPerson::query()
                    ->onlyTrashed()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPersonId);

                $pickupPerson->restore();

                return $pickupPerson;
            },
            3,
        );

        return redirect()
            ->route('pickup-persons.archive')
            ->with(
                'success',
                "Data {$pickupPerson->full_name} berhasil dipulihkan.",
            );
    }

    /**
     * Menghapus permanen data penjemput dari database.
     */
    public function forceDelete(
        Request $request,
        int $pickupPersonId,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->authorizeArchive($user);

        $schoolId = $this->schoolId($user);

        $result = DB::transaction(
            function () use (
                $pickupPersonId,
                $schoolId,
            ): array {
                $pickupPerson = PickupPerson::query()
                    ->onlyTrashed()
                    ->where('school_id', $schoolId)
                    ->lockForUpdate()
                    ->findOrFail($pickupPersonId);

                $result = [
                    'id' => (int) $pickupPerson->id,

                    'name' => $pickupPerson->full_name,
                ];

                $pickupPerson
                    ->students()
                    ->detach();

                $pickupPerson->forceDelete();

                return $result;
            },
            3,
        );

        Storage::disk('public')
            ->deleteDirectory(
                $this->pickupPersonPhotoDirectory(
                    $schoolId,
                    $result['id'],
                ),
            );

        return redirect()
            ->route('pickup-persons.archive')
            ->with(
                'success',
                "Data {$result['name']} berhasil dihapus permanen.",
            );
    }

    /**
     * Mengambil pengguna aktif yang sedang login.
     */
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

    /**
     * Mengambil ID sekolah pengguna.
     */
    private function schoolId(
        User $user,
    ): int {
        abort_if(
            $user->school_id === null,
            403,
            'Akun belum terhubung dengan sekolah.',
        );

        return (int) $user->school_id;
    }

    /**
     * Memastikan pengguna boleh melihat penjemput.
     */
    private function authorizeView(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_GATE_OFFICER,
                User::ROLE_TEACHER,
            ),
            403,
            'Anda tidak memiliki izin melihat data penjemput.',
        );
    }

    /**
     * Memastikan pengguna boleh mengelola penjemput.
     */
    private function authorizeManage(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_TEACHER,
            ),
            403,
            'Anda tidak memiliki izin mengelola data penjemput.',
        );
    }

    /**
     * Memastikan pengguna boleh mengarsipkan penjemput.
     */
    private function authorizeArchive(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            ),
            403,
            'Hanya administrator sekolah yang dapat mengelola arsip penjemput.',
        );
    }

    /**
     * Memastikan pengguna boleh mengelola data biometrik.
     */
    private function authorizeBiometric(
        User $user,
    ): void {
        abort_unless(
            $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            ),
            403,
            'Hanya administrator sekolah yang dapat mengelola registrasi wajah.',
        );
    }

    /**
     * Membentuk permission untuk frontend.
     *
     * @return array{
     *     can_manage: bool,
     *     can_archive: bool,
     *     can_manage_face: bool
     * }
     */
    private function permissions(
        User $user,
    ): array {
        return [
            'can_manage' => $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_TEACHER,
            ),

            'can_archive' => $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            ),

            'can_manage_face' => $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
            ),
        ];
    }

    /**
     * Memastikan penjemput berasal dari sekolah pengguna.
     */
    private function ensureBelongsToSchool(
        User $user,
        PickupPerson $pickupPerson,
    ): void {
        abort_unless(
            (int) $pickupPerson->school_id
                === $this->schoolId($user),
            404,
            'Data penjemput tidak ditemukan.',
        );
    }

    /**
     * Mengambil siswa yang dapat dipilih dalam form.
     *
     * @param  array<int, int>  $selectedIds
     * @return array<int, array{
     *     id: int,
     *     full_name: string,
     *     student_number: string,
     *     status: string,
     *     class_name: string|null,
     *     academic_year: string|null
     * }>
     */
    private function studentOptions(
        int $schoolId,
        array $selectedIds = [],
    ): array {
        $selectedIds = collect($selectedIds)
            ->map(
                fn ($id): int => (int) $id,
            )
            ->filter(
                fn (int $id): bool => $id > 0,
            )
            ->unique()
            ->values()
            ->all();

        return Student::query()
            ->where('school_id', $schoolId)
            ->where(
                function (
                    Builder $query,
                ) use ($selectedIds): void {
                    $query->where(
                        'status',
                        Student::STATUS_ACTIVE,
                    );

                    if ($selectedIds !== []) {
                        $query->orWhereIn(
                            'id',
                            $selectedIds,
                        );
                    }
                },
            )
            ->with([
                'schoolClass:id,name,grade_level,academic_year',
            ])
            ->orderBy('full_name')
            ->get([
                'id',
                'school_class_id',
                'student_number',
                'full_name',
                'status',
            ])
            ->map(
                fn (Student $student): array => [
                    'id' => $student->id,

                    'full_name' => $student->full_name,

                    'student_number' => $student->student_number,

                    'status' => $student->status,

                    'class_name' => $student
                        ->schoolClass
                        ?->name,

                    'academic_year' => $student
                        ->schoolClass
                        ?->academic_year,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Memastikan seluruh siswa pada payload berasal dari sekolah pengguna.
     *
     * @param  array<int, array<string, mixed>>  $studentLinks
     *
     * @throws ValidationException
     */
    private function assertStudentLinksBelongToSchool(
        array $studentLinks,
        int $schoolId,
    ): void {
        $studentIds = collect($studentLinks)
            ->map(
                fn (array $link): int => (int) ($link['student_id'] ?? 0),
            )
            ->filter(
                fn (int $studentId): bool => $studentId > 0,
            )
            ->values();

        if ($studentIds->isEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'Pilih minimal satu siswa yang boleh dijemput.',
            ]);
        }

        if ($studentIds->count() !== count($studentLinks)) {
            throw ValidationException::withMessages([
                'students' => 'Pilihan siswa tidak valid. Muat ulang halaman lalu pilih kembali siswa.',
            ]);
        }

        if (
            $studentIds->unique()->count()
            !== $studentIds->count()
        ) {
            throw ValidationException::withMessages([
                'students' => 'Siswa yang sama tidak boleh dipilih lebih dari satu kali.',
            ]);
        }

        $validStudentCount = Student::query()
            ->where('school_id', $schoolId)
            ->whereIn(
                'id',
                $studentIds->all(),
            )
            ->count();

        if ($validStudentCount !== $studentIds->count()) {
            throw ValidationException::withMessages([
                'students' => 'Salah satu siswa tidak ditemukan atau bukan milik sekolah Anda.',
            ]);
        }
    }

    /**
     * Menyelaraskan relasi penjemput dengan siswa.
     *
     * @param  array<int, array<string, mixed>>  $studentLinks
     */
    private function syncStudents(
        PickupPerson $pickupPerson,
        array $studentLinks,
        int $schoolId,
    ): void {
        $syncData = collect($studentLinks)
            ->mapWithKeys(
                function (
                    array $link,
                ) use ($schoolId): array {
                    $studentId = (int) (
                        $link['student_id'] ?? 0
                    );

                    return [
                        $studentId => [
                            'school_id' => $schoolId,

                            'relationship_type' => (string) (
                                $link['relationship_type']
                                ?? 'other'
                            ),

                            'is_primary' => (bool) (
                                $link['is_primary']
                                ?? false
                            ),

                            'is_active' => (bool) (
                                $link['is_active']
                                ?? true
                            ),

                            'valid_from' => ($link['valid_from'] ?? null)
                                ?: null,

                            'valid_until' => ($link['valid_until'] ?? null)
                                ?: null,
                        ],
                    ];
                },
            )
            ->all();

        $pickupPerson
            ->students()
            ->sync($syncData);
    }

    /**
     * Membentuk data penjemput untuk halaman arsip.
     *
     * @return array{
     *     id: int,
     *     full_name: string,
     *     initials: string,
     *     identity_number: string|null,
     *     phone: string,
     *     email: string|null,
     *     face_status: string,
     *     is_active: bool,
     *     deleted_at: string|null
     * }
     */
    private function archivedPickupPersonListItem(
        PickupPerson $pickupPerson,
    ): array {
        return [
            'id' => $pickupPerson->id,

            'full_name' => $pickupPerson->full_name,

            'initials' => $this->makeInitials(
                $pickupPerson->full_name,
            ),

            'identity_number' => $this->maskIdentityNumber(
                $pickupPerson->identity_number,
            ),

            'phone' => $pickupPerson->phone,

            'email' => $pickupPerson->email,

            'face_status' => $pickupPerson->face_status,

            'is_active' => (bool) $pickupPerson->is_active,

            'deleted_at' => $pickupPerson->deleted_at
                ?->toISOString(),
        ];
    }

    /**
     * Membentuk data penjemput untuk halaman daftar.
     */
    private function pickupPersonListItem(
        PickupPerson $pickupPerson,
    ): array {
        return [
            'id' => $pickupPerson->id,

            'full_name' => $pickupPerson->full_name,

            'initials' => $this->makeInitials(
                $pickupPerson->full_name,
            ),

            'identity_number' => $this->maskIdentityNumber(
                $pickupPerson->identity_number,
            ),

            'phone' => $pickupPerson->phone,

            'email' => $pickupPerson->email,

            'photo_path' => $pickupPerson->photo_path,

            'photo_url' => $this->pickupPersonPhotoUrl(
                $pickupPerson->photo_path,
            ),

            'face_status' => $pickupPerson->face_status,

            'is_active' => (bool) $pickupPerson->is_active,

            'students_count' => $pickupPerson
                ->students
                ->count(),

            'students' => $pickupPerson
                ->students
                ->take(3)
                ->map(
                    fn (
                        Student $student,
                    ): array => [
                        'id' => $student->id,

                        'full_name' => $student->full_name,

                        'student_number' => $student->student_number,

                        'relationship_type' => (string) $student->pivot
                            ->relationship_type,

                        'is_primary' => (bool) $student->pivot
                            ->is_primary,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Membentuk data lengkap penjemput untuk halaman detail.
     */
    private function pickupPersonDetail(
        PickupPerson $pickupPerson,
    ): array {
        return [
            'id' => $pickupPerson->id,

            'full_name' => $pickupPerson->full_name,

            'initials' => $this->makeInitials(
                $pickupPerson->full_name,
            ),

            'identity_number' => $pickupPerson->identity_number,

            'phone' => $pickupPerson->phone,

            'email' => $pickupPerson->email,

            'address' => $pickupPerson->address,

            'photo_path' => $pickupPerson->photo_path,

            'photo_url' => $this->pickupPersonPhotoUrl(
                $pickupPerson->photo_path,
            ),

            'face_status' => $pickupPerson->face_status,

            'face_profile' => $this->faceProfilePayload(
                $pickupPerson->faceProfile,
            ),

            'is_active' => (bool) $pickupPerson->is_active,

            'notes' => $pickupPerson->notes,

            'students' => $pickupPerson
                ->students
                ->map(
                    fn (
                        Student $student,
                    ): array => [
                        'id' => $student->id,

                        'full_name' => $student->full_name,

                        'student_number' => $student->student_number,

                        'status' => $student->status,

                        'class_name' => $student
                            ->schoolClass
                            ?->name,

                        'academic_year' => $student
                            ->schoolClass
                            ?->academic_year,

                        'relationship_type' => (string) $student->pivot
                            ->relationship_type,

                        'is_primary' => (bool) $student->pivot
                            ->is_primary,

                        'is_active' => (bool) $student->pivot
                            ->is_active,

                        'valid_from' => $this->formatDateValue(
                            $student->pivot
                                ->valid_from,
                        ),

                        'valid_until' => $this->formatDateValue(
                            $student->pivot
                                ->valid_until,
                        ),
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * Membentuk metadata profil wajah tanpa mengirim embedding.
     *
     * @return array<string, mixed>|null
     */
    private function faceProfilePayload(
        ?PickupPersonFaceProfile $profile,
    ): ?array {
        if (! $profile) {
            return null;
        }

        return [
            'status' => $profile->status,

            'embedding_dimension' => $profile->embedding_dimension,

            'model_name' => $profile->model_name,

            'model_version' => $profile->model_version,

            'quality_score' => $profile->quality_score !== null
                    ? (float) $profile->quality_score
                    : null,

            'liveness_passed' => (bool) $profile->liveness_passed,

            'capture_method' => $profile->capture_method,

            'registration_revision' => (int) $profile->registration_revision,

            'consent_version' => $profile->consent_version,

            'consented_at' => $profile->consented_at
                ?->toISOString(),

            'registered_at' => $profile->registered_at
                ?->toISOString(),

            'invalidated_at' => $profile->invalidated_at
                ?->toISOString(),

            'revoked_at' => $profile->revoked_at
                ?->toISOString(),
        ];
    }

    /**
     * Menonaktifkan satu model profil wajah yang sudah dikunci.
     */
    private function invalidateFaceProfileModel(
        PickupPersonFaceProfile $profile,
    ): void {
        $profile->update([
            'embedding' => null,
            'embedding_dimension' => null,
            'quality_score' => null,
            'liveness_passed' => false,
            'photo_sha256' => null,
            'status' => PickupPersonFaceProfile::STATUS_NEEDS_UPDATE,
            'invalidated_at' => now(),
            'revoked_at' => null,
            'metadata' => null,
        ]);
    }

    /**
     * Mencabut dan menghapus data sensitif profil wajah.
     */
    private function revokeFaceProfileModel(
        PickupPersonFaceProfile $profile,
    ): void {
        $profile->update([
            'embedding' => null,
            'embedding_dimension' => null,
            'quality_score' => null,
            'liveness_passed' => false,
            'photo_sha256' => null,
            'status' => PickupPersonFaceProfile::STATUS_REVOKED,
            'invalidated_at' => null,
            'revoked_at' => now(),
            'metadata' => null,
        ]);
    }

    /**
     * Membentuk direktori foto berdasarkan sekolah dan penjemput.
     */
    private function pickupPersonPhotoDirectory(
        int $schoolId,
        int $pickupPersonId,
    ): string {
        return sprintf(
            'schools/%d/pickup-persons/%d',
            $schoolId,
            $pickupPersonId,
        );
    }

    /**
     * Membentuk URL publik foto penjemput.
     */
    private function pickupPersonPhotoUrl(
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

    /**
     * Membuat maksimal dua huruf inisial.
     */
    private function makeInitials(
        string $name,
    ): string {
        $words = preg_split(
            '/\s+/',
            trim($name),
        ) ?: [];

        return collect($words)
            ->filter()
            ->take(2)
            ->map(
                fn (string $word): string => mb_strtoupper(
                    mb_substr(
                        $word,
                        0,
                        1,
                    ),
                ),
            )
            ->implode('');
    }

    /**
     * Menyamarkan nomor identitas di halaman daftar.
     */
    private function maskIdentityNumber(
        ?string $identityNumber,
    ): ?string {
        if (
            $identityNumber === null
            || trim($identityNumber) === ''
        ) {
            return null;
        }

        $identityNumber = trim(
            $identityNumber,
        );

        if (strlen($identityNumber) <= 8) {
            return $identityNumber;
        }

        return substr(
            $identityNumber,
            0,
            4,
        )
            .str_repeat(
                '•',
                strlen($identityNumber) - 8,
            )
            .substr(
                $identityNumber,
                -4,
            );
    }

    /**
     * Menyeragamkan nilai tanggal pivot menjadi format YYYY-MM-DD.
     */
    private function formatDateValue(
        mixed $value,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }
}
