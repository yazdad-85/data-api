<?php

namespace App\Http\Requests\Admin;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['nis', 'nisn', 'kelas_id', 'tahun_ajaran_id', 'jenis_kelamin', 'tempat_lahir', 'email', 'telepon', 'status_keluarga', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'telepon_wali', 'asal_lembaga', 'diterima_tanggal'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if (! $this->filled('kelas_id')) {
            $merge['kelas_id'] = null;
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
        $lembagaId = $this->user()?->lembaga_id;

        return [
            // NIS hanya wajib kalau siswa langsung ditempatkan ke kelas (aktif).
            // Calon murid (SPMB, kelas_id kosong) belum resmi diterima jadi NIS
            // boleh menyusul.
            'nis' => [Rule::requiredIf(fn () => $this->filled('kelas_id')), 'nullable', 'string', 'max:30'],
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
            'jenis_masuk' => ['nullable', Rule::in(['siswa_baru', 'mutasi_masuk'])],
            'asal_lembaga' => ['nullable', 'string', 'max:150'],
            'diterima_tanggal' => ['nullable', 'date'],
            'kelas_id' => [
                'nullable',
                'uuid',
                Rule::exists('kelas', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'tahun_ajaran_id' => [
                Rule::requiredIf(fn () => $this->filled('kelas_id')),
                'nullable',
                'uuid',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lembagaId = $this->user()?->lembaga_id;
            $nis = $this->input('nis');
            $nisn = $this->input('nisn');
            $kelasId = $this->input('kelas_id');
            $jenisMasuk = $this->input('jenis_masuk') ?: 'siswa_baru';

            if (! $lembagaId) {
                return;
            }

            if (is_string($nis)) {
                $exists = Siswa::withTrashed()
                    ->where('lembaga_id', $lembagaId)
                    ->where('nis', $nis)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nis', "NIS {$nis} sudah digunakan di lembaga ini.");
                }
            }

            if (is_string($nisn) && $nisn !== '') {
                $exists = Siswa::withTrashed()
                    ->where('lembaga_id', $lembagaId)
                    ->where('nisn', $nisn)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('nisn', "NISN {$nisn} sudah digunakan di lembaga ini.");
                }
            }

            if ($jenisMasuk === 'mutasi_masuk') {
                if (! $this->filled('asal_lembaga')) {
                    $validator->errors()->add('asal_lembaga', 'Asal lembaga wajib diisi untuk mutasi masuk.');
                }

                if (! $this->filled('diterima_tanggal')) {
                    $validator->errors()->add('diterima_tanggal', 'Tanggal diterima wajib diisi untuk mutasi masuk.');
                }
            }

            if (! is_string($kelasId) || $kelasId === '') {
                return;
            }

            $tahunAjaranId = $this->input('tahun_ajaran_id');
            $kelas = Kelas::query()
                ->where('lembaga_id', $lembagaId)
                ->find($kelasId);

            if (! $kelas instanceof Kelas || ! is_string($tahunAjaranId)) {
                return;
            }

            if (! hash_equals((string) $kelas->tahun_ajaran_id, (string) $tahunAjaranId)) {
                $validator->errors()->add(
                    'tahun_ajaran_id',
                    'Tahun ajaran harus sama dengan tahun ajaran kelas yang dipilih.',
                );
            }
        });
    }
}
