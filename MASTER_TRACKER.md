# PETA GURITA DINASTI — MASTER TRACKER
Living document — diupdate Claude tiap ada progress baru.
Root project: D:\dinasti\dinasti-backend
Referensi wajib: CONVENTIONS.md, DECISIONS.md (satu folder ini)

---

## STATUS RINGKAS

**Fase sekarang:** Phase 1 — MVP Foundation
**Sedang dikerjakan:** Auth & RBAC (Sanctum sudah, RBAC belum)
**Stack terkonfirmasi:** Laravel 13.24.0, PostgreSQL 18 lokal, Redis (Predis), Pest PHP

---

## SELESAI ✅

### Database — 8 tabel fondasi (semua migrated & PASSED testing)
- [x] `regions` — hierarki self-reference (parent_id), language_code default 'id'
- [x] `tenants` — tanpa region_id (tenant & region dimensi terpisah)
- [x] `tenant_region_access` — pivot many-to-many, default access_level 'read_only' (DENY-by-default)
- [x] `entities` — tabel generic person/company/institution via kolom type, tenant_id+region_id wajib, status default 'draft'
- [x] `sources` — nama, type, url, reliability default 'unverified', published_at
- [x] `evidences` — excerpt+locator, FK ke source_id
- [x] `entity_attributes` — bitemporal (valid_from/valid_until), evidence_id WAJIB, constraint EXCLUDE USING gist (btree_gist)
- [x] `relationships` — source_entity_id & target_entity_id, type, evidence_id WAJIB, bitemporal + EXCLUDE USING gist sama seperti entity_attributes

### Models (Eloquent, app/Modules/{Modul}/Models/, semua pakai HasUuids)
- [x] Region, Tenant, TenantRegionAccess (modul TenantRegion)
- [x] Entity, EntityAttribute (modul Entity)
- [x] Source, Evidence (modul Evidence) — catatan: Evidence model wajib `$table = 'evidences'` eksplisit (uncountable noun trap)
- [x] Relationship (modul Relationship)

### Testing (Pest PHP, database dinasti_test terpisah dari dinasti)
- [x] TenantRegionTest.php — 4 test PASSED (UUID otomatis, hierarki region, tenant-region access, DENY-by-default)
- [x] EntityRelationshipEvidenceTest.php — 5 test PASSED (entity default draft, entity_attribute wajib evidence, relationship wajib evidence, dst)

### Data seed — kasus Banten (WOW case pertama)
- [x] RegionSeeder — Indonesia → Banten (36) → Kota Serang (36.73), Tangerang Selatan (36.74), Kabupaten Lebak (36.02)
- [x] Tenant "Research Tenant Banten" (slug tenant-banten), akses full ke 4 region di atas
- [x] BantenCaseSeeder — 3 entities (Ratu Atut Chosiyah, Tubagus Chaeri Wardana/Wawan, Dinas Kesehatan Provinsi Banten), 1 source (Liputan6, unverified), 1 evidence, 2 relationships (corruption_scheme, family_affiliation) — semua status draft sesuai gate legal

### Auth — Sanctum (D11)
- [x] Dikonfirmasi Laravel versi 13.24.0 → cara install beda dari tutorial lama, cukup `php artisan install:api`
- [x] Sanctum v4.3.3 terpasang, migration `personal_access_tokens` jalan
- [x] `routes/api.php` dibuat otomatis (contoh route `/user` pakai `auth:sanctum`)
- [x] `bootstrap/app.php` sudah terdaftar routing api
- [x] Trait `HasApiTokens` ditambahkan manual ke `app/Models/User.php`

---

## SEDANG DIKERJAKAN 🔧

### RBAC (lanjutan D11)
- [ ] Keputusan diambil: pakai `spatie/laravel-permission` mode **"teams"** (role beda per tenant per user), pakai `tenant_id` sebagai team_foreign_key — konsisten dengan D10 (shared database)
- [ ] Install package
- [ ] Konfigurasi teams mode
- [ ] Definisikan role apa saja yang dibutuhkan (belum diputuskan — perlu didiskusikan: SUPER_ADMIN, TENANT_ADMIN, RESEARCHER/EDITOR, LEGAL_REVIEWER, PUBLIC_USER?)
- [ ] Migration & testing

---

## BELUM DIMULAI (urutan sesuai Master Tracker asli / dependency E07→E08/E09→E17)

- [ ] Tenant/Region Context Middleware (E08) — baca header X-Region-ID, set context per request
- [ ] PostgreSQL Row-Level Security (RLS) per D10 — lapis tambahan isolasi tenant di level database
- [ ] API Endpoint pertama (Entity Search API, dst — E17)
- [ ] Legal Review Gate state machine (D7) — DRAFT → PENDING_REVIEW → PUBLISHED
- [ ] Frontend (React + TypeScript + Cytoscape.js) — belum dimulai sama sekali

---

## KEPUTUSAN PENTING YANG SUDAH DIAMBIL DI LUAR DECISIONS.md ASLI
(kalau sudah final & berulang dipakai, sebaiknya dipromosikan jadi entri resmi di DECISIONS.md)

- RBAC pakai spatie/laravel-permission mode teams (belum masuk DECISIONS.md sebagai D12 — pending konfirmasi final)

---

## CHANGE LOG
| Tanggal | Update |
|---|---|
| 2026-08-12 | File dibuat. Progress s/d Sanctum install lengkap dicatat. RBAC (spatie/laravel-permission teams mode) direkomendasikan, menunggu konfirmasi final user. |