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
            'nuptk' => ['nullable', 'string', 'max:40'],
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tahun_masuk' => ['required', 'integer', 'min:1950', 'max:'.((int) now()->year + 1)],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'status_kepegawaian' => ['nullable', 'string', 'max:40'],
        ];
    }
}
