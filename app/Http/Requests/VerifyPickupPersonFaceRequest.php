<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyPickupPersonFaceRequest extends FormRequest
{
    /**
     * Otorisasi role dan sekolah tetap diperiksa
     * di GateFaceVerificationController.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Menyeragamkan beberapa nilai sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('challenge_id')) {
            $payload['challenge_id'] = trim(
                (string) $this->input(
                    'challenge_id',
                ),
            );
        }

        if ($this->has('model_name')) {
            $payload['model_name'] = trim(
                (string) $this->input(
                    'model_name',
                ),
            );
        }

        if ($this->has('model_version')) {
            $modelVersion = trim(
                (string) $this->input(
                    'model_version',
                ),
            );

            $payload['model_version'] =
                $modelVersion !== ''
                    ? $modelVersion
                    : null;
        }

        if ($this->has('capture_method')) {
            $payload['capture_method'] =
                strtolower(
                    trim(
                        (string) $this->input(
                            'capture_method',
                        ),
                    ),
                );
        }

        if ($this->has('liveness_passed')) {
            $payload['liveness_passed'] =
                $this->normalizeBoolean(
                    $this->input(
                        'liveness_passed',
                    ),
                );
        }

        $challengeEvidence = $this->input(
            'challenge_evidence',
        );

        if (is_array($challengeEvidence)) {
            if (
                array_key_exists(
                    'completed_actions',
                    $challengeEvidence,
                )
                && is_array(
                    $challengeEvidence[
                        'completed_actions'
                    ],
                )
            ) {
                $challengeEvidence[
                    'completed_actions'
                ] = collect(
                    $challengeEvidence[
                        'completed_actions'
                    ],
                )
                    ->map(
                        fn (mixed $action): string => strtolower(
                            trim(
                                (string) $action,
                            ),
                        ),
                    )
                    ->values()
                    ->all();
            }

            if (
                array_key_exists(
                    'returned_to_center',
                    $challengeEvidence,
                )
            ) {
                $challengeEvidence[
                    'returned_to_center'
                ] = $this->normalizeBoolean(
                    $challengeEvidence[
                        'returned_to_center'
                    ],
                );
            }

            $payload['challenge_evidence'] =
                $challengeEvidence;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minimumDimension = max(
            1,
            (int) config(
                'biometrics.minimum_embedding_dimension',
                64,
            ),
        );

        $maximumDimension = max(
            $minimumDimension,
            (int) config(
                'biometrics.maximum_embedding_dimension',
                2048,
            ),
        );

        $blinkMinimumMilliseconds = max(
            1,
            (int) config(
                'biometrics.challenge.blink_min_ms',
                60,
            ),
        );

        $blinkMaximumMilliseconds = max(
            $blinkMinimumMilliseconds,
            (int) config(
                'biometrics.challenge.blink_max_ms',
                900,
            ),
        );

        $maximumChallengeDuration = max(
            1000,
            (int) config(
                'biometrics.challenge.maximum_duration_ms',
                30000,
            ),
        );

        return [
            /*
            |--------------------------------------------------------------------------
            | Challenge server
            |--------------------------------------------------------------------------
            */

            'challenge_id' => [
                'bail',
                'required',
                'uuid',
            ],

            'challenge_evidence' => [
                'bail',
                'required',

                /*
                 * Hanya key berikut yang diperbolehkan.
                 */
                'array:completed_actions,blink_duration_ms,maximum_yaw_delta,returned_to_center,duration_ms,sample_count',
            ],

            'challenge_evidence.completed_actions' => [
                'bail',
                'required',
                'array',
                'size:2',
            ],

            'challenge_evidence.completed_actions.*' => [
                'bail',
                'required',
                'string',

                Rule::in([
                    'blink',
                    'turn_head',
                ]),

                'distinct:strict',
            ],

            'challenge_evidence.blink_duration_ms' => [
                'bail',
                'required',
                'integer',

                sprintf(
                    'between:%d,%d',
                    $blinkMinimumMilliseconds,
                    $blinkMaximumMilliseconds,
                ),
            ],

            'challenge_evidence.maximum_yaw_delta' => [
                'bail',
                'required',
                'numeric',
                'between:0,3.2',
            ],

            'challenge_evidence.returned_to_center' => [
                'bail',
                'required',
                'boolean',
            ],

            'challenge_evidence.duration_ms' => [
                'bail',
                'required',
                'integer',

                sprintf(
                    'between:1,%d',
                    $maximumChallengeDuration,
                ),
            ],

            'challenge_evidence.sample_count' => [
                'bail',
                'required',
                'integer',
                'between:1,1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Descriptor wajah
            |--------------------------------------------------------------------------
            */

            'embedding' => [
                'bail',
                'required',
                'array',

                sprintf(
                    'min:%d',
                    $minimumDimension,
                ),

                sprintf(
                    'max:%d',
                    $maximumDimension,
                ),
            ],

            'embedding.*' => [
                'bail',
                'required',
                'numeric',
                'between:-1000,1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Model biometrik
            |--------------------------------------------------------------------------
            */

            'model_name' => [
                'bail',
                'required',
                'string',
                'max:100',
            ],

            'model_version' => [
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Kualitas dan liveness
            |--------------------------------------------------------------------------
            */

            'quality_score' => [
                'bail',
                'required',
                'numeric',
                'between:0,1',
            ],

            'liveness_passed' => [
                'bail',
                'required',
                'boolean',
            ],

            'live_score' => [
                'nullable',
                'numeric',
                'between:0,1',
            ],

            'real_score' => [
                'nullable',
                'numeric',
                'between:0,1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Sumber pengambilan
            |--------------------------------------------------------------------------
            */

            'capture_method' => [
                'bail',
                'required',

                Rule::in([
                    'camera',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Metadata analisis
            |--------------------------------------------------------------------------
            |
            | Metadata boleh disimpan untuk audit, tetapi embedding probe
            | tidak boleh disalin ke dalam metadata.
            |
            */

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
            /*
            |--------------------------------------------------------------------------
            | Challenge
            |--------------------------------------------------------------------------
            */

            'challenge_id.required' => 'Challenge verifikasi belum tersedia.',

            'challenge_id.uuid' => 'ID challenge verifikasi tidak valid.',

            'challenge_evidence.required' => 'Bukti penyelesaian challenge belum tersedia.',

            'challenge_evidence.array' => 'Format bukti challenge tidak valid.',

            'challenge_evidence.completed_actions.required' => 'Daftar challenge yang diselesaikan belum tersedia.',

            'challenge_evidence.completed_actions.array' => 'Format daftar challenge tidak valid.',

            'challenge_evidence.completed_actions.size' => 'Kedua challenge harus diselesaikan.',

            'challenge_evidence.completed_actions.*.required' => 'Jenis challenge tidak boleh kosong.',

            'challenge_evidence.completed_actions.*.in' => 'Jenis challenge yang diselesaikan tidak valid.',

            'challenge_evidence.completed_actions.*.distinct' => 'Challenge yang sama tidak boleh dikirim dua kali.',

            'challenge_evidence.blink_duration_ms.required' => 'Durasi kedipan belum tersedia.',

            'challenge_evidence.blink_duration_ms.integer' => 'Durasi kedipan harus berupa angka dalam milidetik.',

            'challenge_evidence.blink_duration_ms.between' => 'Durasi kedipan berada di luar batas yang diperbolehkan.',

            'challenge_evidence.maximum_yaw_delta.required' => 'Nilai perubahan posisi kepala belum tersedia.',

            'challenge_evidence.maximum_yaw_delta.numeric' => 'Nilai perubahan posisi kepala tidak valid.',

            'challenge_evidence.maximum_yaw_delta.between' => 'Nilai perubahan posisi kepala berada di luar batas.',

            'challenge_evidence.returned_to_center.required' => 'Status kembalinya wajah ke tengah belum tersedia.',

            'challenge_evidence.returned_to_center.boolean' => 'Status posisi tengah wajah tidak valid.',

            'challenge_evidence.duration_ms.required' => 'Durasi challenge belum tersedia.',

            'challenge_evidence.duration_ms.integer' => 'Durasi challenge harus berupa angka dalam milidetik.',

            'challenge_evidence.duration_ms.between' => 'Durasi challenge melewati batas waktu yang diperbolehkan.',

            'challenge_evidence.sample_count.required' => 'Jumlah sampel challenge belum tersedia.',

            'challenge_evidence.sample_count.integer' => 'Jumlah sampel challenge harus berupa bilangan bulat.',

            'challenge_evidence.sample_count.between' => 'Jumlah sampel challenge tidak valid.',

            /*
            |--------------------------------------------------------------------------
            | Embedding
            |--------------------------------------------------------------------------
            */

            'embedding.required' => 'Descriptor wajah belum tersedia.',

            'embedding.array' => 'Format descriptor wajah tidak valid.',

            'embedding.min' => 'Dimensi descriptor wajah terlalu kecil.',

            'embedding.max' => 'Dimensi descriptor wajah terlalu besar.',

            'embedding.*.required' => 'Descriptor wajah memiliki nilai kosong.',

            'embedding.*.numeric' => 'Descriptor wajah mengandung nilai bukan angka.',

            'embedding.*.between' => 'Descriptor wajah mengandung nilai di luar batas.',

            /*
            |--------------------------------------------------------------------------
            | Model
            |--------------------------------------------------------------------------
            */

            'model_name.required' => 'Nama model biometrik wajib tersedia.',

            'model_name.string' => 'Nama model biometrik tidak valid.',

            'model_name.max' => 'Nama model biometrik terlalu panjang.',

            'model_version.string' => 'Versi model biometrik tidak valid.',

            'model_version.max' => 'Versi model biometrik terlalu panjang.',

            /*
            |--------------------------------------------------------------------------
            | Kualitas dan liveness
            |--------------------------------------------------------------------------
            */

            'quality_score.required' => 'Nilai kualitas wajah wajib tersedia.',

            'quality_score.numeric' => 'Nilai kualitas wajah harus berupa angka.',

            'quality_score.between' => 'Nilai kualitas wajah harus berada antara 0 dan 1.',

            'liveness_passed.required' => 'Hasil pemeriksaan liveness wajib tersedia.',

            'liveness_passed.boolean' => 'Hasil pemeriksaan liveness tidak valid.',

            'live_score.numeric' => 'Nilai live harus berupa angka.',

            'live_score.between' => 'Nilai live harus berada antara 0 dan 1.',

            'real_score.numeric' => 'Nilai anti-spoofing harus berupa angka.',

            'real_score.between' => 'Nilai anti-spoofing harus berada antara 0 dan 1.',

            /*
            |--------------------------------------------------------------------------
            | Metode dan metadata
            |--------------------------------------------------------------------------
            */

            'capture_method.required' => 'Metode pengambilan wajah wajib tersedia.',

            'capture_method.in' => 'Verifikasi gerbang harus menggunakan kamera.',

            'metadata.array' => 'Format metadata analisis tidak valid.',

            'metadata.max' => 'Metadata analisis terlalu banyak.',
        ];
    }

    /**
     * Nama field yang lebih mudah dibaca pada pesan bawaan Laravel.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'challenge_id' => 'ID challenge',

            'challenge_evidence' => 'bukti challenge',

            'challenge_evidence.completed_actions' => 'challenge yang diselesaikan',

            'challenge_evidence.blink_duration_ms' => 'durasi kedipan',

            'challenge_evidence.maximum_yaw_delta' => 'perubahan posisi kepala',

            'challenge_evidence.returned_to_center' => 'status posisi tengah',

            'challenge_evidence.duration_ms' => 'durasi challenge',

            'challenge_evidence.sample_count' => 'jumlah sampel challenge',

            'embedding' => 'descriptor wajah',

            'model_name' => 'nama model biometrik',

            'model_version' => 'versi model biometrik',

            'quality_score' => 'kualitas wajah',

            'liveness_passed' => 'hasil liveness',

            'live_score' => 'nilai live',

            'real_score' => 'nilai anti-spoofing',

            'capture_method' => 'metode pengambilan',

            'metadata' => 'metadata analisis',
        ];
    }

    /**
     * Menyeragamkan boolean dari JSON atau form-data.
     */
    private function normalizeBoolean(
        mixed $value,
    ): mixed {
        if (is_bool($value)) {
            return $value;
        }

        if (
            $value === 1
            || $value === '1'
            || $value === 'true'
        ) {
            return true;
        }

        if (
            $value === 0
            || $value === '0'
            || $value === 'false'
        ) {
            return false;
        }

        return $value;
    }
}
