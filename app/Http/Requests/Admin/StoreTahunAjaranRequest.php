<?php

namespace App\Http\Requests\Admin;

use App\Models\TahunAjaran;
use App\Support\Master\TahunAjaranNamer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTahunAjaranRequest extends FormRequest
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
        $year = (int) now()->year;

        return [
            'tahun_mulai' => ['required', 'integer', 'min:'.($year - 2), 'max:'.($year + 3)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tahunMulai = $this->input('tahun_mulai');

            if (! is_numeric($tahunMulai)) {
                return;
            }

            $nama = TahunAjaranNamer::fromTahunMulai((int) $tahunMulai);

            $exists = TahunAjaran::query()
                ->where('lembaga_id', $this->user()?->lembaga_id)
                ->where('nama', $nama)
                ->exists();

            if ($exists) {
                $validator->errors()->add('tahun_mulai', "Tahun ajaran {$nama} sudah ada.");
            }
        });
    }
}
