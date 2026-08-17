<?php

namespace App\Modules\TenantRegion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'has_role:...' di route
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:TENANT_ADMIN,RESEARCHER,LEGAL_REVIEWER'],
        ];
    }

    /**
     * Role hierarchy constraint: TENANT_ADMIN TIDAK BOLEH undang orang
     * jadi TENANT_ADMIN. Query manual (bukan hasRole()) karena
     * hasRole() bawaan Spatie punya bug tidak include role global
     * saat team scope aktif (lihat HasRole.php middleware).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inviterIsSuperAdmin = $this->user()->roles()
                ->whereNull('roles.tenant_id')
                ->where('roles.name', 'SUPER_ADMIN')
                ->exists();

            if ($this->input('role') === 'TENANT_ADMIN' && ! $inviterIsSuperAdmin) {
                $validator->errors()->add(
                    'role',
                    'Cuma SUPER_ADMIN yang boleh mengundang orang sebagai TENANT_ADMIN.'
                );
            }
        });
    }
}