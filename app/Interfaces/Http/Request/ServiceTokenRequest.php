<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ServiceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'grant_type' => ['sometimes', 'string', 'in:client_credentials'],
            'client_id' => ['required', 'string'],
            'client_secret' => ['required', 'string'],
        ];
    }
}
