<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportGuruRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel untuk diimport.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
