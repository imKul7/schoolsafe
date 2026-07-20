<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $student = $this->route('student');

        return $user !== null
            && $student instanceof Student
            && $user->school_id !== null
            && (int) $student->school_id === (int) $user->school_id
            && $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_TEACHER,
            );
    }

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

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;
        $student = $this->route('student');

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
                    ->ignore($student)
                    ->where(
                        fn ($query) => $query
                            ->where('school_id', $schoolId),
                    ),
            ],

            'nisn' => [
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique('students', 'nisn')
                    ->ignore($student),
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

    public function messages(): array
    {
        return [
            'school_class_id.required' =>
                'Kelas siswa wajib dipilih.',

            'school_class_id.exists' =>
                'Kelas yang dipilih tidak valid.',

            'student_number.required' =>
                'Nomor siswa wajib diisi.',

            'student_number.unique' =>
                'Nomor siswa sudah digunakan.',

            'nisn.regex' =>
                'NISN harus terdiri dari tepat 10 angka.',

            'nisn.unique' =>
                'NISN sudah digunakan siswa lain.',

            'full_name.required' =>
                'Nama lengkap siswa wajib diisi.',

            'full_name.min' =>
                'Nama lengkap minimal terdiri dari 3 karakter.',

            'gender.required' =>
                'Jenis kelamin wajib dipilih.',

            'date_of_birth.before_or_equal' =>
                'Tanggal lahir tidak boleh melewati hari ini.',

            'status.required' =>
                'Status siswa wajib dipilih.',

            'notes.max' =>
                'Catatan maksimal 2.000 karakter.',
        ];
    }
}