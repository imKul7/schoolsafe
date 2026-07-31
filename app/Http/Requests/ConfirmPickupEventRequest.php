<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmPickupEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return
            $user instanceof User
            && (bool) $user->is_active
            && (int) $user->school_id > 0
            && $user->hasRole(
                'school_admin',
                'gate_officer',
            );
    }

    protected function prepareForValidation(): void
    {
        $idempotencyKey =
            $this->input(
                'idempotency_key',
            );

        $notes =
            $this->input('notes');

        $studentIds =
            $this->input(
                'student_ids',
            );

        if (is_array($studentIds)) {
            $studentIds =
                array_values(
                    array_map(
                        static function (
                            mixed $value,
                        ): mixed {
                            if (
                                is_int($value)
                            ) {
                                return $value;
                            }

                            if (
                                is_string($value)
                                && ctype_digit(
                                    trim($value),
                                )
                            ) {
                                return (int) trim(
                                    $value,
                                );
                            }

                            return $value;
                        },
                        $studentIds,
                    ),
                );
        }

        $this->merge([
            'idempotency_key' => is_string(
                $idempotencyKey,
            )
                    ? strtolower(
                        trim(
                            $idempotencyKey,
                        ),
                    )
                    : $idempotencyKey,

            'student_ids' => $studentIds,

            'notes' => is_string($notes)
                    ? trim($notes)
                    : $notes,
        ]);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => [
                'bail',
                'required',
                'string',
                'uuid',
                'max:36',
            ],

            'face_verification_attempt_id' => [
                'bail',
                'required',
                'integer',
                'min:1',
            ],

            'student_ids' => [
                'bail',
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'student_ids.*' => [
                'bail',
                'required',
                'integer',
                'distinct',
                'min:1',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Kunci idempotency wajib dikirim.',

            'idempotency_key.uuid' => 'Kunci idempotency harus berupa UUID yang valid.',

            'face_verification_attempt_id.required' => 'Hasil verifikasi wajah wajib dipilih.',

            'face_verification_attempt_id.integer' => 'ID hasil verifikasi wajah tidak valid.',

            'student_ids.required' => 'Pilih minimal satu siswa yang akan diserahkan.',

            'student_ids.array' => 'Daftar siswa harus berupa array.',

            'student_ids.min' => 'Pilih minimal satu siswa yang akan diserahkan.',

            'student_ids.max' => 'Maksimal 20 siswa dalam satu transaksi.',

            'student_ids.*.integer' => 'Salah satu ID siswa tidak valid.',

            'student_ids.*.distinct' => 'Siswa yang sama tidak boleh dipilih lebih dari satu kali.',

            'notes.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'idempotency_key' => 'kunci idempotency',

            'face_verification_attempt_id' => 'hasil verifikasi wajah',

            'student_ids' => 'daftar siswa',

            'student_ids.*' => 'siswa',

            'notes' => 'catatan',
        ];
    }

    /**
     * @return array<int>
     */
    public function studentIds(): array
    {
        $studentIds =
            $this->validated(
                'student_ids',
                [],
            );

        if (! is_array($studentIds)) {
            return [];
        }

        return collect($studentIds)
            ->map(
                static fn (
                    mixed $studentId,
                ): int => (int) $studentId,
            )
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function verificationAttemptId(): int
    {
        return (int) $this->validated(
            'face_verification_attempt_id',
        );
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated(
            'idempotency_key',
        );
    }

    public function notes(): ?string
    {
        $notes =
            $this->validated(
                'notes',
            );

        if (! is_string($notes)) {
            return null;
        }

        $notes = trim($notes);

        return $notes !== ''
            ? $notes
            : null;
    }
}
