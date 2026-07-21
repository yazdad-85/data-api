<?php

namespace App\Http\Requests\Admin;

use App\Support\Api\ApiClientScopes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('apiClient'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiClientScopes::all())],
            'field_profile' => ['required', Rule::in(['minimal', 'academic', 'contact'])],
        ];
    }
}
