<?php

namespace App\Modules\TenantRegion\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Belum login — memang tujuannya bikin akun baru
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}