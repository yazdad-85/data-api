<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nik' => ['nullable', 'string', 'max:30'],
            'peg_id' => ['nullable', 'string', 'max:40'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tahun_masuk' => ['required', 'integer', 'min:1950', 'max:'.((int) now()->year + 1)],
            'pendidikan_terakhir' => ['nullable', Rule::in(['SMP', 'SMA', 'S1', 'S2', 'S3'])],
            'instansi_pendidikan' => ['nullable', 'string', 'max:150'],
            'jurusan' => ['nullable', 'string', 'max:100'],
            'status_sertifikasi' => ['nullable', Rule::in(['Sudah', 'Belum'])],
            'status_inpasing' => ['nullable', Rule::in(['Sudah', 'Belum'])],
            'mapel_sertifikasi' => ['nullable', 'string', 'max:100'],
            'status_menikah' => ['nullable', Rule::in(['Sudah Menikah', 'Belum Menikah'])],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'status_kepegawaian' => ['nullable', 'string', 'max:40'],
        ];
    }
}
