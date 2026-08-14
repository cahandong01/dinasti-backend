<?php

namespace App\Modules\Graph\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NetworkExploreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
    }

    public function rules(): array
    {
        return [
            'depth' => ['sometimes', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'depth.max' => 'Depth maksimal 4 hop (D1) — traversal lebih dari itu ditolak demi performa.',
        ];
    }

    public function depthOrDefault(): int
    {
        return (int) $this->validated('depth', 2);
    }
}