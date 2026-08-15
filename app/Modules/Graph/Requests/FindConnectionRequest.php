<?php

namespace App\Modules\Graph\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FindConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'target_id' => ['required', 'uuid'],
        ];
    }

    /**
     * Validasi target_id != source_id (dari route parameter {id}) —
     * dicek di sini karena butuh akses ke route parameter, bukan
     * cuma field body/query biasa.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sourceId = $this->route('id');

            if ($this->input('target_id') === $sourceId) {
                $validator->errors()->add(
                    'target_id',
                    'target_id tidak boleh sama dengan entity sumber (source).'
                );
            }
        });
    }
}