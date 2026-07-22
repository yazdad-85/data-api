<?php

namespace App\Http\Requests\Admin;

use App\Models\Lembaga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLembagaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Lembaga::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'niy_kode' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/', Rule::unique('lembaga', 'niy_kode')],
            'nama' => ['required', 'string', 'max:150'],
            'jenis' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kota' => ['nullable', 'string', 'max:100'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }
}
