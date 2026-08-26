<?php

namespace App\Http\Requests\Admin;

use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpmbDistribusiRequest extends FormRequest
{
    public const MAX_ROWS = 200;

    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true
            && $this->user()?->lembaga_id !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['kelas_id', 'mulai_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lembagaId = $this->user()?->lembaga_id;

        return [
            'kelas_id' => [
                'required',
                'uuid',
                Rule::exists('kelas', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'siswa_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'siswa_ids.*' => [
                'required',
                'uuid',
                Rule::exists('siswa', 'id')->where(fn ($query) => $query
                    ->where('lembaga_id', $lembagaId)
                    ->where('status_siswa', SiswaStatus::CALON)),
            ],
            'mulai_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'siswa_ids.required' => 'Pilih minimal satu calon murid.',
            'siswa_ids.max' => 'Batch maksimal '.self::MAX_ROWS.' siswa sekaligus.',
            'siswa_ids.*.exists' => 'Salah satu siswa yang dipilih tidak valid atau bukan calon murid.',
            'kelas_id.required' => 'Kelas tujuan wajib dipilih.',
        ];
    }
}
