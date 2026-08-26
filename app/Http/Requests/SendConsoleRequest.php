<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendConsoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => 'required|string',
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE',
            'query_params' => 'nullable|string',
            'body' => 'nullable|string',
            'base_url' => 'sometimes|string',
            'service' => 'sometimes|string|in:iaaas,ibaas',
        ];
    }
}
