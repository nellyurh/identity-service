<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'owner_type' => ['required', 'string', 'in:user,service_account'],
            'owner_id' => ['required', 'string', 'ulid'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'regex:/^[a-z][a-z0-9_]*[.:][a-z][a-z0-9_*]*$/'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
