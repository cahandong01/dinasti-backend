<?php

namespace App\Modules\Relationship\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RelationshipUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', 'max:100'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}