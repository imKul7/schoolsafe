<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * Menentukan apakah pengguna boleh menambahkan siswa.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->school_id !== null
            && $user->is_active
            && $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_TEACHER,
            );
    }

    /**
     * Merapikan input sebelum proses validasi.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'student_number' => mb_strtoupper(
                trim((string) $this->input('student_number')),
            ),

            'full_name' => trim(
                (string) $this->input('full_name'),
            ),

            'nisn' => $this->filled('nisn')
                ? trim((string) $this->input('nisn'))
                : null,

            'date_of_birth' => $this->filled('date_of_birth')
                ? $this->input('date_of_birth')
                : null,

            'notes' => $this->filled('notes')
                ? trim((string) $this->input('notes'))
                : null,
        ]);
    }

    /**
     * Aturan validasi data siswa.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;

        return [
            'school_class_id' => [
                'required',
                'integer',
                Rule::exists('school_classes', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('school_id', $schoolId)
                            ->where('is_active', true),
                    ),
            ],

            'student_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_number')
                    ->where(
                        fn ($query) => $query
                            ->where('school_id', $schoolId),
                    ),
            ],

            'nisn' => [
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique('students', 'nisn'),
            ],

            'full_name' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'gender' => [
                'required',
                Rule::in([
                    'male',
                    'female',
                ]),
            ],

            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'status' => [
                'required',
                Rule::in([
                    Student::STATUS_ACTIVE,
                    Student::STATUS_INACTIVE,
                    Student::STATUS_GRADUATED,
                ]),
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Pesan validasi berbahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_class_id.required' =>
                'Kelas siswa wajib dipilih.',

            'school_class_id.integer' =>
                'Kelas yang dipilih tidak valid.',

            'school_class_id.exists' =>
                'Kelas yang dipilih tidak tersedia atau tidak aktif.',

            'student_number.required' =>
                'Nomor siswa wajib diisi.',

            'student_number.max' =>
                'Nomor siswa maksimal 50 karakter.',

            'student_number.unique' =>
                'Nomor siswa sudah digunakan di sekolah ini.',

            'nisn.regex' =>
                'NISN harus terdiri dari tepat 10 angka.',

            'nisn.unique' =>
                'NISN sudah digunakan siswa lain.',

            'full_name.required' =>
                'Nama lengkap siswa wajib diisi.',

            'full_name.min' =>
                'Nama lengkap minimal terdiri dari 3 karakter.',

            'full_name.max' =>
                'Nama lengkap maksimal 255 karakter.',

            'gender.required' =>
                'Jenis kelamin wajib dipilih.',

            'gender.in' =>
                'Jenis kelamin yang dipilih tidak valid.',

            'date_of_birth.date' =>
                'Tanggal lahir tidak valid.',

            'date_of_birth.before_or_equal' =>
                'Tanggal lahir tidak boleh melewati hari ini.',

            'status.required' =>
                'Status siswa wajib dipilih.',

            'status.in' =>
                'Status siswa yang dipilih tidak valid.',

            'notes.max' =>
                'Catatan maksimal 2.000 karakter.',
        ];
    }

    /**
     * Nama atribut untuk pesan validasi.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'school_class_id' => 'kelas',
            'student_number' => 'nomor siswa',
            'nisn' => 'NISN',
            'full_name' => 'nama lengkap',
            'gender' => 'jenis kelamin',
            'date_of_birth' => 'tanggal lahir',
            'status' => 'status',
            'notes' => 'catatan',
        ];
    }
}