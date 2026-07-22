<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KenaikanKelasRequest extends FormRequest
{
    public const MAX_ROWS = 200;

    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true
            && $this->user()?->lembaga_id !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['tahun_tujuan_id', 'kelas_tujuan_default_id', 'efektif_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $rows = $this->input('rows');
        if (is_array($rows)) {
            $normalized = [];
            foreach ($rows as $key => $row) {
                if (! is_array($row)) {
                    $normalized[$key] = $row;

                    continue;
                }

                if (($row['kelas_tujuan_id'] ?? null) === '') {
                    $row['kelas_tujuan_id'] = null;
                }

                $normalized[$key] = $row;
            }

            $this->merge(['rows' => $normalized]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lembagaId = $this->user()?->lembaga_id;

        $kelasExists = Rule::exists('kelas', 'id')
            ->where(fn ($query) => $query->where('lembaga_id', $lembagaId));

        return [
            'tahun_tujuan_id' => [
                'nullable',
                'uuid',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'kelas_tujuan_default_id' => ['nullable', 'uuid', $kelasExists],
            'efektif_at' => ['nullable', 'date'],
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*.siswa_id' => ['required', 'uuid'],
            'rows.*.aksi' => ['required', Rule::in(['naik', 'tinggal', 'lulus', 'mutasi_keluar'])],
            'rows.*.kelas_tujuan_id' => ['nullable', 'uuid', $kelasExists],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'Tidak ada siswa yang dapat diproses.',
            'rows.max' => 'Batch maksimal '.self::MAX_ROWS.' siswa sekaligus.',
            'rows.*.siswa_id.required' => 'Data siswa tidak lengkap.',
            'rows.*.aksi.in' => 'Aksi yang dipilih tidak valid.',
        ];
    }
}
