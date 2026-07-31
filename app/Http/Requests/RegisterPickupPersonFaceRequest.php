<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPickupPersonFaceRequest extends FormRequest
{
    /**
     * Otorisasi dilakukan di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minimumDimension = (int) config(
            'biometrics.minimum_embedding_dimension',
            64,
        );

        $maximumDimension = (int) config(
            'biometrics.maximum_embedding_dimension',
            2048,
        );

        return [
            'embedding' => [
                'required',
                'array',
                "min:{$minimumDimension}",
                "max:{$maximumDimension}",
            ],

            'embedding.*' => [
                'required',
                'numeric',
                'between:-1000,1000',
            ],

            'model_name' => [
                'required',
                'string',
                'max:100',
            ],

            'model_version' => [
                'required',
                'string',
                'max:50',
            ],

            'quality_score' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'liveness_passed' => [
                'required',
                'boolean',
            ],

            'capture_method' => [
                'required',
                Rule::in([
                    'camera',
                    'upload',
                ]),
            ],

            'consent_confirmed' => [
                'required',
                'accepted',
            ],

            'metadata' => [
                'nullable',
                'array',
                'max:30',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'embedding.required' => 'Data biometrik wajah belum tersedia.',

            'embedding.array' => 'Format data biometrik wajah tidak valid.',

            'embedding.min' => 'Dimensi data biometrik terlalu kecil.',

            'embedding.max' => 'Dimensi data biometrik terlalu besar.',

            'embedding.*.numeric' => 'Salah satu nilai data biometrik tidak valid.',

            'model_name.required' => 'Nama model pengenal wajah wajib tersedia.',

            'model_version.required' => 'Versi model pengenal wajah wajib tersedia.',

            'quality_score.required' => 'Nilai kualitas foto wajah wajib tersedia.',

            'quality_score.between' => 'Nilai kualitas wajah harus berada antara 0 dan 1.',

            'liveness_passed.required' => 'Hasil pemeriksaan liveness wajib tersedia.',

            'capture_method.required' => 'Metode pengambilan foto wajib tersedia.',

            'capture_method.in' => 'Metode pengambilan foto tidak valid.',

            'consent_confirmed.accepted' => 'Persetujuan penggunaan data biometrik harus diberikan.',
        ];
    }
}
