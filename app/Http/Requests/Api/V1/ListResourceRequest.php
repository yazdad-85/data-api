<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Api\ApiFieldProfiles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates query parameters for `GET /api/v1/{resource}` (design §5).
 * `per_page` is validated loosely; the lister clamps it to the 1..200 range.
 */
class ListResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise textual booleans ("true"/"false") from the query string so the
     * `boolean` rule accepts them alongside 1/0 (design §5).
     */
    protected function prepareForValidation(): void
    {
        foreach (['include_deleted', 'active_only'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([
                    $flag => filter_var($this->query($flag), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->query($flag),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_deleted' => ['sometimes', 'boolean'],
            'active_only' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'nullable', Rule::in(ApiFieldProfiles::ALL)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
