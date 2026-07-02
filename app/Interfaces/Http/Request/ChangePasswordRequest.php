<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:4096'],
            'new_password' => ['required', 'string', 'min:8', 'max:4096', 'different:current_password'],
        ];
    }
}
