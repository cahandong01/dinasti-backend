<?php

namespace App\Modules\Relationship\Services;

use App\Modules\Relationship\Models\Relationship;
use Illuminate\Validation\ValidationException;

class RelationshipUpdateService
{
    /**
     * Edit relationship HANYA boleh kalau statusnya 'draft' atau
     * 'needs_revision' — sama pola kayak EntityUpdateService (D7).
     */
    private const STATUS_BOLEH_EDIT = ['draft', 'needs_revision'];

    public function update(Relationship $relationship, array $data): Relationship
    {
        if (! in_array($relationship->status, self::STATUS_BOLEH_EDIT, true)) {
            throw ValidationException::withMessages([
                'status' => "Relationship dengan status '{$relationship->status}' tidak bisa diedit langsung. Gunakan alur review/revisi yang sesuai.",
            ]);
        }

        $relationship->update($data);

        return $relationship->refresh();
    }
}