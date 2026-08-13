# PETA GURITA DINASTI — DECISIONS.md
Version: 1.0
Status: FINAL — keputusan arsitektur/produk yang menutup gap blueprint v1.1

> Dokumen ini melengkapi dokumen 01/02/Master Handoff/Master Tracker.
> Blueprint asli sudah matang di level strategi, tapi ada beberapa
> keputusan teknis konkret yang belum diturunkan. Dokumen ini yang
> menutup gap itu, diputuskan lewat review expert + riset eksternal.
> Statusnya FINAL — bukan draft — kecuali ada temuan baru yang
> mengharuskan revisi (dicatat di Deviation Log CONVENTIONS.md).

---

## D1. Graph Traversal Depth Limit

**Keputusan:** Hard cap 4 hop untuk semua query graph traversal
(`Find Connection`, `Explore Network`, `Cross-Region Explorer`) di
level API — request yang minta lebih dari 4 hop WAJIB ditolak dengan
error jelas, bukan dibiarkan jalan sampai timeout/server hang.

**Alasan (riset):** PostgreSQL recursive CTE performanya masih wajar
di kisaran 3-4 hop pada dataset jutaan edge, tapi mulai timeout di 10
hop dan sering tidak pernah selesai di 15-20 hop. Masalahnya
arsitektural — recursive executor PostgreSQL adalah iterative set
processor, bukan traversal framework asli: tidak menyimpan visited-state
antar iterasi, sehingga graph yang "melipat balik" (banyak siklus/
koneksi silang) menyebabkan ledakan kombinatorial.

**Trigger ekstraksi ke graph engine dedicated (kalau nanti dibutuhkan):**
Ada use case NYATA (bukan asumsi) yang butuh traversal >4 hop secara
reguler. Sebelum itu terjadi, TIDAK ADA alasan menambah graph database
terpisah — selaras working rule "Do not extract microservices without
a measurable reason."

---

## D2. Search Engine

**Keputusan:** PostgreSQL native (`pg_trgm` extension + `tsvector`
full-text search) untuk MVP dan Phase 2-3. TIDAK menambah Elasticsearch/
Meilisearch/dedicated search engine di awal.

**Alasan:** Satu database = satu sistem yang perlu di-maintain solo dev.
`pg_trgm` cukup untuk fuzzy-match nama Indonesia di skala data awal
(puluhan ribu entity).

**Titik evaluasi ulang:** Dataset entity menembus ~100.000+ DAN ada
keluhan relevansi/kecepatan search yang terukur (bukan perasaan) →
evaluasi Meilisearch (lebih ringan untuk di-maintain solo dibanding
Elasticsearch).

---

## D3. Komunikasi Antar-Modul (Event/Messaging)

**Keputusan:** Laravel native Event + Listener, dengan Redis sebagai
queue driver. TIDAK menambah message broker terpisah (RabbitMQ, Kafka)
di MVP/Phase manapun sampai ada alasan terukur.

**Aturan implementasi:**
- Setiap event lintas modul adalah class eksplisit di
  `app/Modules/{ModulName}/Events/` dengan nama deskriptif
  (`EntityResolved`, `EvidenceLinked`, `RelationshipPublished`) —
  bukan generic event bertipe string.
- Listener yang react ke event dari modul lain didaftarkan di
  `EventServiceProvider` milik modul yang mendengarkan, bukan di modul
  yang men-trigger — supaya arah dependency tetap jelas (modul
  pendengar yang tahu soal modul sumber, bukan sebaliknya).

---

## D4. AI/LLM Grounding Strategy

**Keputusan:** Pola wajib **retrieval-then-generate**, LLM tidak pernah
dipanggil tanpa context yang sudah di-retrieve lebih dulu lewat query
biasa (bukan lewat LLM juga).

**Alur wajib untuk "Ask the Graph" dan "AI Insight":**
1. Parse pertanyaan/trigger → jalankan query terstruktur ke
   graph/evidence (kode biasa, bukan LLM).
2. Hasil query (entity, relationship, evidence yang relevan) di-pass
   sebagai context ke LLM.
3. LLM HANYA merangkai hasil itu jadi bahasa natural — dilarang keras
   menambahkan klaim yang tidak ada di context yang di-pass (system
   prompt harus eksplisit melarang ini, dan ada spot-check manual
   berkala terhadap sample output).
4. Setiap output AI WAJIB menyimpan: `prompt_version`, `model_used`,
   `source_query` (query/filter yang dipakai untuk retrieval), dan
   `retrieved_context_ids` (evidence/entity id yang jadi dasar jawaban)
   — ini yang membuat prinsip "AI bukan source of truth" benar-benar
   dipaksa secara teknis, bukan sekadar prinsip di dokumen.

---

## D5. Entity Resolution Confidence Threshold

**Keputusan:** 3 tier eksplisit berdasarkan similarity score:

| Score | Status | Perlakuan |
|---|---|---|
| ≥ 0.90 | `MATCH` | Auto-merge, boleh langsung tersambung ke graph |
| 0.60 – 0.89 | `POSSIBLE_MATCH` | WAJIB masuk antrian review manual — TIDAK BOLEH ditampilkan sebagai fakta pasti ke publik sebelum direview dan disetujui |
| < 0.60 | Dianggap entity berbeda | Tidak digabung otomatis |

Threshold ini adalah nilai awal (v1) — WAJIB dikalibrasi ulang begitu
ada gold-set evaluasi nyata (selaras E41 AI Evaluation Framework di
Master Tracker), dicatat sebagai versi baru di dokumen ini kalau
berubah.

---

## D6. Entity Attribute Versioning (Bitemporal)

**Keputusan:** Extend pola bitemporal yang sudah ada di `relationships`
(`valid_from`/`valid_until`) ke atribut entity yang berubah dari waktu
ke waktu — bukan cuma relationship, atribut entity juga.

**Implementasi:** Tabel `entity_attributes` (bukan kolom langsung di
`entities`) dengan struktur: `entity_id`, `attribute_key` (misal
`position`, `shareholding_percent`), `attribute_value`, `valid_from`,
`valid_until`, `evidence_id` (wajib ada rujukan bukti per perubahan).
Ini yang membuat "siapa jabat apa kapan" bisa ditelusuri historis
penuh, bukan cuma state terakhir yang overwrite tanpa jejak.

---

## D7. Legal/Publication Review Gate (Implementasi Teknis)

**Keputusan:** State machine wajib untuk finding yang menyentuh nama
orang (bukan cuma badan hukum/institusi):

```
DRAFT → PENDING_REVIEW → PUBLISHED
              ↓
          REJECTED / NEEDS_REVISION
```

- Default state untuk finding baru yang menyentuh nama orang: `DRAFT`.
  TIDAK ADA jalur auto-publish untuk kategori ini.
- Transisi `PENDING_REVIEW → PUBLISHED` WAJIB melalui aksi eksplisit
  (bukan cron job/scheduled auto-approve).
- `PUBLISHED` finding tetap bisa masuk `NEEDS_REVISION` kalau ada
  correction/dispute request dari subjek — bukan hanya unpublish diam-
  diam, harus tercatat di audit trail (selaras E35 Audit Trail).

---

## D8. Primary Key Strategy

Lihat CONVENTIONS.md §1B — UUID v7 untuk entity utama, bigint hanya
untuk tabel internal murni. Dicatat ulang di sini sebagai referensi
silang karena ini juga keputusan arsitektur, bukan cuma coding style.

---

## D9. Mobile/PWA Strategy

Lihat CONVENTIONS.md §3.6 — full PWA, Graph Explorer dibatasi 1-hop
render di mobile breakpoint dengan tap-to-expand, dites di perangkat
Android & iOS sungguhan.

---

## D10. Database Multi-Tenancy Strategy

**Keputusan:** Satu database bersama (`dinasti`) untuk SEMUA tenant —
BUKAN database terpisah per tenant. Isolasi lewat kolom `tenant_id` di
setiap tabel data substantif (sudah diterapkan sejak migration
pertama), DIPERKUAT dengan PostgreSQL Row-Level Security (RLS) sebagai
lapis pengaman tambahan di level database.

**Alasan (riset):** Pola shared-database-shared-schema adalah titik
awal standar untuk SaaS modern — database-per-tenant baru masuk akal
kalau ada kebutuhan compliance/regulasi yang mewajibkan pemisahan
fisik data, yang platform ini TIDAK punya. Lebih penting lagi: fitur
Cross-Region/Cross-Tenant Explorer (WOW-006, WOW-012 di Master
Tracker) butuh traversal graph lintas tenant yang authorized — ini
nyaris mustahil dilakukan efisien kalau datanya tersebar di database
fisik terpisah.

**RLS sebagai lapis tambahan:** Isolasi tenant SAAT INI cuma dienforce
di level aplikasi (Laravel global scope/middleware). Risiko nyata:
satu query yang lupa filter `tenant_id` bisa jadi kebocoran data
lintas tenant. RLS menutup celah ini di level database — bahkan kalau
ada bug di kode Laravel, database tetap menolak mengembalikan baris
tenant lain. Ini penerapan konkret dari working rule "tenant isolation
server-side, never UI-only" yang sudah disepakati sejak awal.

**Rencana implementasi RLS (Phase 1, bagian dari E08 Tenant Context
Middleware):**
```sql
ALTER TABLE entities ENABLE ROW LEVEL SECURITY;
ALTER TABLE entities FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON entities
  USING (tenant_id = current_setting('app.current_tenant')::uuid);
```
Setiap tabel dengan kolom `tenant_id` WAJIB punya RLS policy yang
setara sebelum dianggap production-ready — dicatat sebagai security
gate tambahan di samping yang sudah ada di Master Tracker §11.

---

## D11. Autentikasi API

**Keputusan:** Laravel Sanctum (bukan Passport/OAuth2 penuh) untuk
autentikasi API.

**Alasan (riset):** Sanctum adalah paket resmi Laravel yang ringan,
didesain khusus untuk pasangan SPA + API (React + Laravel, sesuai stack
yang sudah diputuskan) dan aplikasi mobile — cukup untuk kebutuhan
platform ini tanpa kompleksitas OAuth2 server penuh yang tidak
diperlukan di skala MVP solo dev. Urutan build: Auth & RBAC (Sanctum)
WAJIB selesai sebelum API endpoint data (Entity Search API, dst)
dibangun — sesuai dependency yang sudah tertulis di Master Tracker
sendiri (E07 → E08/E09 → E17), karena endpoint data butuh middleware
tenant/region context yang berasal dari hasil auth.

---

## D12. RBAC (Role-Based Access Control) Implementation

**Keputusan:** `spatie/laravel-permission` dengan fitur **"teams" diaktifkan**
(`tenant_id` sebagai `team_foreign_key`) — role dan permission di-scope
per tenant, bukan global untuk semua user.

**Alasan (riset):** Mode "teams" dari `spatie/laravel-permission` dirancang
khusus untuk skenario SaaS multi-tenant seperti GURITA — satu user bisa
punya role berbeda di tenant berbeda, dan ini konsisten dengan D10
(shared database, isolasi lewat kolom `tenant_id`). Ini pola established
yang dipakai luas di ekosistem Laravel untuk kasus serupa, bukan solusi
custom yang perlu dibangun dari nol.

**Role v1 (akan dikalibrasi ulang begitu ada kebutuhan riil, sama seperti
pola D5):**

| Role | Scope | Kewenangan inti |
|---|---|---|
| `SUPER_ADMIN` | Global (`team_id = null`) | Akses penuh lintas tenant & region |
| `TENANT_ADMIN` | Per-tenant | Kelola user & akses region dalam 1 tenant |
| `RESEARCHER` | Per-tenant | Input/edit entity, evidence, relationship — TIDAK BISA publish sendiri |
| `LEGAL_REVIEWER` | Per-tenant | Satu-satunya role yang bisa approve `PENDING_REVIEW` → `PUBLISHED` (D7) |
| `PUBLIC_USER` | Global (default, bukan role yang di-assign) | Read-only data `PUBLISHED`, kena rate-limit |

**Prinsip yang dipegang (riset RBAC best practice):**
- **Least privilege** — tiap role cuma dapat izin seminimal fungsinya.
- **Separation of duties** — `RESEARCHER` (pembuat draft) dan
  `LEGAL_REVIEWER` (penyetuju publish) WAJIB beda role, supaya D7
  (legal review gate) benar-benar dipaksa secara teknis lewat RBAC, bukan
  cuma aturan di UI yang bisa dilanggar diam-diam.
- **Hindari role sprawl** — 5 role ini adalah set minimal berdasarkan
  fungsi kerja nyata, bukan ditambah "just in case" (selaras YAGNI).
  
---

## D13. Koneksi Database Aplikasi WAJIB Non-Superuser

**Keputusan:** Koneksi database yang dipakai aplikasi (`.env` dev,
`phpunit.xml` testing) WAJIB pakai user PostgreSQL non-superuser
(`dinasti_app`) — BUKAN user `postgres` (superuser bawaan instalasi).
User `postgres` tetap ada untuk kebutuhan administratif manual
(pgAdmin/DBeaver), bukan untuk koneksi aplikasi sehari-hari.

**Alasan (ditemukan lewat testing, bukan riset awal):** PostgreSQL
secara desain **mengabaikan RLS untuk superuser**, termasuk yang sudah
diaktifkan `FORCE ROW LEVEL SECURITY` (D10). Ini ketahuan lewat test
`EntitySearchTest` yang gagal — entity dari tenant lain tetap muncul
di hasil pencarian tenant lain, padahal RLS policy sudah benar. Root
cause dikonfirmasi lewat query `pg_roles.rolsuper`: koneksi aplikasi
ternyata connect sebagai `postgres` (`rolsuper = true`), sehingga
SEMUA policy RLS yang sudah dibangun sejak awal project **tidak
pernah benar-benar aktif** sampai keputusan ini diterapkan.

**Pelajaran untuk ke depan:** test yang membuktikan isolasi tenant
lintas-boundary (bukan cuma "data yang benar muncul", tapi juga "data
yang salah TIDAK muncul") wajib ada sejak fitur pertama yang
menyentuh data bertenant — bukan ditunda sampai ada fitur yang
"kelihatan". `EntitySearchTest` kebetulan jadi test pertama yang
benar-benar menguji cross-tenant boundary secara eksplisit; RBAC dan
Tenant Context Middleware sebelumnya tidak menguji lapisan RLS ini
karena scope-nya beda (role-checking, bukan data-row-checking).

---

## STATUS ITEM YANG SENGAJA DITUNDA (bukan diabaikan)

Item berikut dari review awal TIDAK diputuskan final sekarang karena
belum ada data/kebutuhan konkret — akan direvisit begitu ada trigger
yang disebutkan:

- **Adversarial pattern detection** (nominee, pemecahan kepemilikan
  buat menghindari deteksi) — ditunda ke Phase 3 (Pattern Detection,
  E32) setelah entity resolution & relationship extraction dasar
  terbukti stabil. Membangun ini terlalu dini berisiko over-engineer
  di atas data yang belum matang.
- **Rate-limit/anti-scraping konkret** (angka request/menit, dsb) —
  prinsipnya sudah final (WAJIB ada), tapi angka threshold ditentukan
  saat implementasi Phase 1 berdasarkan baseline traffic normal yang
  terukur, bukan ditebak dari awal.
- **Horizontal scaling / Laravel Octane / CDN** — prinsip sudah
  disepakati (didesain gampang ditambah), tapi tidak diaktifkan di
  MVP. Ini sengaja: mengaktifkan optimasi performa untuk beban yang
  belum ada itu sendiri adalah pelanggaran YAGNI.

---

## CHANGE LOG

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 2026-08-11 | Dokumen awal — menutup 9 gap dari review expert terhadap blueprint v1.1 |
