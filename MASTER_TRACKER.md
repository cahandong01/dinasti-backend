# PETA GURITA DINASTI — MASTER TRACKER
Living document — resume lengkap progress project, diupdate Claude tiap numpuk progress besar.
Root project backend: D:\dinasti\dinasti-backend
Root project frontend: TERPISAH (repo lain, dibangun sesi Claude lain — lihat bagian FRONTEND)
Referensi wajib: CONVENTIONS.md, DECISIONS.md (satu folder ini)

---

## GLOSARIUM SINGKAT

- **Tenant** = pemilik/pengelola ruang data (contoh: "Research Tenant Banten"). Data antar tenant terisolasi.
- **Region** = wilayah geografis yang dibahas (Banten, dst). Terpisah dari tenant — satu tenant bisa riset banyak region.
- **RLS** = Row-Level Security, lapis pengaman di level PostgreSQL sendiri, nolak baris data yang bukan milik tenant aktif — bahkan kalau kode Laravel ada bug lupa filter.
- **Legal Review Gate (D7)** = state machine draft→pending_review→published/needs_revision. Data yang menyentuh nama orang TIDAK BOLEH auto-publish.
- **Maker-Checker** = pola dimana 1 orang bikin request (maker) dan HARUS orang LAIN yang approve (checker) — tidak ada aksi yang bisa auto-approve diri sendiri.

---

## STATUS RINGKAS (per 2026-08-17)

**Fase sekarang:** Phase 1 — MVP Foundation, mendekati selesai
**Stack backend terkonfirmasi:** Laravel 13.24.0, PostgreSQL 18 lokal, Redis (Predis), Pest PHP
**Total test:** 86/86 PASSED
**Repo backend:** github.com/cahandong01/dinasti-backend (commit terakhir: 0a5fb1a)
**Kredensial DB:** JANGAN pakai user `postgres` (superuser) — WAJIB `dinasti_app` (non-superuser). Lihat D13.
**⚠️ WAJIB BACA sebelum kerja apapun soal role/permission:** Insiden #11 di bawah — `$user->roles()` TIDAK BISA dipercaya buat cek role lintas-tenant (termasuk SUPER_ADMIN).

---

## SELESAI ✅

### Database — fondasi + tambahan
- [x] 8 tabel inti: regions, tenants, tenant_region_access, entities, sources, evidences, entity_attributes, relationships (+ users, invites)
- [x] Kolom `tenant_id` ditambah LANGSUNG ke entity_attributes & relationships (bukan cuma via entity_id) — demi performa RLS
- [x] Kolom `reviewed_by`+`reviewed_at` di entities DAN relationships (minimal audit, BUKAN audit trail lengkap E35 — itu epic terpisah, sengaja ditunda)
- [x] pg_trgm extension aktif + index GIN di entities.name (fuzzy search)
- [x] btree_gist extension (buat constraint EXCLUDE USING gist di entity_attributes/relationships, cegah overlap periode bitemporal)
- [x] Migration perbaikan: UUID morphs + `model_has_roles.tenant_id` dibikin nullable (WAJIB, biar role global kayak SUPER_ADMIN bisa punya pivot tenant_id NULL)

### RLS (Row-Level Security) — D10 + D13 (BACA INI DULU SEBELUM SENTUH DATABASE)
- [x] 6 tabel ber-RLS: entities, sources, evidences, entity_attributes, relationships, tenant_region_access
- [x] **BUG KRITIS D13 — WAJIB DIPAHAMI:** koneksi app awalnya pakai user `postgres` (superuser). PostgreSQL OTOMATIS mengabaikan RLS untuk superuser, walau sudah FORCE ROW LEVEL SECURITY. Sudah diperbaiki: user baru `dinasti_app` (non-superuser) jadi pemilik SEMUA tabel, `.env`+`phpunit.xml` pakai kredensial ini. **KALAU NANTI ADA YANG "RESET" .env KE POSTGRES LAGI, RLS AKAN BOCOR TANPA ERROR APAPUN — TIDAK ADA WARNING.**
- [x] Test yang membuktikan RLS itu WAJIB menguji "data yang salah TIDAK muncul" (cross-tenant boundary), bukan cuma "data yang benar muncul" — pelajaran dari insiden D13.

### Auth & RBAC — D11, D12
- [x] Laravel Sanctum v4.3.3 (SPA/API token auth)
- [x] `POST /api/login` — email ATAU username, `POST /api/logout`
- [x] spatie/laravel-permission v8.3.0 mode "teams" (`tenant_id` sebagai team_foreign_key, BUKAN default `team_id`)
- [x] 5 role: SUPER_ADMIN (global, tenant_id NULL), TENANT_ADMIN/RESEARCHER/LEGAL_REVIEWER (per-tenant), PUBLIC_USER (default tanpa role, TIDAK ada row di tabel roles)
- [x] Separation of duties tervalidasi: RESEARCHER tidak bisa publish, LEGAL_REVIEWER tidak bisa create
- [x] Custom middleware `has_role` (`app/Http/Middleware/HasRole.php`) — GANTIKAN `role:` bawaan Spatie karena bug (lihat Insiden #11)

### Tenant Context Middleware — E08
- [x] `app/Http/Middleware/TenantContext.php`, alias `tenant.context` di bootstrap/app.php
- [x] Baca header `X-Tenant-ID`, validasi user punya role di tenant itu (atau SUPER_ADMIN) LEWAT QUERY PIVOT LANGSUNG (bukan `$user->roles()`, lihat Insiden #11), set `setPermissionsTeamId()` + `SET app.current_tenant` (buat RLS)
- [x] Middleware `has_role` (custom, BUKAN Spatie `role` bawaan), WAJIB dipasang bersarang DI DALAM grup `tenant.context` (urutan matter!)

### User/Profile Management API — Invite Maker-Checker
- [x] `POST /api/invites` — TENANT_ADMIN|SUPER_ADMIN bikin undangan, status selalu `pending_approval` (TIDAK ADA auto-approve, termasuk buat SUPER_ADMIN sendiri)
- [x] `PATCH /api/invites/{id}/approve`, `/reject` — HANYA SUPER_ADMIN, dan HANYA yang BUKAN pembuat invite itu sendiri (maker-checker separation)
- [x] `POST /api/invites/{token}/accept` — publik (throttle:auth), bikin akun baru + assign role sesuai invite, TOLAK kalau expired/belum approved/token salah
- [x] TENANT_ADMIN TIDAK BOLEH invite orang jadi TENANT_ADMIN (cegah privilege escalation lateral)
- [x] Tabel `invites`: token, status (pending_approval/approved/rejected), expires_at, invited_by, approved_by

### API Endpoint (semua di app/Modules/{Entity,Relationship,Graph}/)

**Entity:** Search, Detail, Create, Update, submit-for-review, publish, request-revision — SEMUA SELESAI (lihat commit log buat detail, sudah stabil dari sesi 1-2)

**Relationship (pola identik Entity):** Create, Update, submit-for-review, publish, request-revision — SEMUA SELESAI

**Graph (fitur flagship D1):**
- [x] `GET /api/entities/{id}/network?depth=N` (max 4, default 2) — Explore Network, recursive CTE, traversal dua arah, anti-infinite-loop
- [x] `GET /api/entities/{id}/find-connection?target_id=X` — Find Connection, jalur TERPENDEK, recursive CTE + `rel_path`, TANPA parameter depth user-configurable (preseden LinkedIn "Degree of Connection"), respons `200 + connected:false` (bukan 404) kalau entity ada tapi tidak ada jalur dalam 4 hop
- [ ] Cross-Region Explorer — BELUM dibangun

### Rate Limiting — mitigasi OWASP API4:2023 (Unrestricted Resource Consumption)
- [x] 4 limiter bernama: `auth` (5/menit/IP, SEKARANG SUDAH TERPAKAI di `/login` dan `/invites/{token}/accept`), `graph` (guest 10/menit/IP, authenticated 30/menit/user), `search` (30/menit), `api` default (guest 20/menit, authenticated 60/menit)
- [x] Angka disimpan terpusat di `config/rate_limits.php`, didaftarkan di `AppServiceProvider::boot()`
- [x] Test `RateLimitingTest.php`: buktikan 429 setelah lewat batas authenticated (30/menit), request dalam batas tetap lolos

### Data seed — kasus Banten
- [x] RegionSeeder, TenantSeeder ("Research Tenant Banten"), BantenCaseSeeder (3 entities, 1 source, 1 evidence, 2 relationships), RoleSeeder (4 role awal)

---

## FRONTEND (REPO TERPISAH — BACA INI KALAU DITANYA SOAL FRONTEND)

**PENTING:** Frontend GURITA dikerjakan di repo/akun Claude LAIN sepenuhnya, bukan di `D:\dinasti\dinasti-backend`. Kalau user tanya soal frontend, JANGAN asumsi kondisinya dari nol — tanya dulu status terbaru.

**Stack frontend yang SUDAH DIKUNCI (FINAL TECHNOLOGY LOCK, blueprint v1.3):**
- Next.js (App Router) + React + TypeScript
- **Sigma.js + Graphology** — Cytoscape.js SUDAH DIHAPUS dari basis. **JANGAN rekomendasikan ganti balik** kecuali requirement native baru + ADR + benchmark ulang.
- TanStack Query buat server state, PWA (Serwist)

**Kontrak penting:** Backend Graph API (`/network`, `/find-connection`) SENGAJA balikin format generic (array entities + array relationships) — BUKAN format spesifik Sigma/Graphology. Transformasi itu tanggung jawab frontend.

---

## INSIDEN & PELAJARAN PENTING (WAJIB DIBACA — BIAR TIDAK TERULANG)

1. **`artisan make:model`/`make:controller`/`make:request` SELALU naruh file ke lokasi default Laravel**, TIDAK PERNAH ke `app/Modules/{Modul}/...`. Solusi: bikin file manual (`type nul > path\file.php`), JANGAN pakai `--path`.

2. **RLS otomatis di-bypass PostgreSQL untuk superuser** — koneksi aplikasi WAJIB non-superuser (D13). Test WAJIB cek "data yang salah TIDAK muncul", bukan cuma "data yang benar muncul".

3. **Eloquent `::create()` TIDAK otomatis refresh nilai default kolom** — WAJIB `->refresh()` setelah `create()` kalau butuh baca default value di response.

4. **Query JOIN 2 tabel dengan nama kolom sama WAJIB di-qualify nama tabelnya** (`roles.tenant_id`, bukan cuma `tenant_id`).

5. **Recursive CTE traversal WAJIB array `path` anti-cycle + depth cap di level query.**

6. **Pastikan JELAS command CMD vs kode buat ditempel VSCode** — karakter spesial PHP bisa ke-eksekusi sebagai command CMD kalau salah taruh.

7. **Warning `CRLF will be replaced by LF` pas git itu NORMAL, aman diabaikan.**

8. **State machine WAJIB 1 sumber kebenaran tunggal** (`TRANSISI_VALID` di Service, bukan sebar ke banyak tempat).

9. **Validasi FormRequest `exists:tabel,id` OTOMATIS ke-scope RLS** — bisa dipakai gratis buat validasi "kepunyaan tenant yang sama".

10. **Rate limiter cabang "guest" di route yang dibungkus `auth:sanctum` itu KODE MATI** — `auth:sanctum` nolak request tanpa token duluan (401) sebelum sempat nyampe limiter.

11. **🔴 BUG KRITIS: `$user->roles()` (relasi Eloquent Spatie mode "teams") TIDAK BISA DIPERCAYA buat cek role LINTAS-TENANT, termasuk role global (SUPER_ADMIN, `tenant_id` NULL).**
    **Bukti forensik (dari Tinker, raw SQL):**
```sql
    ... where "model_has_roles"."tenant_id" = ?   ← DIAM-DIAM disuntik Spatie
      and ("roles"."tenant_id" is null or "roles"."tenant_id" = ?)  ← kondisi kita
      and "roles"."tenant_id" is null and "roles"."name" = ?
```
    Spatie nyuntik filter `model_has_roles.tenant_id = current_team` di level RELASI — filter ini ke-AND SEBELUM kondisi `.whereNull()`/`.orWhere()` apapun yang kita tempel di query builder di atasnya. Nambah kondisi manual TIDAK BISA nge-override ini karena filter pivot-nya udah lebih dulu ke-AND. Akibatnya: user dengan role global (pivot `tenant_id` NULL) SELALU keanggap "tidak punya role" begitu context pindah ke tenant manapun (`setPermissionsTeamId($tenant->id)`).
    **Kenapa baru ketauan sekarang:** nggak ada test sebelumnya yang nguji SUPER_ADMIN beraksi di tenant context yang aktif (beda dari test RBAC dasar yang cuma cek role ke-assign, bukan role dipakai lintas-tenant).
    **FIX WAJIB (satu-satunya cara yang terbukti benar):** JANGAN PERNAH pakai `$user->roles()` atau turunannya (`hasRole()`, dst) buat cek role kalau ada kemungkinan role itu global/lintas-tenant. Query LANGSUNG ke tabel `model_has_roles` pakai `DB::table()`, JOIN manual ke `roles`, filter `whereNull('model_has_roles.tenant_id')->orWhere('model_has_roles.tenant_id', getPermissionsTeamId())`. Lihat `app/Http/Middleware/HasRole.php` dan `app/Http/Middleware/TenantContext.php` sebagai contoh acuan yang SUDAH TERBUKTI benar lewat Tinker + test.
    **Kalau nanti nulis pengecekan role di tempat baru manapun (Service, Controller, Policy) — WAJIB pakai pola query pivot langsung ini, JANGAN `$user->hasRole()` bawaan Spatie.**

---

## KEPUTUSAN DI DECISIONS.md (RINGKAS)

D1 hop-limit graph (4 hop; Explore Network + Find Connection SELESAI, Cross-Region Explorer belum) · D2 pg_trgm search · D3 Event+Redis · D4 AI retrieval-then-generate · D5 entity resolution threshold · D6 bitemporal attributes · D7 legal review gate (SELESAI) · D8 UUID v7 · D9 PWA · D10 shared DB + RLS · D11 Sanctum · D12 RBAC teams mode (⚠️ lihat Insiden #11 soal bug-nya) · D13 koneksi app wajib non-superuser (KRUSIAL) · D14 frontend graph stack cross-reference (Sigma.js+Graphology locked)

---

## BELUM DIMULAI

- [ ] Cross-Region Explorer (D1)
- [ ] Dispute Submission — jalur publik formal buat pihak luar ngajuin keberatan/koreksi resmi
- [ ] Audit Trail lengkap (E35) — sengaja ditunda, cuma ada minimal reviewed_by/reviewed_at sekarang
- [ ] Ganti password, kelola anggota tim per tenant (lanjutan User Management — invite+login+accept sudah ada, tapi belum ada self-service password management)
- [ ] DDoS protection & hardening infrastruktur (Cloudflare/Deflect) — level infrastruktur, sebelum go-live
- [ ] PostgreSQL Row-Level Security untuk tabel `roles`/`model_has_roles`/dst (Spatie tables) — belum dievaluasi apakah perlu

---

## CATATAN LAIN

- User (pemilik project) adalah guru honorer, BUKAN software engineer — selalu jelaskan istilah teknis dalam bahasa awam sebelum eksekusi kalau terasa perlu.
- Motivasi user: idealisme personal memberantas dinasti politik kotor di Indonesia, BUKAN order klien.
- Preferensi kerja: SATU langkah per giliran, command CMD siap-tempel, tunggu konfirmasi. `code <path>` DISUSUL LANGSUNG isi kode di pesan yang sama. Edit kode TIDAK perlu diverifikasi ulang setelah "dah" — andalkan hasil test/error.
- **Struktur sesi:** Sesi 1 & 2 backend pakai akun Claude lain (sudah selesai). Sesi 3 (akun ini) lanjutin. Frontend jalur SAMA SEKALI TERPISAH (akun lain, repo lain).
- Root folder dokumentasi blueprint backend asli: `D:\PROJECT IMPIAN\Fix Blueprint Peta Gurita\`

---

## CHANGE LOG
| Tanggal | Update |
|---|---|
| 2026-08-12 | File dibuat. Progress s/d Sanctum install lengkap dicatat. |
| 2026-08-13 | RBAC (D12), E08 Middleware, RLS (D10) selesai + bug kritis D13 ditemukan&diperbaiki, Entity Search+Detail API. |
| 2026-08-14 | Entity Create/Update/Review, Relationship Create/Update/Review (D7 lengkap), Graph Traversal Explore Network (D1) selesai. 64/64 test. |
| 2026-08-15 | Rate Limiting selesai (OWASP API4:2023). Find Connection API (D1) selesai. User/Profile Management API (Invite maker-checker + Login) mulai dikerjakan. |
| 2026-08-17 | **Insiden #11 ditemukan & diperbaiki**: bug kritis Spatie teams-mode, `$user->roles()` exclude role global lintas-tenant — dibuktikan lewat Tinker, di-fix di `HasRole.php` + `TenantContext.php` pakai query pivot langsung. User/Profile Management API SELESAI (invite maker-checker, login email/username). **86/86 test PASS.** Commit terakhir 0a5fb1a. |