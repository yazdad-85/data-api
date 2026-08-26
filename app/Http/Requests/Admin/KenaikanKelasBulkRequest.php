<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KenaikanKelasBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true
            && $this->user()?->lembaga_id !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('efektif_at') === '') {
            $this->merge(['efektif_at' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lembagaId = $this->user()?->lembaga_id;

        $tahunAjaranExists = fn () => Rule::exists('tahun_ajaran', 'id')
            ->where(fn ($query) => $query->where('lembaga_id', $lembagaId));

        return [
            'tahun_asal_id' => ['required', 'uuid', $tahunAjaranExists()],
            'tahun_tujuan_id' => ['required', 'uuid', 'different:tahun_asal_id', $tahunAjaranExists()],
            'efektif_at' => ['nullable', 'date'],
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.kelas_asal_id' => [
                'required',
                'uuid',
                Rule::exists('kelas', 'id')->where(fn ($query) => $query
                    ->where('lembaga_id', $lembagaId)
                    ->where('tahun_ajaran_id', $this->input('tahun_asal_id'))),
            ],
            'mappings.*.kelas_tujuan_id' => [
                'required',
                'uuid',
                Rule::exists('kelas', 'id')->where(fn ($query) => $query
                    ->where('lembaga_id', $lembagaId)
                    ->where('tahun_ajaran_id', $this->input('tahun_tujuan_id'))),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mappings = $this->input('mappings');
            if (! is_array($mappings)) {
                return;
            }

            $seen = [];
            foreach ($mappings as $index => $mapping) {
                $kelasAsalId = is_array($mapping) ? ($mapping['kelas_asal_id'] ?? null) : null;
                if (! is_string($kelasAsalId) || $kelasAsalId === '') {
                    continue;
                }

                if (isset($seen[$kelasAsalId])) {
                    $validator->errors()->add(
                        "mappings.{$index}.kelas_asal_id",
                        'Kelas asal tidak boleh dipetakan lebih dari satu kali.',
                    );

                    continue;
                }

                $seen[$kelasAsalId] = true;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mappings.required' => 'Pilih minimal satu kelas untuk diproses.',
            'tahun_tujuan_id.different' => 'Tahun ajaran tujuan harus berbeda dari tahun ajaran asal.',
        ];
    }
}
