<?php

namespace App\Modules\Dispute\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Endpoint PUBLIK, sengaja tanpa login (semangat Hak Jawab)
    }

    public function rules(): array
    {
        return [
            'disputable_type' => ['required', 'string', 'in:entity,relationship'],
            'disputable_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'disputed_part' => ['nullable', 'string', 'max:2000'],
            'supporting_evidence' => ['required', 'string', 'max:5000'],
            'response_content' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'disputable_type.in' => 'Tipe data yang disengketakan harus "entity" atau "relationship".',
            'supporting_evidence.required' => 'Data pendukung wajib diisi (Pedoman Hak Jawab Dewan Pers).',
            'response_content.required' => 'Isi sanggahan/tanggapan wajib diisi.',
        ];
    }
}