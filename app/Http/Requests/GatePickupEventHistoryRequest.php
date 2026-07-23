<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PickupEvent;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GatePickupEventHistoryRequest extends FormRequest
{
    private const ALLOWED_PER_PAGE = [
        10,
        15,
        25,
        50,
    ];

    public function authorize(): bool
    {
        $user =
            $this->user();

        return (
            $user instanceof User
            && (bool) $user->is_active
            && (int) $user->school_id > 0
            && $user->hasRole(
                User::ROLE_SCHOOL_ADMIN,
                User::ROLE_GATE_OFFICER,
            )
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' =>
                $this->nullableTrimmedString(
                    $this->input(
                        'date_from',
                    ),
                ),

            'date_to' =>
                $this->nullableTrimmedString(
                    $this->input(
                        'date_to',
                    ),
                ),

            'status' =>
                $this->nullableTrimmedString(
                    $this->input(
                        'status',
                    ),
                ),

            'verification_method' =>
                $this->nullableTrimmedString(
                    $this->input(
                        'verification_method',
                    ),
                ),

            'search' =>
                $this->nullableTrimmedString(
                    $this->input(
                        'search',
                    ),
                ),

            'confirmed_by_user_id' =>
                $this->normalizeInteger(
                    $this->input(
                        'confirmed_by_user_id',
                    ),
                ),

            'per_page' =>
                $this->normalizeInteger(
                    $this->input(
                        'per_page',
                    ),
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in(
                    PickupEvent::STATUSES,
                ),
            ],

            'verification_method' => [
                'nullable',
                'string',
                Rule::in(
                    PickupEvent::VERIFICATION_METHODS,
                ),
            ],

            'confirmed_by_user_id' => [
                'nullable',
                'integer',

                Rule::exists(
                    'users',
                    'id',
                )->where(
                    function (
                        QueryBuilder $query,
                    ): void {
                        $user =
                            $this->user();

                        $schoolId =
                            $user instanceof User
                                ? (int) $user->school_id
                                : 0;

                        $query
                            ->where(
                                'school_id',
                                $schoolId,
                            )
                            ->where(
                                'is_active',
                                true,
                            )
                            ->whereIn(
                                'role',
                                [
                                    User::ROLE_SCHOOL_ADMIN,
                                    User::ROLE_GATE_OFFICER,
                                ],
                            );
                    },
                ),
            ],

            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in(
                    self::ALLOWED_PER_PAGE,
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format' =>
                'Tanggal awal harus menggunakan format YYYY-MM-DD.',

            'date_to.date_format' =>
                'Tanggal akhir harus menggunakan format YYYY-MM-DD.',

            'date_to.after_or_equal' =>
                'Tanggal akhir tidak boleh sebelum tanggal awal.',

            'status.in' =>
                'Status transaksi tidak valid.',

            'verification_method.in' =>
                'Metode verifikasi tidak valid.',

            'confirmed_by_user_id.integer' =>
                'Petugas yang dipilih tidak valid.',

            'search.max' =>
                'Kata pencarian maksimal 100 karakter.',

            'per_page.in' =>
                'Jumlah data per halaman tidak valid.',

            'confirmed_by_user_id.integer' =>
                'Petugas konfirmasi tidak valid.',

            'confirmed_by_user_id.exists' =>
                'Petugas konfirmasi tidak ditemukan pada sekolah Anda.',
        ];
    }

    public function dateFrom(): ?string
    {
        return $this->validatedString(
            'date_from',
        );
    }

    public function dateTo(): ?string
    {
        return $this->validatedString(
            'date_to',
        );
    }

    public function status(): ?string
    {
        return $this->validatedString(
            'status',
        );
    }

    public function verificationMethod(): ?string
    {
        return $this->validatedString(
            'verification_method',
        );
    }

    public function searchTerm(): ?string
    {
        return $this->validatedString(
            'search',
        );
    }

    public function confirmedByUserId(): ?int
    {
        $value =
            $this->validated(
                'confirmed_by_user_id',
            );

        return is_numeric($value)
            ? (int) $value
            : null;
    }

    public function perPage(): int
    {
        $value =
            $this->validated(
                'per_page',
                15,
            );

        $perPage =
            is_numeric($value)
                ? (int) $value
                : 15;

        return in_array(
            $perPage,
            self::ALLOWED_PER_PAGE,
            true,
        )
            ? $perPage
            : 15;
    }

    private function validatedString(
        string $key,
    ): ?string {
        $value =
            $this->validated(
                $key,
            );

        if (! is_string($value)) {
            return null;
        }

        $value =
            trim($value);

        return $value !== ''
            ? $value
            : null;
    }

    private function nullableTrimmedString(
        mixed $value,
    ): ?string {
        if (
            ! is_string($value)
            && ! is_numeric($value)
        ) {
            return null;
        }

        $value =
            trim(
                (string) $value,
            );

        return $value !== ''
            ? $value
            : null;
    }

    private function normalizeInteger(
        mixed $value,
    ): mixed {
        if (
            is_int($value)
            || is_float($value)
        ) {
            return (int) $value;
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

        return $value === ''
            ? null
            : $value;
    }
}