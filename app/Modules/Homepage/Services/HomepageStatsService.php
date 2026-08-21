<?php

namespace App\Modules\Homepage\Services;

use App\Modules\Entity\Models\Entity;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use Illuminate\Support\Facades\DB;

class HomepageStatsService
{
    /**
     * Statistik agregat homepage — endpoint PUBLIK.
     *
     * entities/relationships: filter status='published' EKSPLISIT di
     * kode, bukan cuma ngandelin RLS diam-diam — biar method ini tetap
     * BENAR walau suatu saat kepanggil dari konteks yang keliru (misal
     * ada tenant context aktif), bukan cuma "kebetulan benar" karena
     * route-nya publik.
     *
     * sources: dihitung pakai EXISTS yang MENIRU PERSIS logic RLS
     * carve-out evidence chain (lihat migration
     * add_published_carveout_to_evidence_chain) — alasan sama, biar
     * hitungannya tetap presisi ("source yang dirujuk evidence dari
     * data published") regardless konteks pemanggil, bukan cuma
     * ngandelin session RLS yang kebetulan kosong.
     */
    public function getStats(): array
    {
        return [
            'entities' => Entity::where('status', 'published')->count(),
            'relationships' => Relationship::where('status', 'published')->count(),
            'sources' => $this->countPublicSources(),
            // Asumsi: "wilayah" di homepage merujuk level provinsi
            // (konsisten "38" ~ jumlah provinsi Indonesia). Kalau
            // maksudnya semua level (kab/kota dst), tinggal hapus
            // ->where('level', 'province') ini.
            'regions' => Region::where('level', 'province')->count(),
        ];
    }

        /**
     * Cari entity published dengan jumlah relationship published
     * TERBANYAK — kandidat terbaik buat cuplikan graph homepage
     * (paling "ramai", paling representatif).
     */
    public function findMostConnectedPublishedEntityId(): ?string
    {
        $baris = DB::table('entities')
            ->leftJoin('relationships as r_source', function ($join) {
                $join->on('r_source.source_entity_id', '=', 'entities.id')
                    ->where('r_source.status', 'published');
            })
            ->leftJoin('relationships as r_target', function ($join) {
                $join->on('r_target.target_entity_id', '=', 'entities.id')
                    ->where('r_target.status', 'published');
            })
            ->where('entities.status', 'published')
            ->select('entities.id')
            ->selectRaw('COUNT(DISTINCT r_source.id) + COUNT(DISTINCT r_target.id) AS jumlah_koneksi')
            ->groupBy('entities.id')
            ->orderByDesc('jumlah_koneksi')
            ->first();

        return $baris?->id;
    }

    private function countPublicSources(): int
    {
        return DB::table('sources')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('evidences')
                    ->whereColumn('evidences.source_id', 'sources.id')
                    ->where(function ($q) {
                        $q->whereExists(function ($q2) {
                            $q2->select(DB::raw(1))
                                ->from('entity_attributes')
                                ->join('entities', 'entities.id', '=', 'entity_attributes.entity_id')
                                ->whereColumn('entity_attributes.evidence_id', 'evidences.id')
                                ->where('entities.status', 'published');
                        })->orWhereExists(function ($q2) {
                            $q2->select(DB::raw(1))
                                ->from('relationships')
                                ->whereColumn('relationships.evidence_id', 'evidences.id')
                                ->where('relationships.status', 'published');
                        });
                    });
            })
            ->count();
    }
}