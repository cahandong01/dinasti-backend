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

---

## STATUS RINGKAS (per 2026-08-14)

**Fase sekarang:** Phase 1 — MVP Foundation, mendekati selesai
**Stack backend terkonfirmasi:** Laravel 13.24.0, PostgreSQL 18 lokal, Redis (Predis), Pest PHP
**Total test:** 64/64 PASSED
**Repo backend:** github.com/cahandong01/dinasti-backend (commit terakhir: 1f92b6c)
**Kredensial DB:** JANGAN pakai user `postgres` (superuser) — WAJIB `dinasti_app` (non-superuser). Lihat D13.

---

## SELESAI ✅

### Database — fondasi + tambahan
- [x] 8 tabel inti: regions, tenants, tenant_region_access, entities, sources, evidences, entity_attributes, relationships
- [x] Kolom `tenant_id` ditambah LANGSUNG ke entity_attributes & relationships (bukan cuma via entity_id) — demi performa RLS
- [x] Kolom `reviewed_by`+`reviewed_at` di entities DAN relationships (minimal audit, BUKAN audit trail lengkap E35 — itu epic terpisah, sengaja ditunda)
- [x] pg_trgm extension aktif + index GIN di entities.name (fuzzy search)
- [x] btree_gist extension (buat constraint EXCLUDE USING gist di entity_attributes/relationships, cegah overlap periode bitemporal)

### RLS (Row-Level Security) — D10 + D13 (BACA INI DULU SEBELUM SENTUH DATABASE)
- [x] 6 tabel ber-RLS: entities, sources, evidences, entity_attributes, relationships, tenant_region_access
- [x] **BUG KRITIS D13 — WAJIB DIPAHAMI:** koneksi app awalnya pakai user `postgres` (superuser). PostgreSQL OTOMATIS mengabaikan RLS untuk superuser, walau sudah FORCE ROW LEVEL SECURITY. Sudah diperbaiki: user baru `dinasti_app` (non-superuser) jadi pemilik SEMUA tabel, `.env`+`phpunit.xml` pakai kredensial ini. **KALAU NANTI ADA YANG "RESET" .env KE POSTGRES LAGI, RLS AKAN BOCOR TANPA ERROR APAPUN — TIDAK ADA WARNING.**
- [x] Test yang membuktikan RLS itu WAJIB menguji "data yang salah TIDAK muncul" (cross-tenant boundary), bukan cuma "data yang benar muncul" — pelajaran dari insiden D13.

### Auth & RBAC — D11, D12
- [x] Laravel Sanctum v4.3.3 (SPA/API token auth)
- [x] spatie/laravel-permission v8.3.0 mode "teams" (`tenant_id` sebagai team_foreign_key, BUKAN default `team_id`)
- [x] 5 role: SUPER_ADMIN (global, tenant_id NULL), TENANT_ADMIN/RESEARCHER/LEGAL_REVIEWER (per-tenant), PUBLIC_USER (default tanpa role, TIDAK ada row di tabel roles)
- [x] Separation of duties tervalidasi: RESEARCHER tidak bisa publish, LEGAL_REVIEWER tidak bisa create

### Tenant Context Middleware — E08
- [x] `app/Http/Middleware/TenantContext.php`, alias `tenant.context` di bootstrap/app.php
- [x] Baca header `X-Tenant-ID`, validasi user punya role di tenant itu (atau SUPER_ADMIN), set `setPermissionsTeamId()` + `SET app.current_tenant` (buat RLS)
- [x] Middleware `role` (Spatie RoleMiddleware) alias `role`, WAJIB dipasang bersarang DI DALAM grup `tenant.context` (urutan matter!)

### API Endpoint (semua di app/Modules/{Entity,Relationship,Graph}/)

**Entity:**
- [x] `GET /api/entities/search?q=&type=&per_page=` — fuzzy search pg_trgm
- [x] `GET /api/entities/{id}` — detail + atribut bitemporal + evidence trail + relationship dua arah
- [x] `POST /api/entities` — create, role RESEARCHER|TENANT_ADMIN|SUPER_ADMIN, status selalu draft
- [x] `PATCH /api/entities/{id}` — update, HANYA saat status draft/needs_revision
- [x] `PATCH /api/entities/{id}/submit-for-review` — role RESEARCHER dkk
- [x] `PATCH /api/entities/{id}/publish` — role LEGAL_REVIEWER|SUPER_ADMIN saja
- [x] `PATCH /api/entities/{id}/request-revision` — role LEGAL_REVIEWER|SUPER_ADMIN saja

**Relationship (pola identik Entity):**
- [x] `POST /api/relationships` — evidence_id wajib, exists:tabel,id otomatis ke-scope RLS
- [x] `PATCH /api/relationships/{id}` — cuma field type/valid_from/valid_until yang bisa diedit
- [x] `PATCH /api/relationships/{id}/submit-for-review`, `/publish`, `/request-revision`

**Graph (fitur flagship D1):**
- [x] `GET /api/entities/{id}/network?depth=N` (max 4, default 2) — Explore Network, recursive CTE, traversal dua arah, anti-infinite-loop
- [ ] Find Connection (jalur terpendek 2 entity spesifik) — BELUM dibangun
- [ ] Cross-Region Explorer — BELUM dibangun

### Data seed — kasus Banten
- [x] RegionSeeder, TenantSeeder ("Research Tenant Banten"), BantenCaseSeeder (3 entities, 1 source, 1 evidence, 2 relationships), RoleSeeder (4 role awal)

---

## FRONTEND (REPO TERPISAH — BACA INI KALAU DITANYA SOAL FRONTEND)

**PENTING:** Frontend GURITA dikerjakan di repo/sesi Claude LAIN, bukan di `D:\dinasti\dinasti-backend`. Kalau user tanya soal frontend, JANGAN asumsi kondisinya dari nol — tanya dulu status terbaru, atau minta blueprint frontend diupload ulang.

**Stack frontend yang SUDAH DIKUNCI (FINAL TECHNOLOGY LOCK, blueprint v1.3):**
- Next.js (App Router) + React + TypeScript
- **Sigma.js + Graphology** sebagai graph stack — Cytoscape.js SUDAH DIHAPUS dari basis, ini hasil evaluasi eksplisit, BUKAN keputusan sembarangan. **JANGAN rekomendasikan ganti balik ke Cytoscape.js** kecuali ada requirement native baru + ADR + benchmark ulang.
- TanStack Query buat server state
- PWA (Serwist), target juga Android/iOS native-ready (addendum v1.3)

**Kontrak penting yang perlu diingat backend:**
- Data flow: PostgreSQL → Laravel Graph API → scoped graph DTO → Graphology → Sigma.js/WebGL
- Backend Graph API (yang sudah kita bangun, `/api/entities/{id}/network`) SENGAJA balikin format generic (array entities + array relationships) — BUKAN format spesifik Cytoscape/Sigma. Biarkan begitu, transformasi ke format Graphology itu tanggung jawab frontend, BUKAN backend.
- Target hero graph di frontend cuma 10-30 node — konsisten sama filosofi hard-cap 4 hop kita (D1).
- Sigma implementation frontend disembunyikan di balik adapter/hooks layer — kalau backend Graph API berubah shape response-nya, dampaknya idealnya cuma nyentuh 1 lapis adapter itu, bukan semua halaman.

---

## INSIDEN & PELAJARAN PENTING (WAJIB DIBACA — BIAR TIDAK TERULANG)

1. **`artisan make:model`, `make:controller`, `make:request`, dan kemungkinan `make:*` lain SELALU naruh file ke lokasi default Laravel** (`app/Models/`, `app/Http/Controllers/`, `app/Http/Requests/`), TIDAK PERNAH ke `app/Modules/{Modul}/...` walau dikasih path custom. Solusi: bikin file manual (`type nul > path\file.php` lalu isi manual), JANGAN pakai `--path` (tidak didukung).

2. **RLS otomatis di-bypass PostgreSQL untuk superuser** — koneksi aplikasi WAJIB non-superuser (D13). Test yang cuma cek "data yang benar muncul" TIDAK CUKUP — WAJIB ada test yang cek "data yang salah TIDAK muncul" (cross-tenant boundary), sejak fitur PERTAMA yang sentuh data bertenant.

3. **Eloquent `::create()` TIDAK otomatis refresh nilai default kolom dari database ke object PHP di memori** — kalau ada kolom dengan default value (misal `status` default `'draft'`), WAJIB panggil `->refresh()` setelah `create()` kalau mau baca nilai default-nya di response.

4. **Query yang JOIN 2 tabel dengan nama kolom sama (misal `tenant_id` di `roles` DAN `model_has_roles`) WAJIB di-qualify nama tabelnya** (`roles.tenant_id`, bukan cuma `tenant_id`) — kalau tidak, PostgreSQL lempar error "ambiguous column".

5. **Recursive CTE buat graph traversal WAJIB pakai array `path` buat cegah infinite loop** di graph yang bersiklus, PLUS depth counter sebagai hard-cap di level query (bukan dipotong belakangan di PHP/aplikasi).

6. **HATI-HATI kasih blok kode PHP panjang yang berkarakter spesial (`(`, `)`, `$`, `>`) — pastikan JELAS itu "buat ditempel di editor (VSCode)", BUKAN "command buat dijalanin di CMD".** Kejadian nyata: kode PHP kecelakaan ke-eksekusi sebagai command CMD, karakter-karakternya diartiin sebagai redirect/perintah, bikin file sampah aneh yang sempat ke-commit ke GitHub (sudah dibersihkan, commit 1f92b6c).

7. **Warning `CRLF will be replaced by LF` pas `git add`/`git commit` itu NORMAL dan AMAN** — cuma normalisasi line-ending Git di Windows, bukan error.

8. **State machine (legal review gate, dst) WAJIB pakai 1 sumber kebenaran tunggal** (contoh: `private const STATUS_BOLEH_EDIT` atau `TRANSISI_VALID` di Service) — jangan sebar logic validasi transisi ke banyak tempat.

9. **Validasi FormRequest dengan `exists:tabel,id` itu OTOMATIS ke-scope RLS** (karena jalan di koneksi tenant yang sama) — bisa dipakai sebagai validasi "kepunyaan tenant yang sama" GRATIS tanpa kode manual tambahan.

---

## KEPUTUSAN DI DECISIONS.md (RINGKAS)

D1 hop-limit graph (4 hop, Explore Network SELESAI, Find Connection & Cross-Region Explorer belum) · D2 pg_trgm search · D3 Event+Redis · D4 AI retrieval-then-generate · D5 entity resolution threshold · D6 bitemporal attributes · D7 legal review gate (SELESAI, entity+relationship) · D8 UUID v7 · D9 PWA · D10 shared DB + RLS · D11 Sanctum · D12 RBAC teams mode · D13 koneksi app wajib non-superuser (KRUSIAL) · D14 frontend graph stack cross-reference (Sigma.js+Graphology locked, lihat CONVENTIONS/DECISIONS lengkap)

---

## BELUM DIMULAI

- [ ] Find Connection API (D1) — jalur terpendek antar 2 entity spesifik
- [ ] Cross-Region Explorer (D1)
- [ ] Rate limiting / anti-scraping untuk PUBLIC_USER — masih "ditunda" di DECISIONS.md, DIUSULKAN dipercepat (risiko platform jadi target politik)
- [ ] Dispute Submission — jalur publik formal buat pihak luar ngajuin keberatan/koreksi resmi (sekarang transisi published→needs_revision masih manual, LEGAL_REVIEWER harus tau dari luar sistem)
- [ ] Audit Trail lengkap (E35) — sengaja ditunda, cuma ada minimal reviewed_by/reviewed_at sekarang
- [ ] User/Profile management API (registrasi, ganti password, kelola anggota tim per tenant) — belum tersentuh sama sekali
- [ ] DDoS protection & hardening infrastruktur (Cloudflare/Deflect) — level infrastruktur, sebelum go-live
- [ ] PostgreSQL Row-Level Security untuk tabel `roles`/`model_has_roles`/dst (Spatie tables) — belum dievaluasi apakah perlu

---

## CATATAN LAIN

- User (pemilik project) adalah guru honorer, BUKAN software engineer — selalu jelaskan istilah teknis dalam bahasa awam sebelum eksekusi kalau terasa perlu.
- Motivasi user: idealisme personal memberantas dinasti politik kotor di Indonesia, BUKAN order klien. Ini juga instrumen buat "mendongkrak" [[natix]] dan [[xora-platform]] lewat jalur audience/distribusi.
- Preferensi kerja: SATU langkah per giliran, command CMD siap-tempel, tunggu konfirmasi. Kalau minta buka file VSCode, WAJIB kasih command `code <path>` DISUSUL LANGSUNG isi kode di pesan yang sama (jangan nunggu "dah" dulu). Edit kode (cari-ganti) TIDAK perlu diverifikasi ulang setelah user bilang "dah" — andalkan hasil test/error.
- Root folder dokumentasi blueprint backend asli: `D:\PROJECT IMPIAN\Fix Blueprint Peta Gurita\`

---

## CHANGE LOG
| Tanggal | Update |
|---|---|
| 2026-08-12 | File dibuat. Progress s/d Sanctum install lengkap dicatat. |
| 2026-08-13 | RBAC (D12), E08 Middleware, RLS (D10) selesai + bug kritis D13 ditemukan&diperbaiki, Entity Search+Detail API. |
| 2026-08-14 | Update besar: Entity Create/Update/Review, Relationship Create/Update/Review (legal review gate D7 lengkap 2 sisi), Graph Traversal Explore Network (D1) selesai. Insiden file sampah ke-commit (sudah bersih). Frontend blueprint v1.3 diketahui: Sigma.js+Graphology locked. 64/64 test passed. Sesi ditutup, resume lengkap disusun buat sesi berikutnya (dokumen ini + CONVENTIONS.md + DECISIONS.md diupdate). |