<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for `GET /api/v1/{resource}/sync`.
 */
class SyncResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'string'],
            'cursor' => ['nullable', 'string'],
            'watermark' => ['nullable', 'string'],
            'fields' => ['nullable', 'string', 'in:minimal,academic,contact'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
