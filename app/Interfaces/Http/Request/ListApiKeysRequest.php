<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ListApiKeysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'owner_type' => ['required', 'string', 'in:user,service_account'],
            'owner_id' => ['required', 'string', 'ulid'],
        ];
    }
}
