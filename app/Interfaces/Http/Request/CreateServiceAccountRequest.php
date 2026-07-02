<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class CreateServiceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9-]*$/'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'regex:/^[a-z][a-z0-9_]*[.:][a-z][a-z0-9_*]*$/'],
        ];
    }
}
