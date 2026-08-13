<?php

namespace App\Modules\Relationship\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RelationshipCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'source_entity_id' => ['required', 'uuid', 'exists:entities,id', 'different:target_entity_id'],
            'target_entity_id' => ['required', 'uuid', 'exists:entities,id'],
            'evidence_id' => ['required', 'uuid', 'exists:evidences,id'],
            'type' => ['required', 'string', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_entity_id.exists' => 'Entity sumber tidak ditemukan.',
            'source_entity_id.different' => 'Entity sumber dan target tidak boleh sama (tidak bisa relasi ke diri sendiri).',
            'target_entity_id.exists' => 'Entity target tidak ditemukan.',
            'evidence_id.required' => 'Evidence wajib diisi — tidak ada relationship tanpa bukti (D6/D7).',
            'evidence_id.exists' => 'Evidence tidak ditemukan.',
        ];
    }
}