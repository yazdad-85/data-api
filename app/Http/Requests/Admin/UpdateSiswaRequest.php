<?php

namespace App\Http\Requests\Admin;

use App\Models\Siswa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['nis', 'nisn', 'jenis_kelamin', 'tempat_lahir', 'email', 'telepon', 'status_keluarga', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'telepon_wali'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // kelas_id / tahun_ajaran_id are intentionally excluded here: changing a
        // siswa's kelas must go through SiswaLifecycleService (tempatkan/pindah)
        // so the siswa_penempatan snapshot stays in sync. Ordinary edit cannot
        // touch enrollment.
        // NIS hanya wajib untuk siswa yang sudah ditempatkan ke kelas (aktif).
        // Calon murid (kelas_id masih kosong) boleh belum punya NIS.
        $siswa = $this->route('siswa');
        $nisRequired = $siswa instanceof Siswa && $siswa->kelas_id !== null;

        return [
            'nis' => [Rule::requiredIf($nisRequired), 'nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'status_keluarga' => ['nullable', Rule::in(['Yatim', 'Piatu', 'Yatim Piatu', 'Anak Guru, Staff, dan Karyawan'])],
            'nama_ayah' => ['nullable', 'string', 'max:150'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:100'],
            'nama_ibu' => ['nullable', 'string', 'max:150'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:100'],
            'nama_wali' => ['nullable', 'string', 'max:150'],
            'telepon_wali' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lembagaId = $this->user()?->lembaga_id;
            $nis = $this->input('nis');
            $nisn = $this->input('nisn');
            $siswa = $this->route('siswa');

            if (! $lembagaId || ! $siswa instanceof Siswa) {
                return;
            }

            if (is_string($nis)) {
                $exists = Siswa::withTrashed()
                    ->where('lembaga_id', $lembagaId)
                    ->where('nis', $nis)
                    ->where('id', '!=', $siswa->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nis', "NIS {$nis} sudah digunakan di lembaga ini.");
                }
            }

            if (is_string($nisn) && $nisn !== '') {
                $exists = Siswa::withTrashed()
                    ->where('lembaga_id', $lembagaId)
                    ->where('nisn', $nisn)
                    ->where('id', '!=', $siswa->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nisn', "NISN {$nisn} sudah digunakan di lembaga ini.");
                }
            }
        });
    }
}
