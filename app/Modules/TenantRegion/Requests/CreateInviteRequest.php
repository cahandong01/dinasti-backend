<?php

namespace App\Modules\TenantRegion\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi role sudah ditangani middleware 'role:...' di route
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
     * jadi TENANT_ADMIN (role setara) — cegah "admin bayangan" tanpa
     * sepengetahuan SUPER_ADMIN. Cuma SUPER_ADMIN yang boleh assign
     * role TENANT_ADMIN.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $inviterIsSuperAdmin = $this->user()->hasRole('SUPER_ADMIN');

            if ($this->input('role') === 'TENANT_ADMIN' && ! $inviterIsSuperAdmin) {
                $validator->errors()->add(
                    'role',
                    'Cuma SUPER_ADMIN yang boleh mengundang orang sebagai TENANT_ADMIN.'
                );
            }
        });
    }
}