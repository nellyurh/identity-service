<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
