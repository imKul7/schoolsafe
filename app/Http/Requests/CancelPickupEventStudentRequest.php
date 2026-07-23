<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class CancelPickupEventStudentRequest extends FormRequest
{
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

protected function failedAuthorization(): void
{
    $user =
        $this->user();

    if (
        $user instanceof User
        && ! (bool) $user->is_active
    ) {
        throw new AuthorizationException(
            'Akun Anda sedang tidak aktif.',
        );
    }

    if (
        $user instanceof User
        && (
            $user->school_id === null
            || (int) $user->school_id <= 0
        )
    ) {
        throw new AuthorizationException(
            'Akun belum terhubung dengan sekolah.',
        );
    }

    throw new AuthorizationException(
        'Akun tidak memiliki izin mengelola transaksi gerbang.',
    );
}

    protected function prepareForValidation(): void
    {
        $reason =
            $this->input(
                'reason',
            );

        $this->merge([
            'reason' =>
                is_string($reason)
                    ? trim($reason)
                    : $reason,
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'bail',
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' =>
                'Alasan pembatalan siswa wajib diisi.',

            'reason.string' =>
                'Alasan pembatalan siswa tidak valid.',

            'reason.min' =>
                'Alasan pembatalan siswa minimal 5 karakter.',

            'reason.max' =>
                'Alasan pembatalan siswa maksimal 1000 karakter.',
        ];
    }

    public function cancellationReason(): string
    {
        return trim(
            (string) $this->validated(
                'reason',
            ),
        );
    }
}