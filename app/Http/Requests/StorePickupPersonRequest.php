<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PickupPerson;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StorePickupPersonRequest extends FormRequest
{
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

    protected function prepareForValidation(): void
    {
        $studentLinks = collect(
            $this->input('students', []),
        )
            ->filter(fn ($link): bool => is_array($link))
            ->map(function (array $link): array {
                return [
                    'student_id' => $link['student_id'] ?? null,

                    'relationship_type' => trim(
                        (string) (
                            $link['relationship_type']
                            ?? ''
                        ),
                    ),

                    'is_primary' => filter_var(
                        $link['is_primary'] ?? false,
                        FILTER_VALIDATE_BOOLEAN,
                    ),

                    'is_active' => filter_var(
                        $link['is_active'] ?? true,
                        FILTER_VALIDATE_BOOLEAN,
                    ),

                    'valid_from' => filled(
                        $link['valid_from'] ?? null,
                    )
                        ? $link['valid_from']
                        : null,

                    'valid_until' => filled(
                        $link['valid_until'] ?? null,
                    )
                        ? $link['valid_until']
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'full_name' => trim(
                (string) $this->input('full_name'),
            ),

            'identity_number' => $this->filled(
                'identity_number',
            )
                ? preg_replace(
                    '/\D/',
                    '',
                    (string) $this->input(
                        'identity_number',
                    ),
                )
                : null,

            'phone' => trim(
                (string) $this->input('phone'),
            ),

            'email' => $this->filled('email')
                ? mb_strtolower(
                    trim(
                        (string) $this->input('email'),
                    ),
                )
                : null,

            'address' => $this->filled('address')
                ? trim(
                    (string) $this->input('address'),
                )
                : null,

            'face_status' => trim(
                (string) $this->input(
                    'face_status',
                    PickupPerson::FACE_NOT_REGISTERED,
                ),
            ),

            'is_active' => $this->boolean(
                'is_active',
                true,
            ),

            'notes' => $this->filled('notes')
                ? trim(
                    (string) $this->input('notes'),
                )
                : null,

            'students' => $studentLinks,
        ]);
    }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;

        return [
            'full_name' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'identity_number' => [
                'nullable',
                'string',
                'min:8',
                'max:30',
                $this->identityNumberRule($schoolId),
            ],

            'phone' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/^[0-9+\-\s()]+$/',
            ],

            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'face_status' => [
                'required',
                Rule::in([
                    PickupPerson::FACE_NOT_REGISTERED,
                    PickupPerson::FACE_REGISTERED,
                    PickupPerson::FACE_NEEDS_UPDATE,
                ]),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'students' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'students.*.student_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('students', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'school_id',
                                $schoolId,
                            )
                            ->whereNull('deleted_at'),
                    ),
            ],

            'students.*.relationship_type' => [
                'required',
                'string',
                Rule::in([
                    'father',
                    'mother',
                    'sibling',
                    'relative',
                    'driver',
                    'guardian',
                    'other',
                ]),
            ],

            'students.*.is_primary' => [
                'required',
                'boolean',
            ],

            'students.*.is_active' => [
                'required',
                'boolean',
            ],

            'students.*.valid_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'students.*.valid_until' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ];
    }

    /**
     * Validasi tambahan untuk periode izin.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $links = $this->input(
                    'students',
                    [],
                );

                if (! is_array($links)) {
                    return;
                }

                foreach ($links as $index => $link) {
                    if (! is_array($link)) {
                        continue;
                    }

                    $validFrom =
                        $link['valid_from'] ?? null;

                    $validUntil =
                        $link['valid_until'] ?? null;

                    if (
                        ! is_string($validFrom)
                        || ! is_string($validUntil)
                        || $validFrom === ''
                        || $validUntil === ''
                    ) {
                        continue;
                    }

                    if (
                        preg_match(
                            '/^\d{4}-\d{2}-\d{2}$/',
                            $validFrom,
                        ) !== 1
                        || preg_match(
                            '/^\d{4}-\d{2}-\d{2}$/',
                            $validUntil,
                        ) !== 1
                    ) {
                        continue;
                    }

                    if ($validUntil < $validFrom) {
                        $validator->errors()->add(
                            "students.{$index}.valid_until",
                            'Tanggal akhir izin tidak boleh lebih awal dari tanggal mulai.',
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap penjemput wajib diisi.',

            'full_name.min' => 'Nama lengkap minimal terdiri dari 3 karakter.',

            'identity_number.min' => 'Nomor identitas minimal terdiri dari 8 angka.',

            'identity_number.unique' => 'Nomor identitas sudah digunakan penjemput lain.',

            'phone.required' => 'Nomor telepon wajib diisi.',

            'phone.regex' => 'Format nomor telepon tidak valid.',

            'email.email' => 'Format alamat email tidak valid.',

            'face_status.required' => 'Status pendaftaran wajah wajib dipilih.',

            'face_status.in' => 'Status pendaftaran wajah tidak valid.',

            'students.required' => 'Pilih minimal satu siswa yang boleh dijemput.',

            'students.min' => 'Pilih minimal satu siswa yang boleh dijemput.',

            'students.max' => 'Maksimal 20 siswa dapat dihubungkan.',

            'students.*.student_id.required' => 'Siswa wajib dipilih.',

            'students.*.student_id.distinct' => 'Siswa yang sama tidak boleh dipilih lebih dari satu kali.',

            'students.*.student_id.exists' => 'Siswa yang dipilih tidak tersedia.',

            'students.*.relationship_type.required' => 'Hubungan penjemput dengan siswa wajib dipilih.',

            'students.*.relationship_type.in' => 'Jenis hubungan penjemput tidak valid.',

            'address.max' => 'Alamat maksimal 2.000 karakter.',

            'notes.max' => 'Catatan maksimal 2.000 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'identity_number' => 'nomor identitas',
            'phone' => 'nomor telepon',
            'email' => 'email',
            'address' => 'alamat',
            'face_status' => 'status wajah',
            'is_active' => 'status penjemput',
            'students' => 'siswa',
            'notes' => 'catatan',
        ];
    }

    protected function pickupPersonId(): ?int
    {
        return null;
    }

    private function identityNumberRule(
        int|string|null $schoolId,
    ): Unique {
        $rule = Rule::unique(
            'pickup_persons',
            'identity_number',
        )->where(
            fn ($query) => $query->where(
                'school_id',
                $schoolId,
            ),
        );

        $pickupPersonId =
            $this->pickupPersonId();

        if ($pickupPersonId !== null) {
            $rule->ignore($pickupPersonId);
        }

        return $rule;
    }
}
