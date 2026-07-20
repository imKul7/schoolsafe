<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Menampilkan daftar siswa milik sekolah pengguna.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $schoolId = $this->schoolId($user);

        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'class_id' => [
                'nullable',
                'integer',
                Rule::exists('school_classes', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('school_id', $schoolId)
                            ->where('is_active', true),
                    ),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Student::STATUS_ACTIVE,
                    Student::STATUS_INACTIVE,
                    Student::STATUS_GRADUATED,
                ]),
            ],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        $classId = isset($filters['class_id'])
            ? (int) $filters['class_id']
            : null;

        $status = isset($filters['status'])
            ? (string) $filters['status']
            : null;

        $query = Student::query()
            ->where('school_id', $schoolId)
            ->with([
                'schoolClass:id,name,grade_level,academic_year,homeroom_teacher',
            ]);

        if ($search !== '') {
            $query->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
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

        if ($classId !== null) {
            $query->where('school_class_id', $classId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $students = $query
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString()
            ->through(
                fn (Student $student): array =>
                    $this->studentListItem($student),
            );

        $classes = $this->getActiveClasses($schoolId);

        return Inertia::render('students/index', [
            'students' => $students,

            'classes' => $classes,

            'filters' => [
                'search' => $search,
                'class_id' => $classId ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    /**
     * Menampilkan form tambah siswa.
     */
    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $schoolId = $this->schoolId($user);

        $this->authorizeRoles(
            $user,
            User::ROLE_SCHOOL_ADMIN,
            User::ROLE_TEACHER,
        );

        return Inertia::render('students/create', [
            'classes' => $this->getActiveClasses($schoolId, true),
        ]);
    }

    /**
     * Menyimpan siswa baru.
     */
    public function store(
        StoreStudentRequest $request,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);
        $schoolId = $this->schoolId($user);

        $student = Student::create([
            ...$request->validated(),
            'school_id' => $schoolId,
        ]);

        return redirect()
            ->route('students.show', $student)
            ->with(
                'success',
                'Data siswa berhasil ditambahkan.',
            );
    }

    /**
     * Menampilkan detail siswa.
     */
    public function show(
        Request $request,
        Student $student,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->ensureStudentBelongsToSchool(
            $user,
            $student,
        );

        $student->loadMissing([
            'schoolClass:id,name,grade_level,academic_year,homeroom_teacher',
        ]);

        return Inertia::render('students/show', [
            'student' => $this->studentDetail($student),
        ]);
    }

    /**
     * Menampilkan form edit siswa.
     */
    public function edit(
        Request $request,
        Student $student,
    ): Response {
        $user = $this->authenticatedUser($request);
        $schoolId = $this->schoolId($user);

        $this->ensureStudentBelongsToSchool(
            $user,
            $student,
        );

        $this->authorizeRoles(
            $user,
            User::ROLE_SCHOOL_ADMIN,
            User::ROLE_TEACHER,
        );

        return Inertia::render('students/edit', [
            'student' => [
                'id' => $student->id,

                'school_class_id' =>
                    (string) $student->school_class_id,

                'student_number' =>
                    $student->student_number,

                'nisn' =>
                    $student->nisn ?? '',

                'full_name' =>
                    $student->full_name,

                'gender' =>
                    $student->gender,

                'date_of_birth' =>
                    $student->date_of_birth?->format('Y-m-d') ?? '',

                'status' =>
                    $student->status,

                'notes' =>
                    $student->notes ?? '',
            ],

            'classes' => $this->getActiveClasses(
                $schoolId,
                true,
            ),
        ]);
    }

    /**
     * Memperbarui data siswa.
     */
    public function update(
        UpdateStudentRequest $request,
        Student $student,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureStudentBelongsToSchool(
            $user,
            $student,
        );

        $student->update(
            $request->validated(),
        );

        return redirect()
            ->route('students.show', $student)
            ->with(
                'success',
                'Data siswa berhasil diperbarui.',
            );
    }

    /**
     * Mengaktifkan atau menonaktifkan siswa.
     */
    public function toggleStatus(
        Request $request,
        Student $student,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureStudentBelongsToSchool(
            $user,
            $student,
        );

        $this->authorizeRoles(
            $user,
            User::ROLE_SCHOOL_ADMIN,
            User::ROLE_TEACHER,
        );

        abort_if(
            $student->status === Student::STATUS_GRADUATED,
            422,
            'Status siswa yang telah lulus tidak dapat diubah melalui tindakan ini.',
        );

        $newStatus = $student->status === Student::STATUS_ACTIVE
            ? Student::STATUS_INACTIVE
            : Student::STATUS_ACTIVE;

        $student->update([
            'status' => $newStatus,
        ]);

        $message = $newStatus === Student::STATUS_ACTIVE
            ? 'Siswa berhasil diaktifkan.'
            : 'Siswa berhasil dinonaktifkan.';

        return back()->with('success', $message);
    }

    /**
     * Memindahkan siswa ke arsip menggunakan soft delete.
     */
    public function destroy(
        Request $request,
        Student $student,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->ensureStudentBelongsToSchool(
            $user,
            $student,
        );

        $this->authorizeRoles(
            $user,
            User::ROLE_SCHOOL_ADMIN,
        );

        $studentName = $student->full_name;

        $student->delete();

        return redirect()
            ->route('students.index')
            ->with(
                'success',
                "Data {$studentName} berhasil dipindahkan ke arsip.",
            );
    }

    /**
     * Mengambil pengguna yang sedang login dengan tipe User.
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
            $user->is_active,
            403,
            'Akun Anda sedang tidak aktif.',
        );

        return $user;
    }

    /**
     * Mengambil ID sekolah pengguna.
     */
    private function schoolId(User $user): int
    {
        abort_if(
            $user->school_id === null,
            403,
            'Akun belum terhubung dengan sekolah.',
        );

        abort_if(
            $user->school === null,
            403,
            'Data sekolah tidak ditemukan.',
        );

        abort_unless(
            $user->school->is_active,
            403,
            'Sekolah sedang tidak aktif.',
        );

        return (int) $user->school_id;
    }

    /**
     * Memastikan siswa berasal dari sekolah yang sama.
     */
    private function ensureStudentBelongsToSchool(
        User $user,
        Student $student,
    ): void {
        $schoolId = $this->schoolId($user);

        abort_unless(
            (int) $student->school_id === $schoolId,
            404,
            'Data siswa tidak ditemukan.',
        );
    }

    /**
     * Memastikan pengguna memiliki salah satu role.
     */
    private function authorizeRoles(
        User $user,
        string ...$roles,
    ): void {
        abort_unless(
            $user->hasRole(...$roles),
            403,
            'Anda tidak memiliki izin untuk melakukan tindakan ini.',
        );
    }

    /**
     * Mengambil seluruh kelas aktif sekolah.
     */
    private function getActiveClasses(
        int $schoolId,
        bool $includeTeacher = false,
    ) {
        $columns = [
            'id',
            'name',
            'grade_level',
            'academic_year',
        ];

        if ($includeTeacher) {
            $columns[] = 'homeroom_teacher';
        }

        return SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get($columns);
    }

    /**
     * Mengubah model siswa menjadi data untuk tabel.
     */
    private function studentListItem(
        Student $student,
    ): array {
        $schoolClass = $student->schoolClass;

        abort_if(
            $schoolClass === null,
            500,
            'Relasi kelas siswa tidak ditemukan.',
        );

        return [
            'id' => $student->id,

            'student_number' =>
                $student->student_number,

            'nisn' =>
                $student->nisn,

            'full_name' =>
                $student->full_name,

            'gender' =>
                $student->gender,

            'date_of_birth' =>
                $student->date_of_birth?->format('d-m-Y'),

            'status' =>
                $student->status,

            'initials' =>
                $this->makeInitials($student->full_name),

            'class' => [
                'id' =>
                    $schoolClass->id,

                'name' =>
                    $schoolClass->name,

                'grade_level' =>
                    $schoolClass->grade_level,

                'academic_year' =>
                    $schoolClass->academic_year,
            ],
        ];
    }

    /**
     * Mengubah model siswa menjadi data halaman detail.
     */
    private function studentDetail(
        Student $student,
    ): array {
        $schoolClass = $student->schoolClass;

        abort_if(
            $schoolClass === null,
            500,
            'Relasi kelas siswa tidak ditemukan.',
        );

        return [
            'id' => $student->id,

            'student_number' =>
                $student->student_number,

            'nisn' =>
                $student->nisn,

            'full_name' =>
                $student->full_name,

            'gender' =>
                $student->gender,

            'date_of_birth' =>
                $student->date_of_birth?->format('d-m-Y'),

            'status' =>
                $student->status,

            'notes' =>
                $student->notes,

            'photo_path' =>
                $student->photo_path,

            'initials' =>
                $this->makeInitials($student->full_name),

            'class' => [
                'id' =>
                    $schoolClass->id,

                'name' =>
                    $schoolClass->name,

                'grade_level' =>
                    $schoolClass->grade_level,

                'academic_year' =>
                    $schoolClass->academic_year,

                'homeroom_teacher' =>
                    $schoolClass->homeroom_teacher,
            ],
        ];
    }

    /**
     * Membuat maksimal dua huruf inisial nama.
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
                fn (string $word): string =>
                    mb_strtoupper(
                        mb_substr($word, 0, 1),
                    ),
            )
            ->implode('');
    }
}