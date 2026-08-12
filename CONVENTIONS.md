# PETA GURITA DINASTI — CONVENTIONS.md
Version: 1.0
Status: WAJIB DIPATUHI — dokumen ini adalah "hukum" project

> Dokumen ini adalah pengganti fungsi code review manusia. Karena tim ini
> solo (1 orang non-engineer + Claude sebagai executor), tidak ada rekan
> kerja yang bisa mengecek penyimpangan kode secara manual. Setiap aturan
> di sini WAJIB diikuti tanpa kecuali, kecuali ada konfirmasi eksplisit
> dari user untuk menyimpang — dan penyimpangan itu harus dicatat di
> bagian "Deviation Log" di akhir dokumen ini beserta alasannya.

Setiap keputusan di dokumen ini disandarkan ke sumber (dokumentasi resmi
atau referensi arsitektur yang established), bukan preferensi subjektif.
Sumber dicantumkan per bagian.

---

# 1. PRINSIP INTI

1. **DRY (Don't Repeat Yourself)** — Kalau logika yang sama muncul 3x+,
   itu WAJIB diekstrak jadi function/method/component. 2x masih boleh
   ditoleransi (rule of three), tapi harus dicatat sebagai kandidat
   refactor.
2. **KISS (Keep It Simple, Stupid)** — Solusi paling sederhana yang
   memenuhi acceptance criteria selalu menang, kecuali ada requirement
   eksplisit yang butuh kompleksitas lebih.
3. **YAGNI (You Aren't Gonna Need It)** — Jangan bangun abstraksi/fitur
   untuk kebutuhan yang "mungkin nanti dibutuhkan". Selaras dengan
   working rule Master Tracker: *"Do not extract microservices without
   a measurable reason"* dan *"Do not add advanced AI before evaluation
   datasets exist."*
4. **Single Responsibility** — Satu class/function/component = satu
   alasan untuk berubah.
5. **Explicit over implicit** — Nama variable/function harus jelas
   maksudnya tanpa perlu baca implementasinya. Tidak ada magic number
   tanpa constant bernama.

---

# 1B. PRIMARY KEY STRATEGY — UUID vs BIGINT (WAJIB DIPATUHI SEJAK MIGRASI PERTAMA)

> Ini keputusan yang HARUS benar sejak migration pertama dibuat — salah
> di sini itu mahal sekali diperbaiki setelah data & relasi menumpuk.

**Keputusan: UUID v7 sebagai primary key publik untuk semua entity
utama, `bigint` internal cuma untuk index performa.**

| Tabel | Primary Key | Alasan |
|---|---|---|
| `entities`, `relationships`, `evidences`, `sources`, `tenants`, `regions` | `uuid` (v7) sebagai kolom `id`, PRIMARY KEY | Direferensikan dari luar sistem (URL publik, laporan, citation) — tidak boleh bocorin urutan/jumlah data, tidak boleh gampang dienumerasi (`/entity/1`, `/entity/2`, dst) |
| Tabel internal murni (log, cache, junction table tanpa referensi eksternal) | `bigint` autoincrement biasa | Tidak pernah diekspos ke luar, prioritaskan performa index |

**Kenapa UUID v7, bukan v4:**
- UUID v4 (random murni) merusak performa insert PostgreSQL karena
  pola tulis acak ke B-tree index (index jadi terfragmentasi, bukan
  sequential).
- UUID v7 punya timestamp di 48-bit pertama → sequential-ish saat
  insert (performa insert mendekati bigint autoincrement), TAPI tetap
  tidak bisa ditebak/dienumerasi orang luar karena sisa bit tetap
  random.
- Laravel 12 sudah default pakai ordered UUID (v7) di trait `HasUuids`
  bawaan — dikonfirmasi lewat riset dokumentasi resmi & sumber
  komunitas per 2026, TIDAK perlu override manual `newUniqueId()`
  seperti anggapan awal sebelum riset ini dilakukan (koreksi dari versi
  draft dokumen ini sebelumnya).

**Aturan implementasi:**
- Model yang pakai UUID: cukup `use HasUuids;` — TIDAK perlu override
  `newUniqueId()`, Laravel 12 sudah generate ordered UUID v7 secara
  default. Override manual hanya kalau ada temuan baru yang
  membuktikan sebaliknya (dicatat di Deviation Log kalau terjadi).
- Foreign key SELALU merujuk ke UUID kolom `id` yang sama, konsisten
  di semua tabel — tidak boleh campur (sebagian tabel pakai bigint FK,
  sebagian UUID) kecuali untuk tabel internal murni di atas.
- Kolom UUID di PostgreSQL pakai tipe native `uuid`, BUKAN `char(36)`
  atau `varchar` — supaya index dan storage efisien.
- Setiap migration WAJIB direview terhadap tabel ini sebelum di-run —
  kalau ada tabel baru yang statusnya ambigu (internal vs
  publik/eksternal), tanya ke user dulu, jangan asumsi.

---

# 2. BACKEND — LARAVEL / PHP

### 2.1 Baseline
- **PSR-12** sebagai baseline gaya kode PHP (standar resmi PHP-FIG,
  extended coding style guide).
- PHP type hints WAJIB untuk semua parameter fungsi dan return type
  (PHP 8.1+ — manfaatkan `enum`, `readonly property`, `first-class
  callable syntax` di mana relevan).
- Static analysis: **PHPStan / Larastan** minimal level 5 (naik
  bertahap ke level lebih tinggi setelah base project stabil — level 8
  Larastan langsung dari hari 1 berisiko menghambat kecepatan solo dev
  tanpa manfaat proporsional di tahap awal).

### 2.2 Struktur folder — Modular Monolith
Mengikuti pola yang sudah dikunci di Master Tracker & Enterprise
Architecture Blueprint. Referensi pattern: Laravel modular monolith
architecture (bukan default Laravel App/Http/Controllers datar), dengan
prinsip **setiap modul self-contained dan komunikasi antar-modul lewat
Events/Queue, bukan manggil model modul lain secara langsung** — ini
mencegah "leaky abstraction" yang jadi pitfall paling umum di pattern
ini.

```
app/
  Modules/
    Entity/
      Controllers/
      Models/
      Services/
      Repositories/      (hanya jika dibutuhkan — lihat 2.3)
      Requests/           (FormRequest validation)
      Events/
      Listeners/
      routes.php
      Tests/
    Relationship/
      ...
    TenantRegion/
      ...
    Evidence/
      ...
    (dst sesuai modul di dokumen 02)
  Shared/                 (helper/trait lintas modul, dipakai sangat terbatas)
```

**Aturan keras modular monolith:**
- Modul TIDAK BOLEH langsung query/import Model dari modul lain.
  Komunikasi lintas modul lewat Event (`event()` + Listener) atau lewat
  Service interface yang di-inject via container — bukan manggil
  Eloquent model modul lain secara langsung.
- Setiap modul harus bisa "dihapus" secara konseptual tanpa merusak
  modul lain (self-contained).

### 2.3 Kapan pakai Repository Pattern vs Eloquent langsung
Berdasar riset: repository pattern **tidak wajib untuk semua CRUD**.
Aturan main:
- **Pakai Eloquent langsung** di Service class untuk CRUD sederhana
  tanpa kebutuhan abstraksi data source ganda.
- **Pakai Repository + Interface** HANYA kalau: (a) butuh testability
  tinggi dengan mock data layer, atau (b) ada kemungkinan nyata sumber
  data berubah/beragam (contoh relevan di GURITA: Entity data bisa
  datang dari PostgreSQL langsung ATAU dari hasil resolusi Python
  Worker — ini kandidat sah untuk repository pattern).
- Jangan pasang repository layer "just in case" — itu overhead
  kompleksitas tanpa manfaat, sesuai prinsip YAGNI di atas.

### 2.4 Fat Model/Skinny Controller → Service Layer
- **Controller**: hanya terima request, panggil Service, kembalikan
  response. Tidak ada business logic di controller.
- **FormRequest**: semua validasi input lewat class FormRequest
  terpisah, bukan validasi inline di controller.
- **Service class**: tempat business logic. Satu Service = satu domain
  concern (contoh: `EntityResolutionService`, bukan `EntityService`
  raksasa yang isinya segala hal soal entity).
- **DTO (Data Transfer Object)**: gunakan typed DTO (readonly property,
  named argument) untuk passing data antar layer pada operasi yang
  kompleks (bukan untuk CRUD sepele) — mencegah array asosiatif
  "bentuk bebas" yang gampang typo key-nya dan susah di-trace.

### 2.4B Cara Membuat File Model (WAJIB, mencegah kesalahan berulang)

**JANGAN pakai `php artisan make:model Modules/{Modul}/Models/{Nama}`
secara langsung** — command ini SELALU naruh file di bawah
`app/Models/...` walau diberi path custom (keterbatasan hardcoded di
Laravel `ModelMakeCommand`, bukan bug project ini), menghasilkan
struktur ganda yang salah (`app/Models/Modules/...`) dan butuh
`move` manual tiap kali.

**Cara yang benar: buat file model langsung di lokasi final**, tanpa
lewat `artisan make:model`:
```
type nul > app\Modules\{Modul}\Models\{Nama}.php
code app\Modules\{Modul}\Models\{Nama}.php
```
Isi manual dengan namespace `App\Modules\{Modul}\Models`, trait
`HasUuids`, dan `$fillable`/relasi sesuai kebutuhan. PSR-4 autoload
Composer otomatis mengenali class ini tanpa config tambahan.

**Jebakan penamaan tabel yang WAJIB diperiksa tiap model baru:**
Laravel Eloquent menebak nama tabel dari nama class secara otomatis
(snake_case + plural bahasa Inggris — `TenantRegionAccess` ditebak
jadi `tenant_region_accesses`, nambah akhiran "es"). Kalau nama tabel
sebenarnya BEDA dari tebakan otomatis ini (seperti `tenant_region_access`
tanpa akhiran, karena kita definisikan manual di migration), model
WAJIB punya baris eksplisit:
```php
protected $table = 'nama_tabel_asli';
```
Tanpa ini, Eloquent akan query ke tabel yang salah/tidak ada dan
error saat runtime — bukan saat migration. Selalu cocokkan nama tabel
di migration dengan nama yang Eloquent tebak sebelum lanjut; kalau
beda, WAJIB tambahkan `$table` eksplisit ini.

### 2.5 Naming convention

| Elemen | Konvensi | Contoh |
|---|---|---|
| Class | PascalCase | `EntityResolutionService` |
| Method/variable | camelCase | `resolveMatchConfidence()` |
| Kolom database | snake_case | `tenant_id`, `valid_from` |
| Config key | snake_case | `graph.max_traversal_depth` |
| Route (URI) | kebab-case | `/api/v1/cross-region-explorer` |
| Migration file | snake_case standar Laravel | `2026_08_11_create_entities_table` |

### 2.6 Testing
- Setiap HTTP endpoint WAJIB punya feature test yang cover: skenario
  sukses, validasi gagal, otorisasi gagal (termasuk cross-tenant
  DENIED — ini bukan opsional, ini security gate dari Master Tracker
  §11).
- Target coverage: fokus ke **critical path dulu** (auth, tenant/region
  scope, evidence linkage) — bukan kejar angka coverage global di awal,
  itu buang waktu buat solo dev di fase MVP.
- Framework: **Pest PHP** (sintaks lebih ringkas dari PHPUnit, standar
  modern Laravel testing).
- **Struktur folder test: FLAT**, `tests/Feature/{NamaDeskriptif}Test.php`
  — BUKAN nested per modul (`tests/Feature/Modules/{Modul}/...`).
  Keputusan ini disengaja demi konsistensi dengan proyek lain milik
  user ([[xora-platform]], yang terbukti jalan baik dengan pola flat +
  nama deskriptif seperti `CashierShiftControllerTest.php`,
  `BranchCrudEndToEndTest.php`). Nama file WAJIB deskriptif dan
  menyebut modul/fitur di namanya (misal `TenantRegionAccessTest.php`,
  bukan cuma `AccessTest.php`) supaya tetap mudah ditemukan walau flat.

---

# 3. FRONTEND — REACT + TYPESCRIPT

### 3.1 Struktur folder — Feature-Based / Feature-Sliced
Riset 2026 konsisten: pola **feature-based** (group by domain/fitur,
bukan by tipe file) adalah standar untuk aplikasi production skala
menengah-besar — alasannya colocation (file yang berubah bareng, hidup
bareng) dan mengurangi cognitive load saat onboarding/lanjut kerja
antar sesi.

```
src/
  features/
    entity-search/
      components/
      hooks/
      api/
      types.ts
    graph-explorer/
      components/
      hooks/
      api/
      types.ts
    evidence-viewer/
      ...
    tenant-region-scope/
      ...
  shared/
    components/        (Button, Modal, dll — dipakai lintas fitur)
    hooks/
    lib/
    api/                (axios/fetch instance, interceptor tenant header)
  app/
    routes.tsx
    App.tsx
```

**Aturan keras:**
- Fitur boleh import dari `shared/`, TAPI `shared/` TIDAK BOLEH import
  apapun dari `features/*` (dependency searah, mencegah circular
  dependency dan coupling tersembunyi).
- Kalau sebuah fitur dihapus foldernya, sisa aplikasi harus tetap
  jalan tanpa error (tanda self-contained yang sehat).

### 3.2 Export style
- Gunakan **named export**, bukan default export — alasan riset:
  refactor/rename lebih aman (semua reference ikut ter-update otomatis
  oleh IDE), dan autocomplete/AI tooling lebih akurat mendeteksinya.

### 3.3 State management
- **Server state** (data dari API: entity, graph, evidence): pakai
  library query-cache (misal TanStack Query) — BUKAN disimpan manual di
  useState/Redux. Server state punya karakter beda (loading, error,
  stale, refetch) yang sudah diselesaikan library ini, jangan
  reinvent.
- **UI state lokal** (modal terbuka/tutup, tab aktif): `useState`
  biasa di komponen terkait, tidak perlu global store.
- **Global state benar-benar lintas-aplikasi** (tenant/region context
  aktif, user session): Context API atau state manager ringan (Zustand)
  — hindari Redux kecuali kompleksitas state sudah terbukti butuh itu
  (YAGNI).

### 3.4 Styling & Design System
Mengikuti keputusan visual yang sudah disepakati (terang & institusional):

```css
/* Token warna — CSS variables, WAJIB dipakai, tidak boleh hardcode hex baru di komponen manapun */
--color-bg: #FAFAF9;
--color-text-primary: #1E293B;
--color-primary: #1D4ED8;      /* aksi utama, link, aksen trust */
--color-danger: #DC2626;        /* red-flag/risk indicator — pemakaian TERBATAS */
--color-verified: #059669;      /* evidence/verified marker */
```

- Typography: pola token terpusat (`--font-*`) — sama prinsip yang
  sudah terbukti jalan di proyek lain milik user (XORA). Font family:
  Inter atau IBM Plex Sans (legible untuk tabel/data, kesan formal —
  bukan font playful/rounded ala consumer app).
- **Aturan mutlak (mengadaptasi pelajaran dari proyek lain milik
  user):** semua font-size WAJIB pakai token `var(--font-*)`, tidak
  boleh hardcode `px` baru di halaman manapun. Butuh ukuran yang belum
  ada tokennya → konsolidasi ke token terdekat dulu, jangan langsung
  bikin token baru tanpa alasan hierarki visual yang jelas.
- Styling engine: Tailwind CSS (utility-first, cepat untuk solo dev,
  konsisten dengan design token lewat `tailwind.config` yang
  mereferensikan CSS variables di atas — bukan value bebas).

### 3.5 Graph rendering (Cytoscape.js)
- Konfigurasi style Cytoscape (warna node/edge per tipe entity, layout
  algorithm) WAJIB didefinisikan di satu file config terpusat
  (`features/graph-explorer/cytoscapeConfig.ts`), tidak boleh
  inline/tersebar di berbagai komponen — supaya kalau nanti butuh ganti
  skema warna node, cukup 1 file yang diubah.

### 3.6 Mobile-Friendly & PWA (WAJIB, bukan opsional)

**Keputusan: Full PWA, dan Graph Explorer WAJIB tetap bisa dipakai
nyaman di HP** — bukan cuma "responsive supaya nggak rusak", tapi
didesain khusus untuk layar kecil.

**PWA baseline:**
- `manifest.json` lengkap (icon 192/512, theme-color sesuai token
  `--color-primary`, `display: standalone`) sejak awal project, bukan
  ditambah belakangan.
- Service worker untuk offline-shell minimal (halaman tetap bisa
  dibuka walau nggak ada koneksi, walau datanya nggak update) —
  library `vite-plugin-pwa` (kalau pakai Vite) menghindari nulis
  service worker manual dari nol.
- Instalable di Android (Add to Home Screen) dan iOS (Safari Add to
  Home Screen) — dites eksplisit di kedua platform sebelum dianggap
  "selesai", bukan asumsi dari desktop testing.

**Strategi khusus Graph Explorer di layar kecil (berdasar riset
dokumentasi resmi Cytoscape.js):**
Prinsip dari dokumentasi Cytoscape sendiri: <cite index="42-1">graph besar itu masalah bukan cuma performa tapi juga secara visual susah diparse manusia — solusinya memfilter ke subset relevan dan biarkan user navigasi antar subset, bukan render semua node sekaligus.</cite> Ini kita manfaatkan sebagai strategi mobile:

- **Desktop/tablet**: boleh render graph dengan radius koneksi lebih
  luas (misal 2-hop dari entity yang dipilih).
- **Mobile (breakpoint ≤768px)**: WAJIB batasi ke **1-hop only**
  (entity terpilih + koneksi langsungnya), dengan tap-to-expand untuk
  masuk lebih dalam node per node — bukan render subgraph besar
  sekaligus.
- **Kontrol touch**: pastikan `tap`, `pinch-to-zoom`, dan `pan` diuji
  di perangkat sungguhan (bukan cuma browser dev-tools mobile
  emulation) — Cytoscape.js native mendukung touch events, tapi hit-area
  node WAJIB diperbesar di breakpoint mobile (radius node minimum
  lebih besar dari desktop) supaya gampang di-tap jari.
- Performance hint (`hideEdgesOnViewport`, batasi `devicePixelRatio`
  di HP low-end) diaktifkan khusus di breakpoint mobile — trade-off
  ketajaman render demi kelancaran pan/zoom di HP.
- Panel detail (Entity Profile, Evidence Viewer) yang biasanya
  side-by-side dengan graph di desktop → WAJIB jadi bottom-sheet/modal
  full-screen terpisah di mobile, bukan dipepetin jadi kecil di
  samping graph.

### 3.7 Testing
- Component test pakai Vitest + React Testing Library (bukan
  Enzyme — sudah deprecated secara de facto di ekosistem modern).
- Prioritas sama seperti backend: cover dulu flow kritis (search →
  graph → evidence), bukan kejar coverage number.

---

# 4. API CONTRACT (BACKEND ↔ FRONTEND)

- Response JSON WAJIB konsisten formatnya di semua endpoint:
  ```json
  {
    "data": {},
    "meta": { "tenant_id": "...", "region_id": "..." },
    "errors": null
  }
  ```
- Error response WAJIB punya `code` (machine-readable) DAN `message`
  (human-readable), supaya frontend bisa branching logic tanpa parsing
  string.
- Setiap response yang mengandung data entity/relationship WAJIB
  menyertakan referensi evidence/source (selaras prinsip evidence-first
  dari Master Tracker) — ini bukan opsional per requirement governance
  yang sudah disepakati.

---

# 5. WORKFLOW RULES (KESEPAKATAN KERJA)

1. Satu langkah kerja per giliran chat, command siap-tempel (CMD
   Windows). Tidak ada bundling banyak perubahan dalam satu pesan.
2. Untuk file baru: buat file kosong dulu, baru buka editor.
3. Kalau ada keputusan kecil yang BELUM diatur di dokumen ini (nama
   variable ambigu, ukuran spacing baru, dll) → WAJIB tanya dulu ke
   user, tidak boleh menebak/berimprovisasi diam-diam.
4. Kalau user bingung sudah sampai mana / lupa progress → cek kondisi
   file aktual dulu (jangan menebak dari histori chat).
5. Revisi/bugfix dikumpulkan jadi satu kali ganti file lengkap per
   sesi kerja, bukan dicicil sedikit-sedikit.
6. Setiap penyimpangan dari dokumen ini (termasuk yang diminta user
   secara eksplisit) WAJIB dicatat di Deviation Log (§7) beserta
   tanggal dan alasan.
7. **Setiap rekomendasi teknis, keputusan arsitektur, dan urutan
   eksekusi WAJIB berbasis riset** (dokumentasi resmi tool/framework
   yang dipakai, atau referensi eksternal established) — bukan asumsi
   atau kebiasaan. Kalau riset menemukan urutan/pendekatan yang lebih
   benar dari rencana yang sudah disepakati sebelumnya, itu WAJIB
   dikoreksi saat itu juga (dijelaskan alasannya ke user), bukan
   dilanjutkan demi konsistensi dengan rencana lama. Ini yang mencegah
   utang teknis menumpuk dari keputusan yang sebenarnya belum matang.

---

# 6. SUMBER RISET

- PSR-12 Extended Coding Style Guide (PHP-FIG) — baseline gaya kode PHP.
- Laravel modular monolith architecture pattern (Laracasts "Modular
  Laravel" course; multiple 2026 Laravel 12 modular architecture
  guides) — struktur modul, event-driven cross-module communication.
- Laravel service/repository pattern best practices (multiple 2025-2026
  sumber) — kapan repository dibutuhkan vs Eloquent langsung.
- React/TypeScript feature-based folder structure — konsensus multiple
  sumber 2026 (Robin Wieruch, feature-sliced design pattern) sebagai
  standar aplikasi production skala menengah-besar.
- Working rules & governance principles: Master Tracker v1.1, Enterprise
  Software Architecture Blueprint v1.1 (dokumen internal proyek).
- UUID v7 vs v4 untuk PostgreSQL primary key — konsensus komunitas
  Postgres modern soal dampak insert performance & index fragmentation,
  serta dukungan native UUID v7 di Laravel 12.
- Cytoscape.js official documentation & performance guide (js.cytoscape.org,
  GitHub cytoscape/cytoscape.js) — strategi subset/filter graph besar
  dan performance hint untuk viewport manipulation.

---

# 7. DEVIATION LOG

| Tanggal | Aturan yang disimpangi | Alasan | Disetujui oleh |
|---|---|---|---|
| — | — | — | — |

