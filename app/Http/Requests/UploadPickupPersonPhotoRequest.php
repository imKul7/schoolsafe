<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPickupPersonPhotoRequest extends FormRequest
{
    /**
     * Otorisasi utama dilakukan di controller agar konsisten
     * dengan permission modul penjemput.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' =>
                'Pilih foto penjemput terlebih dahulu.',

            'photo.file' =>
                'Foto yang dipilih tidak valid.',

            'photo.image' =>
                'File harus berupa gambar.',

            'photo.mimes' =>
                'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',

            'photo.max' =>
                'Ukuran foto maksimal 5 MB.',

            'photo.dimensions' =>
                'Ukuran foto minimal 200 × 200 piksel dan maksimal 6000 × 6000 piksel.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => 'foto penjemput',
        ];
    }
}