<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportSiswaCalonRequest extends FormRequest
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
        $lembagaId = $this->user()?->lembaga_id;

        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'tahun_ajaran_id' => [
                'nullable',
                'uuid',
                Rule::exists('tahun_ajaran', 'id')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
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
