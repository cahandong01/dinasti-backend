<?php

namespace App\Modules\Entity\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntitySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi akses tenant sudah ditangani middleware tenant.context
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['sometimes', 'string', 'in:person,company,institution'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Kata kunci pencarian (q) wajib diisi.',
            'q.min' => 'Kata kunci pencarian minimal 2 karakter.',
        ];
    }
}