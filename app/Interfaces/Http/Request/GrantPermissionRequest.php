<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class GrantPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', 'max:128', 'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/'],
        ];
    }
}
