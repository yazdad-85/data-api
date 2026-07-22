<?php

namespace App\Http\Requests\Admin;

use App\Support\Master\SiswaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiswaLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminLembaga() === true
            && $this->user()?->lembaga_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['kelas_id', 'mulai_at', 'status_at', 'alasan', 'asal', 'tujuan', 'status'] as $field) {
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
        $lembagaId = $this->user()?->lembaga_id;

        $kelasRule = [
            'required',
            'uuid',
            Rule::exists('kelas', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
        ];

        return match ($this->route()?->getName()) {
            'admin.siswa.lifecycle.tempatkan',
            'admin.siswa.lifecycle.pindah' => [
                'kelas_id' => $kelasRule,
                'mulai_at' => ['nullable', 'date'],
            ],
            'admin.siswa.lifecycle.set_status' => [
                'status' => ['required', Rule::in([SiswaStatus::CALON, SiswaStatus::MUTASI_MASUK])],
                'status_at' => ['nullable', 'date'],
                'alasan' => ['nullable', 'string', 'max:255'],
                'asal' => ['nullable', 'string', 'max:150'],
                'tujuan' => ['nullable', 'string', 'max:150'],
            ],
            default => [ // mutasi_keluar & luluskan
                'status_at' => ['nullable', 'date'],
                'alasan' => ['nullable', 'string', 'max:255'],
                'asal' => ['nullable', 'string', 'max:150'],
                'tujuan' => ['nullable', 'string', 'max:150'],
            ],
        };
    }

    /**
     * Metadata lifecycle (alasan/asal/tujuan/status_at) untuk diteruskan ke service.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $meta = [];

        foreach (['alasan', 'asal', 'tujuan'] as $field) {
            if ($this->has($field)) {
                $meta[$field] = $this->input($field);
            }
        }

        if ($this->filled('status_at')) {
            $meta['status_at'] = $this->input('status_at');
        }

        return $meta;
    }
}
