<?php

namespace App\Modules\Entity\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntityCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['required', 'string', Rule::in(['person', 'company', 'institution'])],
            'region_id' => ['required', 'uuid', 'exists:regions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama entity wajib diisi.',
            'type.required' => 'Tipe entity wajib diisi.',
            'type.in' => 'Tipe entity harus salah satu dari: person, company, institution.',
            'region_id.required' => 'Region wajib diisi.',
            'region_id.exists' => 'Region yang dipilih tidak ditemukan.',
        ];
    }
}