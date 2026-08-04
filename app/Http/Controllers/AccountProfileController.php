<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AccountProfileController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('profile/show', [
            'profile' => $this->profilePayload($user),
        ]);
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=120,min_height=120,max_width=5000,max_height=5000',
            ],
        ], [
            'photo.required' => 'Pilih foto profil terlebih dahulu.',
            'photo.image' => 'File yang dipilih harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
            'photo.dimensions' => 'Dimensi foto tidak sesuai ketentuan.',
        ]);

        /** @var User $user */
        $user = $request->user();

        $uploadedPhoto = $request->file('photo');

        if ($uploadedPhoto === null) {
            throw ValidationException::withMessages([
                'photo' => 'Foto profil gagal dibaca.',
            ]);
        }

        $newPhotoPath = $uploadedPhoto->store(
            'profile-photos',
            'public',
        );

        if (! is_string($newPhotoPath) || $newPhotoPath === '') {
            throw ValidationException::withMessages([
                'photo' => 'Foto profil gagal disimpan.',
            ]);
        }

        $oldPhotoPath = $user->getAttribute('profile_photo_path');

        $user->setAttribute(
            'profile_photo_path',
            $newPhotoPath,
        );

        $user->save();

        if (
            is_string($oldPhotoPath) &&
            $oldPhotoPath !== '' &&
            $oldPhotoPath !== $newPhotoPath
        ) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return back()->with(
            'success',
            'Foto profil berhasil diperbarui.',
        );
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $photoPath = $user->getAttribute(
            'profile_photo_path',
        );

        if (is_string($photoPath) && $photoPath !== '') {
            Storage::disk('public')->delete($photoPath);
        }

        $user->setAttribute(
            'profile_photo_path',
            null,
        );

        $user->save();

        return back()->with(
            'success',
            'Foto profil berhasil dihapus.',
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     role_label: string,
     *     initials: string,
     *     photo_url: string|null,
     *     email_verified_at: string|null,
     *     created_at: string|null
     * }
     */
    private function profilePayload(User $user): array
    {
        $rawRole = $user->getAttribute('role');

        $role = $rawRole instanceof BackedEnum
            ? (string) $rawRole->value
            : (string) $rawRole;

        $photoPath = $user->getAttribute(
            'profile_photo_path',
        );

        $emailVerifiedAt = $user->getAttribute(
            'email_verified_at',
        );

        $createdAt = $user->getAttribute(
            'created_at',
        );

        $nameParts = preg_split(
            '/\s+/',
            trim((string) $user->name),
        ) ?: [];

        $initials = collect($nameParts)
            ->filter()
            ->take(2)
            ->map(
                static fn (string $part): string => Str::upper(
                    Str::substr($part, 0, 1),
                ),
            )
            ->implode('');

        return [
            'id' => (int) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => $role,
            'role_label' => $this->roleLabel($role),
            'initials' => $initials !== ''
                ? $initials
                : 'SS',
            'photo_url' => is_string($photoPath) &&
                $photoPath !== ''
                    ? Storage::disk('public')->url($photoPath)
                    : null,
            'email_verified_at' => $this->dateValue(
                $emailVerifiedAt,
            ),
            'created_at' => $this->dateValue($createdAt),
        ];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin',
            'school_admin' => 'Admin Sekolah',
            'gate_officer' => 'Petugas Gerbang',
            'teacher' => 'Guru',
            'parent' => 'Orang Tua',
            default => Str::headline($role),
        };
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
