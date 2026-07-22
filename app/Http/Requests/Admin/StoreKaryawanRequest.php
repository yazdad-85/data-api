<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKaryawanRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tahun_masuk' => ['required', 'integer', 'min:1950', 'max:'.((int) now()->year + 1)],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
        ];
    }
}
