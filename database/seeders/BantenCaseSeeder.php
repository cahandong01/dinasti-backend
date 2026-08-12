<?php

namespace Database\Seeders;

use App\Modules\Entity\Models\Entity;
use App\Modules\Evidence\Models\Evidence;
use App\Modules\Evidence\Models\Source;
use App\Modules\Relationship\Models\Relationship;
use App\Modules\TenantRegion\Models\Region;
use App\Modules\TenantRegion\Models\Tenant;
use Illuminate\Database\Seeder;

class BantenCaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'tenant-banten')->firstOrFail();
        $banten = Region::where('code', '36')->firstOrFail();
        $tangsel = Region::where('code', '36.74')->firstOrFail();

        // --- Entities ---
        $ratuAtut = Entity::create([
            'tenant_id' => $tenant->id,
            'region_id' => $banten->id,
            'type' => 'person',
            'name' => 'Ratu Atut Chosiyah',
        ]);

        $wawan = Entity::create([
            'tenant_id' => $tenant->id,
            'region_id' => $banten->id,
            'type' => 'person',
            'name' => 'Tubagus Chaeri Wardana (Wawan)',
        ]);

        $dinkesBanten = Entity::create([
            'tenant_id' => $tenant->id,
            'region_id' => $banten->id,
            'type' => 'institution',
            'name' => 'Dinas Kesehatan Provinsi Banten',
        ]);

        // --- Source & Evidence ---
        $source = Source::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ratu Atut Didakwa Korupsi Alkes Banten Rp 79,789 Miliar - Liputan6',
            'type' => 'news_article',
            'url' => 'https://www.liputan6.com/news/read/2879432/ratu-atut-didakwa-korupsi-alkes-banten-rp-79789-miliar',
            'reliability' => 'unverified',
            'published_at' => '2017-03-08',
        ]);

        $evidence = Evidence::create([
            'source_id' => $source->id,
            'tenant_id' => $tenant->id,
            'excerpt' => 'Ratu Atut didakwa merugikan negara Rp 79,789 miliar dalam korupsi pengadaan alat kesehatan (alkes) Banten, melakukan pengaturan lelang pada Dinkes Provinsi Banten 2012.',
            'locator' => 'paragraf 1-2',
        ]);

        // --- Relationships ---
        Relationship::create([
            'source_entity_id' => $ratuAtut->id,
            'target_entity_id' => $dinkesBanten->id,
            'evidence_id' => $evidence->id,
            'type' => 'corruption_scheme',
            'valid_from' => '2012-01-01',
        ]);

        Relationship::create([
            'source_entity_id' => $wawan->id,
            'target_entity_id' => $ratuAtut->id,
            'evidence_id' => $evidence->id,
            'type' => 'family_affiliation',
        ]);
    }
}