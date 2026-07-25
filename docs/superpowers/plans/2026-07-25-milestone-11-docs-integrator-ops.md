# Milestone 11 Docs Integrator & Ops Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship standalone Indonesian `docs/API_INTEGRATION.md` for lembaga integrators and a generic Indonesian `docs/DEPLOYMENT.md` runbook for operators, then sync SPEC/RULES contract drift and mark Milestone 11 complete.

**Architecture:** Documentation-only milestone. Two focused docs (integrator vs operator) with `PRODUCTION_NOTES.md` kept as the short app-level checklist. Facts must match running code/tests; SPEC/RULES get small sync edits from design §8. Follow RULES B1: implement → review → verify (not classic TDD). Verification is fact-checking against code, not new feature tests.

**Tech Stack:** Markdown docs; Laravel 13 API surface under `/api/v1`; PHP `/usr/local/Cellar/php/8.5.8/bin/php` only if a code fix is forced (default: no code changes).

**Spec:** `docs/superpowers/specs/2026-07-25-milestone-11-docs-integrator-ops-design.md` (DISETUJUI)

---

## File structure

| Path | Responsibility |
|------|----------------|
| `docs/API_INTEGRATION.md` | Create — panduan mandiri lengkap untuk integrator |
| `docs/DEPLOYMENT.md` | Create — runbook generik operator |
| `docs/PRODUCTION_NOTES.md` | Modify — pointer eksplisit ke kedua dokumen baru |
| `docs/SPEC.md` | Modify — sync §4 kontrak (fields sync, active_only, limits as defaults, contoh pesan, bentuk 422/404) |
| `docs/RULES.md` | Modify — sync A13.2 profil default; B4.2 same_site → `lax` |
| `docs/IMPLEMENTATION_TODO.md` | Modify — tandai M11 selesai |
| Code | **Jangan ubah** kecuali review menemukan fakta dokumen yang salah karena bug kode (bukan karena SPEC tertinggal) |

**Sumber fakta wajib (baca sebelum menulis):**

- `routes/api.php`
- `app/Support/Api/ApiErrorResponse.php`
- `app/Support/Api/ApiResourceCatalog.php`
- `app/Support/Api/ApiFieldProfiles.php`
- `app/Support/Api/ApiClientScopes.php`
- `app/Services/Api/ApiKeyParser.php`
- `app/Services/Api/ApiResourceLister.php`
- `app/Services/Api/ApiResourceSyncer.php`
- `app/Services/Api/ApiSyncQueryValidator.php`
- `app/Http/Middleware/ThrottleApiClient.php`
- `config/security.php`, `config/cors.php`, `.env.example`
- `docs/PRODUCTION_NOTES.md`
- Tests: `ApiClientAuthTest`, `ApiResourceListTest`, `ApiResourceSyncTest`

**PHP binary (hanya jika suite dijalankan):** `/usr/local/Cellar/php/8.5.8/bin/php`

**Placeholder rule:** di `DEPLOYMENT.md`, setiap langkah yang bergantung pada provider/VPS harus ditandai `(pilihan operator)` — jangan mengarang hostname, CIDR Cloudflare, perintah ACME, atau tool backup spesifik.

---

### Task 1: `API_INTEGRATION.md` — fondasi (auth, endpoint, profil)

**Files:**
- Create: `docs/API_INTEGRATION.md`

- [ ] **Step 1: Buat kerangka dokumen**

Buat file dengan heading berikut (Bahasa Indonesia):

```markdown
# Panduan integrasi API (fase 1)

Untuk developer aplikasi konsumen lembaga. Kontrak normatif: [SPEC.md](./SPEC.md) §2.2 dan §4. Ringkasan akses vs proxy: [PRODUCTION_NOTES.md](./PRODUCTION_NOTES.md).

## 1. Ringkasan
## 2. Quick start
## 3. Autentikasi
## 4. Endpoint dan envelope
## 5. Resource, scope, dan field profile
## 6. Tarik penuh
## 7. Sync delta
## 8. Error dan rate limit
## 9. Retry
## 10. Checklist sebelum production
```

Isi §1–§5 di Task 1; sisakan §6–§10 sebagai heading kosong atau stub satu baris `_(isi di Task 2)_`.

- [ ] **Step 2: Tulis §1 Ringkasan + §2 Quick start**

Wajib ada:

- Model **server-to-server** (backend lembaga → API dengan API key; browser tidak memanggil langsung).
- Base URL placeholder: `https://data.example.id`.
- Quick start tiga `curl`:
  1. `GET /api/v1/health` → exact `{"status":"ok"}`
  2. `GET /api/v1/me` dengan `X-API-Key: dc_live_exampleprefix_examplesecretexamplesecretexample`
  3. Satu `GET /api/v1/guru?per_page=1`

Contoh response `/me` harus memuat field: `lembaga_id`, `kode`, `nama`, `is_active`, `client_id`, `client_name`, `scopes`, `field_profile` (sesuai `MeController`).

- [ ] **Step 3: Tulis §3 Autentikasi**

Dokumentasikan:

| Fakta | Nilai |
|-------|-------|
| Header utama | `X-API-Key` |
| Alternatif | `Authorization: Bearer <key>` |
| Prioritas | jika keduanya ada → `X-API-Key` menang |
| Format | `dc_live_<prefix>_<secret>` |
| Plain key | hanya sekali saat buat/rotate di admin |
| 401 | `UNAUTHENTICATED` / `Autentikasi gagal.` |
| 403 client | `API_CLIENT_INACTIVE` |
| 403 lembaga | `LEMBAGA_INACTIVE` |

Sertakan contoh request 401 dengan envelope `{message, code, request_id}`.

- [ ] **Step 4: Tulis §4 Endpoint dan envelope**

Tabel endpoint:

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/v1/health` | tidak |
| GET | `/api/v1/me` | ya |
| GET | `/api/v1/{resource}` | ya |
| GET | `/api/v1/{resource}/sync` | ya |

Resource slug: `tahun-ajaran`, `guru`, `kelas`, `siswa`, `karyawan`.

Envelope list dan sync (salin bentuk field dari `ApiResourceLister` / `ApiResourceSyncer`). Catat: non-GET → **405**.

- [ ] **Step 5: Tulis §5 Resource, scope, field profile**

- Scope list dari `ApiClientScopes`.
- Profil: `minimal ⊂ academic ⊂ contact`.
- **Default efektif:** tanpa `fields` → pakai `field_profile` milik client (bukan selalu `minimal`).
- Meminta profil di atas ceiling → `403 FORBIDDEN` / `Profil field tidak diizinkan.`
- Tabel field per resource/profil dari `ApiResourceCatalog` (full lists agar mandiri).
- Embeds siswa: `penempatan_aktif` (academic+), `riwayat_penempatan` (contact).
- Format tanggal: `Y-m-d` untuk date-only; timestamp UTC `Y-m-d\TH:i:s\Z`.

- [ ] **Step 6: Verifikasi fakta Task 1**

Jalankan checklist manual (baca file kode, jangan menebak):

```bash
rg -n "health|resource|api.client" routes/api.php
rg -n "X-API-Key|Bearer|dc_live_" app/Services/Api/ApiKeyParser.php
rg -n "lembaga_id|field_profile|scopes" app/Http/Controllers/Api/V1/MeController.php
rg -n "tahun-ajaran|active_column|penempatan" app/Support/Api/ApiResourceCatalog.php
```

Expected: setiap klaim di §1–§5 punya bukti di kode.

- [ ] **Step 7: Commit**

```bash
git add docs/API_INTEGRATION.md
git commit -m "$(cat <<'EOF'
docs(m11): add API integration guide foundation

Document auth, endpoints, envelopes, scopes, and field profiles
for lembaga integrators against the live /api/v1 contract.
EOF
)"
```

---

### Task 2: `API_INTEGRATION.md` — tarik penuh, sync, error, retry

**Files:**
- Modify: `docs/API_INTEGRATION.md`

- [ ] **Step 1: Tulis §6 Tarik penuh**

Dokumentasikan query params:

| Param | Default | Catatan |
|-------|---------|---------|
| `include_deleted` | false | bila true, tambah `deleted_at` di tiap baris |
| `active_only` | false | filter `active_column`; **no-op untuk `kelas`**; `tahun-ajaran` pakai `is_aktif` |
| `fields` | profil client | `minimal` / `academic` / `contact` |
| `page` | 1 | ≥ 1 |
| `per_page` | 100 | **clamp** 1..200 (nilai >200 → 200, bukan 422) |

Urutan: `nama ASC, id ASC`; **`tahun-ajaran` = `nama DESC, id ASC`**.

Sertakan contoh response list lengkap + contoh pagination halaman 2.

- [ ] **Step 2: Tulis §7 Sync delta**

Alur wajib:

1. Halaman 1: `since` saja → dapat `watermark`
2. Lanjutan: `since` + `watermark` sama + `cursor`
3. Persist watermark/`synced_at` hanya jika `next_cursor === null`
4. `watermark` tanpa `cursor` → `INVALID_CURSOR`
5. Tombstone: `{id, deleted_at, changed_at}` saja
6. `SINCE_TOO_OLD` → fallback tarik penuh
7. Tidak ada sync terpisah `siswa_penempatan`
8. `fields` **didukung** di sync (ceiling sama dengan list)
9. Batas umur `since`: default 90 hari (`API_SYNC_MAX_SINCE_DAYS`) — sebut sebagai **default yang dapat dikonfigurasi**

Contoh wajib: response halaman 1 (dengan `next_cursor`), request halaman 2, satu tombstone, satu kasus `SINCE_TOO_OLD`.

- [ ] **Step 3: Tulis §8 Error dan rate limit**

Tabel kode bisnis dengan HTTP + pesan **persis dari kode**:

| Code | HTTP | Message |
|------|------|---------|
| `UNAUTHENTICATED` | 401 | `Autentikasi gagal.` |
| `API_CLIENT_INACTIVE` | 403 | `API client tidak aktif.` |
| `LEMBAGA_INACTIVE` | 403 | `Lembaga tidak aktif.` |
| `FORBIDDEN` | 403 | `Scope tidak mencukupi.` / `Profil field tidak diizinkan.` |
| `RATE_LIMITED` | 429 | `Terlalu banyak permintaan.` |
| `INVALID_SINCE` | 400 | `Parameter since tidak valid.` |
| `SINCE_TOO_OLD` | 400 | `Parameter since terlalu lama; gunakan tarik penuh.` |
| `INVALID_CURSOR` | 400 | `Cursor atau watermark tidak valid.` |

Catatan bentuk non-bisnis (wajib):

- Validasi FormRequest gagal → **422** Laravel `{message, errors}` (bukan envelope bisnis; `VALIDATION_FAILED` di SPEC untuk dashboard/admin).
- Slug resource tidak dikenal → **404** JSON framework (bukan `NOT_FOUND` envelope).
- `X-Request-ID`: klien boleh kirim; di-echo bila valid.
- Rate limit default 120/menit/key + 240/menit/IP (env-overridable). Header: hanya `Retry-After` pada 429; **tidak ada** `X-RateLimit-*`.

Contoh JSON 429 dengan `Retry-After`.

- [ ] **Step 4: Tulis §9 Retry + §10 Checklist**

Retry:

- 429 → tunggu `Retry-After`, ulangi request identik
- 5xx / timeout → backoff eksponensial + jitter
- Sync retry-safe dengan triplet `since`/`watermark`/`cursor` sama
- Jangan naikkan watermark sebelum `next_cursor === null`
- 401/403/400 → jangan retry membabi buta

Checklist production singkat: key di secret store, jangan log plain key, hormati scope/profil, uji sync multi-page, tangani 429, siap fallback full pull.

- [ ] **Step 5: Verifikasi fakta Task 2**

```bash
rg -n "per_page|active_only|include_deleted|nama DESC" app/Services/Api/ApiResourceLister.php
rg -n "watermark|next_cursor|deleted_at" app/Services/Api/ApiResourceSyncer.php app/Http/Controllers/Api/V1/ResourceSyncController.php
rg -n "SINCE_TOO_OLD|INVALID_CURSOR|api_sync_max_since_days" app/Services/Api/ApiSyncQueryValidator.php config/security.php
rg -n "Retry-After|RATE_LIMITED" app/Http/Middleware/ThrottleApiClient.php
rg -n "UNAUTHENTICATED|INVALID_SINCE|SINCE_TOO_OLD" app/Support/Api/ApiErrorResponse.php
```

Expected: semua klaim §6–§10 cocok. Scan dokumen untuk secret nyata / hostname production — tidak boleh ada.

- [ ] **Step 6: Commit**

```bash
git add docs/API_INTEGRATION.md
git commit -m "$(cat <<'EOF'
docs(m11): complete API pull, sync, error, and retry guide

Finish the standalone integrator guide with full-pull and delta-sync
examples, business error codes, and retry rules.
EOF
)"
```

---

### Task 3: `DEPLOYMENT.md` — instalasi hingga aset

**Files:**
- Create: `docs/DEPLOYMENT.md`

- [ ] **Step 1: Buat kerangka**

```markdown
# Deployment (fase 1)

Runbook generik untuk operator VPS. Ringkasan env app: [PRODUCTION_NOTES.md](./PRODUCTION_NOTES.md). Basis: [SPEC.md](./SPEC.md) §6, [RULES.md](./RULES.md) B3–B4 / B8.

Langkah yang bergantung pada provider/OS ditandai **(pilihan operator)** — jangan menganggap perintah contoh sebagai wajib.

## 1. Prasyarat
## 2. Topologi
## 3. Instalasi aplikasi
## 4. Env production
## 5. Migrasi dan Super Admin
## 6. Aset front-end dan cache
## 7. TLS, Cloudflare, dan trusted proxies
## 8. Firewall dan isolasi database
## 9. Backup, RPO/RTO, restore test
## 10. Verifikasi post-deploy
## 11. Incident response
## 12. Checklist go-live
```

Isi §1–§6 di Task 3; stub §7–§12 untuk Task 4.

- [ ] **Step 2: Tulis §1–§2 Prasyarat dan topologi**

Prasyarat akurat:

- Laravel **13.x** (`composer.json` `^13.8`)
- PHP `^8.3` (+ ekstensi Laravel/PhpSpreadsheet + **`pdo_pgsql`**)
- PostgreSQL **16** di jaringan privat
- Apache (mod_php **atau** php-fpm — pilihan operator); Nginx tidak dipakai
- Node.js cukup untuk `npm run build` (versi exact = pilihan operator; Vite 8 biasanya Node 20+)
- Document root = `public/`

Topologi:

```text
Internet → Cloudflare (full proxy) → Apache/VPS → PHP (Laravel) → PostgreSQL (localhost/private)
```

- [ ] **Step 3: Tulis §3–§5 Instalasi, env, migrasi**

Urutan perintah generik (sesuaikan path/user = pilihan operator):

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env   # lalu edit nilai production
php artisan key:generate
php artisan migrate --force
php artisan install:super-admin
```

Peringatan eksplisit:

- **Jangan** `php artisan db:seed` di production (seeder membuat test user).
- `API_KEY_PEPPER` wajib sebelum menerbitkan API key.
- `MFA_SUPER_ADMIN_REQUIRED=true` sebelum publik.
- `queue:work` / `schedule:run` **tidak wajib** fase 1 (tidak ada job/schedule di kode).
- `storage:link` opsional.

Env wajib: salin daftar dari `PRODUCTION_NOTES.md` + sebut `DB_*`, rate limit, `API_SYNC_MAX_SINCE_DAYS`, caveat log redaction hanya pada channel `single`/`daily`.

- [ ] **Step 4: Tulis §6 Aset dan cache**

```bash
npm ci   # atau npm install — pilihan operator
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permission writable `storage/` dan `bootstrap/cache` = **(pilihan operator / OS)** — jangan mengarang `chown`/`chmod` spesifik.

- [ ] **Step 5: Verifikasi Task 3**

```bash
rg -n '"php"|laravel/framework' composer.json
rg -n "install:super-admin" app/Console
test ! -d app/Jobs && echo "no jobs dir OK"
```

Expected: versi cocok; bootstrap = command; tidak ada jobs yang memaksa worker.

- [ ] **Step 6: Commit**

```bash
git add docs/DEPLOYMENT.md
git commit -m "$(cat <<'EOF'
docs(m11): add deployment runbook through app install

Document prerequisites, topology, env, migrate, Super Admin
bootstrap, and asset/cache steps without invented provider commands.
EOF
)"
```

---

### Task 4: `DEPLOYMENT.md` — TLS, backup, verifikasi, incident

**Files:**
- Modify: `docs/DEPLOYMENT.md`

- [ ] **Step 1: Tulis §7 TLS / Cloudflare / proxies**

Wajib:

- Cloudflare orange cloud / full proxy **wajib sebelum produksi publik**
- Origin sebaiknya hanya menerima traffic dari Cloudflare (**pilihan operator**: batasi IP / Authenticated Origin Pull)
- Isi `TRUSTED_PROXIES` dengan IP/CIDR ingress — **bukan** IP laptop lembaga
- Tanpa trusted proxies: `X-Forwarded-Proto` diabaikan → HSTS/cookie secure bisa salah
- `*` hanya jika app hanya bisa diakses lewat satu proxy tepercaya
- ACME/Let's Encrypt = **(pilihan operator)**

Rujuk `PRODUCTION_NOTES.md` untuk penjelasan akses lembaga vs proxies.

- [ ] **Step 2: Tulis §8 Firewall / DB**

- Buka 80/443; SSH dibatasi; fail2ban **(pilihan operator)**
- PostgreSQL **tidak** terbuka ke publik
- Apache request limits **(pilihan operator)**

- [ ] **Step 3: Tulis §9 Backup**

Tabel aturan terkunci:

| Aturan | Nilai |
|--------|-------|
| Dump | terjadwal |
| Enkripsi | wajib |
| Offsite | wajib, tidak publik |
| Retensi | ≥ 30 hari |
| RPO | ≤ 24 jam |
| RTO | target ≤ 4 jam |
| Restore test | sebelum go-live + berkala |

Tool/`pg_dump`/cron/storage = **(pilihan operator)**.

- [ ] **Step 4: Tulis §10–§12 Verifikasi, incident, checklist**

Post-deploy smoke:

1. `GET /api/v1/health` → `{"status":"ok"}`
2. Login admin + MFA Super Admin
3. Header keamanan / HSTS (dengan proxy tepercaya)
4. `APP_DEBUG=false` (error tidak bocor)
5. DB tidak publik

Incident (RULES B4.4):

1. Rotate API key
2. Nonaktifkan akun
3. Blok IP (**pilihan operator**)
4. Restore backup

Checklist go-live: gabungkan item di atas + Cloudflare aktif + backup restore sudah diuji + MFA on + pepper set.

- [ ] **Step 5: Scan placeholder & anti-duplikasi**

```bash
rg -n "pilihan operator|Cloudflare|TRUSTED_PROXIES|RPO|RTO" docs/DEPLOYMENT.md
rg -n "db:seed|queue:work|Laravel 15" docs/DEPLOYMENT.md || true
```

Expected: ada penanda pilihan operator; **tidak** ada anjuran `db:seed` prod; **tidak** mengklaim Laravel 15 sebagai versi.

- [ ] **Step 6: Commit**

```bash
git add docs/DEPLOYMENT.md
git commit -m "$(cat <<'EOF'
docs(m11): finish deployment TLS, backup, and incident runbook

Add Cloudflare/proxy guidance, backup RPO/RTO targets, post-deploy
smoke checks, and incident response steps for operators.
EOF
)"
```

---

### Task 5: Sync SPEC/RULES + pointer + tandai M11 selesai

**Files:**
- Modify: `docs/SPEC.md`
- Modify: `docs/RULES.md`
- Modify: `docs/PRODUCTION_NOTES.md`
- Modify: `docs/IMPLEMENTATION_TODO.md`

- [ ] **Step 1: Sync RULES**

Di `docs/RULES.md`:

1. A13.2 (~L105): ganti klaim "Default response API memakai profil `minimal`" menjadi: default efektif = profil yang di-assign ke API client; `minimal` adalah default DB untuk client baru; klien boleh meminta profil lebih rendah via `fields`.
2. B4.2 session (~L187): ganti "same_site ketat" menjadi `same_site=lax` (keputusan M10), tetap sebutkan `httpOnly` + `secure` di production.

- [ ] **Step 2: Sync SPEC §4**

Di `docs/SPEC.md`:

1. `active_only`: jelaskan memakai kolom aktif per resource; **tidak berlaku untuk `kelas`**; `tahun-ajaran` memakai `is_aktif`.
2. §4.4 sync: tambahkan parameter `fields` (ceiling sama dengan list).
3. Rate limit 120/menit dan umur `since` 90 hari: nyatakan sebagai **default** yang dapat diubah via env (`API_RATE_PER_MINUTE`, `API_IP_RATE_PER_MINUTE`, `API_SYNC_MAX_SINCE_DAYS`).
4. Contoh error message: samakan ke `Parameter since tidak valid.`
5. Catatan singkat di dekat tabel error: slug tidak dikenal → 404 framework; 422 validasi API → bentuk Laravel `{message, errors}`; `VALIDATION_FAILED` tetap untuk dashboard/admin.
6. Catatan watermark: tidak disimpan server; integrator wajib mengirim ulang nilai dari halaman 1.
7. Pointer ke `docs/API_INTEGRATION.md` di awal §4 (satu kalimat).

Jangan rewrite §4 secara besar — edit bertarget saja.

- [ ] **Step 3: Update PRODUCTION_NOTES pointer**

Di `docs/PRODUCTION_NOTES.md` bagian atas, pastikan pointer:

- Detail infra → `DEPLOYMENT.md`
- Panduan integrator → `API_INTEGRATION.md`

(Tambah baris bila belum ada tautan `API_INTEGRATION.md`.)

- [ ] **Step 4: Tandai IMPLEMENTATION_TODO M11**

Di `docs/IMPLEMENTATION_TODO.md` Milestone 11:

- Status: `**Selesai**` + tautan spek `2026-07-25-milestone-11-docs-integrator-ops-design.md` (style sama M9/M10)
- Centang semua item implementasi yang sudah benar-benar ada di dokumen
- Centang review wajib hanya setelah reviewer spek/kualitas approve di sesi eksekusi

- [ ] **Step 5: Verifikasi akhir**

```bash
test -f docs/API_INTEGRATION.md && test -f docs/DEPLOYMENT.md
rg -n "API_INTEGRATION|DEPLOYMENT" docs/PRODUCTION_NOTES.md docs/SPEC.md docs/IMPLEMENTATION_TODO.md
rg -n "Default response API memakai profil \`minimal\`" docs/RULES.md && echo "FAIL still old text" || echo "RULES profile OK"
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: suite penuh tetap hijau (saat ini 286 tests bila tidak ada perubahan kode).

- [ ] **Step 6: Commit**

```bash
git add docs/SPEC.md docs/RULES.md docs/PRODUCTION_NOTES.md docs/IMPLEMENTATION_TODO.md
git commit -m "$(cat <<'EOF'
docs(m11): sync contracts and mark documentation milestone done

Align SPEC/RULES with live API defaults, link the new integrator and
deployment guides, and close Milestone 11.
EOF
)"
```

---

## Self-review (penulis rencana)

| Spec requirement | Task |
|------------------|------|
| `API_INTEGRATION.md` lengkap mandiri | 1–2 |
| Auth header, scope, profil, tarik, sync, error, retry | 1–2 |
| `DEPLOYMENT.md` generik Apache/PHP/PG/Cloudflare/backup/incident | 3–4 |
| Placeholder, bukan perintah dikarang | 3–4 |
| Sync SPEC/RULES §8 | 5 |
| Pointer PRODUCTION_NOTES + TODO M11 | 5 |
| Tidak OpenAPI / tidak CORS fase 2 / tidak kode fitur | seluruh plan |
| Verifikasi fakta vs kode | setiap task Step verifikasi |

Placeholder scan: tidak ada TBD/TODO kosong di langkah; semua nilai dari kode dicantumkan.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-25-milestone-11-docs-integrator-ops.md`.

**Dua opsi eksekusi:**

1. **Subagent-Driven (disarankan)** — satu subagent per task, review spek + kualitas di antara task
2. **Inline Execution** — kerjakan berurutan di sesi ini dengan checkpoint

Yang mana?
