# PETA GURITA DINASTI — MASTER TRACKER
Living document — resume lengkap progress project, diupdate Claude tiap numpuk progress besar.
Root project backend: D:\dinasti\dinasti-backend
Root project frontend: TERPISAH (repo lain, akun Claude lain — lihat bagian FRONTEND)
Referensi wajib: CONVENTIONS.md, DECISIONS.md, **API_CONTRACT.md** (satu folder ini)

---

## GLOSARIUM SINGKAT

- **Tenant** = pemilik/pengelola ruang data. Data antar tenant terisolasi.
- **Region** = wilayah geografis, terpisah dari tenant, hierarkis (parent_id).
- **RLS** = Row-Level Security, lapis pengaman di level PostgreSQL sendiri.
- **Legal Review Gate (D7)** = state machine draft→pending_review→published/needs_revision.
- **Maker-Checker** = 1 orang bikin request (maker), orang LAIN yang approve (checker).
- **Hak Jawab** (UU Pers Pasal 1 ayat 11) = tanggapan/sanggahan pemberitaan yang merugikan nama baik PEMOHON SENDIRI, batas 2 bulan sejak publikasi.
- **Hak Koreksi** (UU Pers Pasal 1 ayat 12) = koreksi kekeliruan informasi tentang diri sendiri MAUPUN orang lain, TANPA batas waktu.

---

## STATUS RINGKAS (per 2026-08-20)

**Fase sekarang:** Phase 1 — MVP Foundation, mendekati selesai
**Stack backend terkonfirmasi:** Laravel 13.24.0, PostgreSQL 18 lokal, Redis (Predis), Pest PHP
**Total test:** 106/106 PASSED
**Repo backend:** github.com/cahandong01/dinasti-backend (commit terakhir: d36c647, SUDAH di-push)
**Kredensial DB:** WAJIB `dinasti_app` (non-superuser), lihat D13.
**⚠️ WAJIB BACA sebelum kerja apapun soal role/RLS/migration:** Insiden #11-#17 di bawah.
**⚠️ WAJIB PUSH setiap sesi kerja selesai** — sesi frontend cuma bisa lihat progress backend lewat GitHub, bukan folder lokal. Commit yang numpuk tanpa push bikin frontend salah asumsi "belum dikerjakan" (kejadian nyata 2026-08-19/20, lihat Insiden #17).
**⚠️ WAJIB BACA sebelum kerja frontend-related apapun:** `API_CONTRACT.md` — dokumen kontrak FINAL yang disepakati bareng sesi frontend, 7 keputusan (slug routing, tracking_token, dispute type, upload file ditunda, status hukum ditunda, endpoint regions, riwayat dispute publik).

---

## SELESAI ✅

### Database — fondasi + tambahan
- [x] 10 tabel inti: regions, tenants, tenant_region_access, entities, sources, evidences, entity_attributes, relationships, users, invites, disputes
- [x] Kolom `slug` di entities — SEO-friendly URL, unique global, auto-generate dari nama (collision-safe, suffix angka)
- [x] Kolom `first_published_at` di entities & relationships — cuma keisi SEKALI (publikasi pertama)
- [x] disputes: kolom `type` (hak_jawab/koreksi), `tracking_token` (opaque, publik), `is_self_reported` (self-declared, BUKAN diverifikasi identitas)
- [x] VIEW `entity_disputes_public` — proyeksi kolom non-PII dari disputes, dipakai endpoint riwayat publik

### RLS (Row-Level Security) — D10 + D13 + carve-out publik (BACA SEBELUM SENTUH DATABASE)
- [x] entities/relationships: carve-out `OR status = 'published'` — akses publik lintas-tenant buat data published
- [x] disputes: carve-out TERPISAH, SELECT-only, `status IN ('resolved_accepted','resolved_rejected')` — TIDAK ADA carve-out INSERT/UPDATE/DELETE publik (beda dari entities/relationships)
- [x] disputes: policy INSERT terpisah (`WITH CHECK (true)`) — aman karena tenant_id selalu di-set server-side, bukan input user
- [x] WAJIB `NULLIF(..., '')` sebelum cast uuid di semua kondisi RLS (current_setting balikin string kosong kalau di-RESET, bukan NULL, dan itu crash bukan cuma nolak akses)

### Auth & RBAC — D11, D12
- [x] Sanctum, `/login` (email/username), `/logout`
- [x] spatie/laravel-permission mode "teams", 5 role
- [x] Custom middleware `has_role` (GANTIKAN `role:` bawaan Spatie karena bug, Insiden #11)

### User/Profile Management API — Invite Maker-Checker
- [x] `POST /api/invites`, `PATCH /api/invites/{id}/approve|reject`, `POST /api/invites/{token}/accept`

### Entity/Relationship/Graph API
- [x] Entity & Relationship: Search, Detail (`GET /api/entities/{slug}` — SLUG bukan UUID), Create, Update, submit-for-review, publish, request-revision
- [x] **Search & Detail SEKARANG bisa diakses PUBLIK tanpa login** — 1 endpoint, 2 mode (optional auth Sanctum): tanpa token → cuma `published` lintas-tenant; dengan token+X-Tenant-ID → semua status di tenant sendiri (perilaku lama, tidak berubah). Middleware baru `tenant.context.optional`, lihat `OptionalTenantContext.php`
- [x] RLS carve-out publik diperluas ke rantai evidence (`entity_attributes`, `evidences`, `sources`) — sebelumnya cuma `entities`/`relationships`, jadi detail entity publik crash 500 kalau ada atribut/evidence (lihat Insiden #17)
- [x] `GET /api/entities/{id}/network`, `GET /api/entities/{id}/find-connection` (masih pakai UUID — beda dari endpoint detail)
- [ ] Cross-Region Explorer — BELUM dibangun

### Dispute Submission API — Hak Jawab/Hak Koreksi (LENGKAP, sesuai API_CONTRACT.md)
- [x] `POST /api/disputes` — publik, field `type` (hak_jawab/koreksi), identitas pelapor, `is_self_reported`, disputed_part/supporting_evidence/response_content
- [x] Batas waktu 2 bulan HANYA berlaku utk `hak_jawab` (dihitung dari first_published_at) — `koreksi` TIDAK ada batas waktu (UU Pers Pasal 1 ayat 11 vs 12)
- [x] `GET /api/disputes/status/{token}` — publik, cek status pakai tracking_token opaque (BUKAN email di URL — hindari kebocoran PII lewat log/Referer header)
- [x] `PATCH /api/disputes/{id}/approve|reject` — LEGAL_REVIEWER|SUPER_ADMIN, trigger state machine Entity/Relationship yang sudah ada
- [x] `GET /api/entities/{id}/disputes` — riwayat publik, HANYA resolved, lewat VIEW `entity_disputes_public` (kolom PII secara STRUKTURAL tidak ada di view ini)
- [x] Modul `app/Modules/Dispute/` (domain sendiri, bukan numpang Entity/Relationship/TenantRegion)
- [x] Morph map `entity`/`relationship` (alias stabil, bukan nama class PHP mentah)
- [x] Rate limiter `dispute` (5/menit/IP)

### Region API
- [x] `GET /api/regions?parent_id=` — publik, cascading dropdown Provinsi→Kab/Kota→Kecamatan→Desa

### Rate Limiting — OWASP API4:2023
- [x] 5 limiter: `auth`, `dispute`, `graph`, `search`, `api`

---

## FRONTEND (REPO TERPISAH)

**⚠️ WAJIB BACA `API_CONTRACT.md`** — dokumen FINAL hasil negosiasi backend↔frontend (proses: draft dari backend → jawaban frontend → keputusan final backend buat 3 poin ambigu, berbasis riset). 7 keputusan final:
1. Entity routing pakai `slug`, bukan UUID
2. Dispute tracking via `tracking_token` opaque (BUKAN email di URL)
3. Dispute punya `type` (hak_jawab/koreksi) sesuai UU Pers, cuma hak_jawab yang ada batas 2 bulan
4. Upload bukti file DITUNDA (belum ada timeline pasti dari frontend)
5. Status Hukum (`legal_cases`) DITUNDA — scope fitur besar terpisah, BELUM dikerjakan
6. `GET /api/regions` publik — SELESAI
7. Riwayat dispute per-entity publik, TANPA data pelapor/reviewer — SELESAI

**Stack locked:** Next.js + React + TypeScript, **Sigma.js + Graphology** (BUKAN Cytoscape.js), TanStack Query, PWA (Serwist).
**Kontrak Graph API:** balikin format generic (entities+relationships), transformasi ke Graphology tanggung jawab frontend.
**Kebutuhan API BELUM dikerjakan:** statistik agregat homepage (jumlah entitas/relasi/sumber bukti/wilayah), network graph preview publik, badge "terverifikasi" entity resolution (D5 — BELUM ada implementasinya sama sekali, JANGAN desain UI yang nunggu field ini).

---

## INSIDEN & PELAJARAN PENTING (WAJIB DIBACA)

*(1-10: struktur folder modul manual bukan artisan make:*, RLS bypass superuser D13, Eloquent create() tidak auto-refresh default DB, qualify nama tabel di JOIN, recursive CTE anti-cycle, command CMD vs kode VSCode, CRLF warning aman, state machine 1 sumber kebenaran, exists: rule otomatis ke-scope RLS, rate limiter guest unreachable di balik auth:sanctum — lihat commit history buat detail lengkap)*

11. **🔴 `$user->roles()` (Spatie teams mode) TIDAK BISA DIPERCAYA buat cek role LINTAS-TENANT** (termasuk SUPER_ADMIN). Spatie diam-diam nyuntik filter `model_has_roles.tenant_id = current_team`. FIX: query LANGSUNG ke `model_has_roles` pakai `DB::table()` + JOIN manual. Lihat `HasRole.php`, `TenantContext.php`.

12. **`Relation::enforceMorphMap()` beda dari `morphMap()`.** `enforceMorphMap()` MEMAKSA SELURUH aplikasi (termasuk Spatie, Sanctum) harus terdaftar di map, bikin `ClassMorphViolationException` di fitur yang nggak ada hubungannya (role assignment, token). Pakai `morphMap()` biasa.

13. **RLS Postgres butuh POLICY TERPISAH buat INSERT vs SELECT/UPDATE/DELETE.** Policy `USING` yang ketat otomatis nolak INSERT dari request publik (app.current_tenant kosong) walau `tenant_id` yang ditulis valid. Solusi: `CREATE POLICY ... FOR INSERT WITH CHECK (true)` — aman kalau kolom sensitif selalu di-set server-side.

14. **`->refresh()` (atau `find()`/`findOrFail()`) setelah `create()` bisa GAGAL DIAM-DIAM di request PUBLIK pada tabel ber-RLS.** Row-nya ADA, tapi refresh() itu SELECT ulang yang tunduk RLS, ditolak tanpa tenant context → `ModelNotFoundException` → Laravel otomatis balikin `404` (bukan 500 — kelihatan kayak "route salah" bukan "masalah RLS", menyesatkan pas debug). FIX: skip `refresh()` kalau semua nilai response udah ada di memori dari array `create()`.

15. **🔴 MIGRATION (backfill data lewat `DB::table()->get()/update()`) TETAP TUNDUK RLS**, karena jalan lewat koneksi `dinasti_app` non-superuser (D13) — BUKAN cuma runtime API. Tanpa `app.current_tenant` di-set, migration cuma "lihat" baris yang lolos carve-out publik (misal `status='published'`), baris lain (draft dst) KELEWAT dari backfill — dan `ALTER COLUMN ... SET NOT NULL` di akhir migration itu DDL yang scan SEMUA baris fisik (bypass RLS), jadi nemu baris yang slug/kolomnya masih NULL dan migration GAGAL di langkah terakhir. **WAJIB `ALTER TABLE ... DISABLE ROW LEVEL SECURITY` sebelum backfill, `ENABLE`+`FORCE` lagi sesudahnya**, di SETIAP migration yang backfill data lama. Ketahuan pas migration tambah kolom `slug` ke `entities`.

16. **🔴 RLS Postgres itu ROW-LEVEL, BUKAN COLUMN-LEVEL.** Kalau ada kasus "sebagian baris boleh publik, tapi sebagian KOLOM tetap harus privat" (misal: dispute yang sudah resolved boleh dibaca publik, tapi nama/email/HP pelapor tetap harus rahasia) — RLS SENDIRI TIDAK CUKUP, karena carve-out row-level otomatis buka SEMUA kolom di baris itu. **FIX (riset, rekomendasi standar): kombinasikan RLS row-level carve-out DENGAN PostgreSQL VIEW** yang secara STRUKTURAL cuma punya kolom non-sensitif — bukan cuma ngandelin aplikasi `SELECT` kolom tertentu (itu rawan lupa/bug di kode masa depan). Lihat migration `create_entity_disputes_public_view`, `DisputeService::getPublicHistoryForDisputable()` (WAJIB query VIEW, JANGAN model `Dispute` langsung buat endpoint publik). **Batasan jujur:** RLS carve-out row-level-nya sendiri berlaku di level tabel asli juga, jadi proteksi kolom penuh tetap bergantung disiplin kode (cuma lewat method/VIEW ini buat akses publik) — bukan 100% dijamin database.

17. **🔴 Commit lokal yang NGGAK di-push itu INVISIBLE buat sesi frontend — mereka cuma bisa cek progress backend lewat GitHub, bukan folder lokal user.** Kejadian nyata: 17 commit (semua kerjaan Dispute Submission, slug, RLS carve-out, dst) numpuk lokal tanpa di-push, sesi frontend investigasi ke GitHub dan nemuin state LAMA (`AppServiceProvider.php` masih kosong versi awal) — laporan gap mereka akurat buat apa yang mereka lihat, tapi menyesatkan karena kerjaan sebenarnya udah jauh lebih maju. **WAJIB `git push origin main` di akhir SETIAP sesi kerja yang ngasilin commit** — bukan cuma commit lokal doang, terutama kalau lagi ada kerjaan yang overlap sama frontend (endpoint publik, kontrak API).

    Insiden kedua yang sama harinya: endpoint `search`/`{slug}` entity ternyata masih dibungkus `auth:sanctum` wajib login, padahal RLS carve-out publik-nya udah dibangun dari awal — fondasi database-nya ada, tapi rute HTTP-nya nggak pernah dibuka buat manfaatin itu. **Fix: middleware `tenant.context.optional`** (pola resmi Laravel Sanctum "optional auth" — jangan paksa `auth:sanctum`, resolve `$request->user('sanctum')` manual, balikin `null` buat tamu bukan `401`). 1 route, 2 perilaku, tergantung ada token atau nggak.

---

## KEPUTUSAN DI DECISIONS.md (RINGKAS)

D1 hop-limit graph · D2 pg_trgm · D3 Event+Redis · D4 AI retrieval-then-generate · D5 entity resolution threshold (⚠️ BELUM diimplementasi sama sekali — lihat catatan Frontend soal badge "terverifikasi") · D6 bitemporal attributes · D7 legal review gate · D8 UUID v7 · D9 PWA · D10 shared DB + RLS (+ 2 carve-out publik) · D11 Sanctum · D12 RBAC teams mode (⚠️ Insiden #11) · D13 non-superuser (KRUSIAL, ⚠️ juga berlaku ke migration, Insiden #15) · D14 frontend Sigma.js+Graphology locked

**Dokumen baru: `API_CONTRACT.md`** — kontrak FINAL backend↔frontend, 7 keputusan (lihat bagian FRONTEND di atas).

---

## BELUM DIMULAI

- [ ] Cross-Region Explorer (D1)
- [ ] Audit Trail lengkap (E35)
- [ ] Status Hukum / `legal_cases` (API_CONTRACT.md #5) — scope fitur besar, tabel polymorphic baru (pengadilan, nomor perkara, tanggal, status, sumber)
- [ ] Statistik agregat homepage + network graph preview publik
- [ ] Entity Resolution pipeline (D5) — badge "terverifikasi" frontend nunggu ini
- [ ] Upload dokumen/bukti dispute (API_CONTRACT.md #4, ditunda, belum ada timeline)
- [ ] Ganti password/kelola anggota tim
- [ ] DDoS protection & hardening infrastruktur
- [ ] RLS untuk tabel Spatie (`roles`/`model_has_roles`)

---

## CATATAN LAIN

- User adalah guru honorer, BUKAN software engineer — jelaskan istilah teknis dalam bahasa awam.
- Motivasi: idealisme personal memberantas dinasti politik kotor di Indonesia.
- Preferensi kerja: SATU langkah per giliran, command CMD siap-tempel, tunggu konfirmasi. `code <path>` DISUSUL LANGSUNG isi kode. Edit kode TIDAK perlu diverifikasi ulang setelah "dah" **KECUALI user eksplisit minta verifikasi (biasanya kalau ada indikasi error/Find & Replace nggak exact) — dalam kasus itu, WAJIB minta paste isi lengkap file, jangan asumsi Find & Replace berhasil.** User lebih suka Find & Replace (Ctrl+H) exact daripada instruksi ambigu.
- Struktur sesi: Sesi 1 & 2 backend akun Claude lain (selesai). Sesi 3 (akun ini) lanjutin. Frontend jalur SAMA SEKALI TERPISAH (akun lain) — koordinasi lewat dokumen `API_CONTRACT.md` yang dibawa bolak-balik user, bukan komunikasi langsung antar sesi.
- Root folder dokumentasi blueprint backend asli: `D:\PROJECT IMPIAN\Fix Blueprint Peta Gurita\`

---

## CHANGE LOG
| Tanggal | Update |
|---|---|
| 2026-08-12 s/d 2026-08-15 | Lihat commit log — fondasi Auth+RBAC+RLS, CRUD Entity/Relationship+D7, Explore Network, Rate Limiting, Find Connection, User Management dimulai. |
| 2026-08-17 | Insiden #11 (Spatie teams bug), User Management selesai, RLS carve-out publik (entities/relationships), Dispute Submission API dasar (Hak Jawab UU Pers No 40/1999). 97/97 test. |
| 2026-08-18 | Diskusi 3 blueprint frontend + ilustrasi visual → `API_CONTRACT_DRAFT.md` → respons frontend → `API_CONTRACT.md` FINAL (7 keputusan). Implementasi penuh: slug routing entity, dispute type+tracking_token+is_self_reported, endpoint status publik, `GET /api/regions`, riwayat dispute publik via VIEW. Insiden #12-#16 ditemukan & diperbaiki (morphMap, RLS insert/select terpisah, refresh() vs RLS publik, migration tunduk RLS, RLS row-level butuh VIEW buat column-level). 102/102 test PASS. |
| 2026-08-19/20 | Sesi frontend lapor gap via `GAP_API_CONTRACT_UNTUK_BACKEND.md` — ternyata 17 commit lokal belum ke-push (Insiden #17), PLUS search/detail entity ternyata masih wajib login walau RLS carve-out-nya udah ada. Push 17 commit lama + fix: middleware `tenant.context.optional` (optional auth Sanctum), RLS carve-out publik diperluas ke rantai evidence (entity_attributes/evidences/sources). Balasan status dikirim ke frontend via `BALASAN_GAP_API_UNTUK_FRONTEND.md`. **106/106 test PASS.** Commit terakhir d36c647, SUDAH di-push. |