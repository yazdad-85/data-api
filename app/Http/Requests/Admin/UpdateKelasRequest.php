<?php

namespace App\Http\Requests\Admin;

use App\Models\Kelas;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('wali_kelas_guru_id') === '') {
            $this->merge(['wali_kelas_guru_id' => null]);
        }

        if ($this->input('tingkat') === '') {
            $this->merge(['tingkat' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lembagaId = $this->user()?->lembaga_id;

        return [
            'tahun_ajaran_id' => [
                'required',
                'uuid',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'nama' => ['required', 'string', 'max:50'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'wali_kelas_guru_id' => [
                'nullable',
                'uuid',
                Rule::exists('guru', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nama = $this->input('nama');
            $tahunAjaranId = $this->input('tahun_ajaran_id');
            $lembagaId = $this->user()?->lembaga_id;
            $kelas = $this->route('kelas');

            if (! is_string($nama) || ! is_string($tahunAjaranId) || ! $lembagaId || ! $kelas instanceof Kelas) {
                return;
            }

            $exists = Kelas::withTrashed()
                ->where('lembaga_id', $lembagaId)
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->where('nama', $nama)
                ->where('id', '!=', $kelas->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('nama', "Kelas dengan nama {$nama} sudah ada pada tahun ajaran ini.");
            }
        });
    }
}
