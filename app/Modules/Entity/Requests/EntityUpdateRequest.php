<?php

namespace App\Modules\Entity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(['person', 'company', 'institution'])],
            'region_id' => ['sometimes', 'required', 'uuid', 'exists:regions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe entity harus salah satu dari: person, company, institution.',
            'region_id.exists' => 'Region yang dipilih tidak ditemukan.',
        ];
    }
}