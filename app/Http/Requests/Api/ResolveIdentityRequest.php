<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIdentityRequest extends FormRequest
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
            'tenant_id' => ['required', 'integer', 'exists:organizations,id'],
            'anon_id' => [
                'required',
                'string',
                Rule::exists('anon_identities', 'id')->where('organization_id', $this->input('tenant_id')),
            ],
            'email' => ['required', 'email'],
        ];
    }
}
