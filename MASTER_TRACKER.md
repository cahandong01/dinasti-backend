# PETA GURITA DINASTI — MASTER TRACKER
Living document — diupdate Claude tiap ada progress numpuk.
Root project: D:\dinasti\dinasti-backend
Referensi wajib: CONVENTIONS.md, DECISIONS.md (satu folder ini)

---

## GLOSARIUM SINGKAT

- **Tenant** = pemilik/pengelola ruang data (contoh: "Research Tenant Banten"). Data antar tenant terisolasi.
- **Region** = wilayah geografis yang dibahas (Banten, dst). Terpisah dari tenant — satu tenant bisa riset banyak region.
- **RLS** = Row-Level Security, lapis pengaman di level PostgreSQL sendiri, nolak baris data yang bukan milik tenant aktif — bahkan kalau kode Laravel ada bug lupa filter.

---

## STATUS RINGKAS

**Fase sekarang:** Phase 1 — MVP Foundation
**Sedang dikerjakan:** API endpoint tambahan (Entity Create/Update, atau Relationship API)
**Stack terkonfirmasi:** Laravel 13.24.0, PostgreSQL 18 lokal, Redis (Predis), Pest PHP
**Total test:** 26/26 PASSED
**Repo:** github.com/cahandong01/dinasti-backend (up to date, commit terakhir 1742a7f)

---

## SELESAI ✅

### Database — 8 tabel fondasi + 2 kolom tambahan
- [x] `regions`, `tenants`, `tenant_region_access`, `entities`, `sources`, `evidences`, `entity_attributes`, `relationships`
- [x] Kolom `tenant_id` ditambah langsung ke `entity_attributes` & `relationships` (awalnya cuma via `entity_id`) — demi performa RLS (subquery tidak bisa dioptimasi planner)
- [x] pg_trgm extension aktif + index GIN di `entities.name` (fuzzy search)

### RLS (Row-Level Security) — D10 + D13
- [x] 6 tabel ber-RLS: entities, sources, evidences, entity_attributes, relationships, tenant_region_access
- [x] **Bug kritis ditemukan & diperbaiki (D13):** koneksi app awalnya pakai user `postgres` (superuser) — RLS otomatis di-bypass PostgreSQL untuk superuser. User baru `dinasti_app` (non-superuser) dibuat, jadi pemilik semua tabel, `.env`+`phpunit.xml` diupdate pakai kredensial baru. RLS sekarang BENERAN aktif, tervalidasi test cross-tenant.

### Auth & RBAC — D11, D12
- [x] Laravel Sanctum v4.3.3 (SPA/API token auth)
- [x] spatie/laravel-permission mode "teams" (`tenant_id` sebagai team_foreign_key)
- [x] 5 role: SUPER_ADMIN (global), TENANT_ADMIN/RESEARCHER/LEGAL_REVIEWER (per-tenant), PUBLIC_USER (default tanpa role)
- [x] Separation of duties tervalidasi: RESEARCHER ≠ LEGAL_REVIEWER

### Tenant Context Middleware — E08
- [x] `TenantContext.php` (alias `tenant.context`) — baca header `X-Tenant-ID`, validasi user punya role di tenant itu, aktifkan `setPermissionsTeamId()` + `SET app.current_tenant` (buat RLS)

### API Endpoint
- [x] `GET /api/entities/search` (E17) — fuzzy search pakai pg_trgm, filter by type, pagination
- [x] `GET /api/entities/{id}` — detail lengkap: atribut bitemporal + evidence trail + relationship dua arah (source & target)

### Data seed — kasus Banten
- [x] RegionSeeder (Indonesia → Banten → 3 kota/kabupaten), Tenant "Research Tenant Banten", BantenCaseSeeder (3 entities, 1 source, 1 evidence, 2 relationships — semua status draft)
- [x] RoleSeeder (4 role: 1 global + 3 per-tenant)

### Governance/Keamanan tambahan
- [x] `.env` dikonfirmasi aman di `.gitignore`, tidak pernah ke-commit
- [ ] DDoS protection (Cloudflare/Deflect) — BELUM disetting, levelnya infrastruktur, nanti pas deploy
- [ ] Rate limiting API — masih "ditunda" di DECISIONS.md, DIUSULKAN dipercepat mengingat risiko platform ini jadi target (dinasti politik)

---

## SEDANG DIKERJAKAN 🔧

- [ ] Endpoint berikutnya belum diputuskan — kandidat: Entity Create/Update API (dengan legal review gate D7), Relationship Create API, atau Rate Limiting (keamanan)

---

## BELUM DIMULAI

- [ ] Entity Create/Update API + validasi legal review gate (D7): DRAFT → PENDING_REVIEW → PUBLISHED
- [ ] Correction/dispute path (janji ke publik, prinsip governance awal)
- [ ] Rate limiting / anti-scraping untuk PUBLIC_USER
- [ ] Graph traversal API (Find Connection, Explore Network) — hard cap 4 hop (D1)
- [ ] Frontend (React + TypeScript + Cytoscape.js) — belum dimulai sama sekali
- [ ] DDoS protection & hardening infrastruktur (Cloudflare/Deflect) — sebelum go-live

---

## KEPUTUSAN DI DECISIONS.md (RINGKAS)

D1 hop-limit graph · D2 pg_trgm search · D3 Event+Redis · D4 AI retrieval-then-generate · D5 entity resolution threshold · D6 bitemporal attributes · D7 legal review gate · D8 UUID v7 · D9 PWA · D10 shared DB + RLS · D11 Sanctum · D12 RBAC teams mode · **D13 (baru) koneksi app wajib non-superuser**

---

## PELAJARAN PENTING (biar gak keulang)

- `artisan make:model`, `make:controller`, `make:request` (dan kemungkinan `make:*` lain) SELALU naruh file ke lokasi default Laravel, bukan ke `app/Modules/{Modul}/...` — cek dulu lokasi sebelum lanjut isi, jangan asumsi.
- RLS itu **otomatis diabaikan untuk superuser PostgreSQL** — koneksi app WAJIB non-superuser, atau RLS cuma ilusi (D13).
- Test yang membuktikan "data yang salah TIDAK muncul" (bukan cuma "data yang benar muncul") itu WAJIB ada sejak fitur pertama yang sentuh data bertenant — jangan ditunda sampai fitur "kelihatan".

---

## CHANGE LOG
| Tanggal | Update |
|---|---|
| 2026-08-12 | File dibuat. Progress s/d Sanctum install lengkap dicatat. |
| 2026-08-13 | Update besar: RBAC (D12), E08 Middleware, RLS (D10) selesai + bug kritis D13 ditemukan&diperbaiki, Entity Search API (E17), Entity Detail API selesai. 26/26 test passed. |