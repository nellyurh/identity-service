<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/** GET /identity/users?email=|username= — exactly one lookup key, validated here. */
final class UserLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required_without:username', 'prohibits:username', 'string', 'email', 'max:255'],
            'username' => ['required_without:email', 'string', 'max:32'],
        ];
    }
}
