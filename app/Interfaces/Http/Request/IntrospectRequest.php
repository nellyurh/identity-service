<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class IntrospectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:8192'],
        ];
    }
}
