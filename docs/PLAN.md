# PLAN — Pusat Data

Status: **DISETUJUI — 18 Jul 2026**; revisi penguatan dari [AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md) sudah dimasukkan.  
Stack: **DISETUJUI** — Laravel + PostgreSQL + **Apache** di VPS; UI admin **Blade + Livewire**  
(Laravel disetujui 15 Jul 2026; web server dikoreksi ke Apache)  
Kode: **boleh dimulai** — PLAN, SPEC, RULES sudah disetujui  
Keamanan: standar **data center** (lihat RULES B4) — Cloudflare wajib sebelum produksi publik + MFA Super Admin + scoped API client + rate limit + firewall + harden origin  
Proses kode: tulis → **review jujur & jelas** → perbaiki temuan → **baru tes**

---

## 1. Ringkasan produk

Pusat Data adalah sistem **master data** lembaga pendidikan.

- Data diisi & diubah hanya di Pusat Data (Super Admin + Admin Lembaga).
- Aplikasi lain (absensi, perangkat, administrasi, dll.) **menarik** dan **menyinkronkan** data.
- Sinkron memakai mode **delta** (hanya yang berubah).
- Akses aplikasi: **API key per API client aplikasi konsumen** yang terikat ke satu lembaga, dengan scope resource dan profil field.

Dokumen terkait:

| Dokumen | Isi |
|---------|-----|
| [SPEC.md](./SPEC.md) | Entitas, field, API, auth, sync |
| [RULES.md](./RULES.md) | Aturan bisnis & aturan implementasi |
| [RENCANA.md](./RENCANA.md) | Catatan kesepakatan awal (riwayat diskusi) |
| [AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md) | Audit lanjutan sebelum coding dan dasar revisi penguatan |

---

## 2. Tujuan fase 1

1. Super Admin dapat mengelola lembaga dan Admin Lembaga.
2. Admin Lembaga dapat mengelola master: Guru, Siswa, Karyawan, Kelas, Tahun ajaran.
3. Setiap lembaga dapat punya beberapa API client/key untuk aplikasi konsumen berbeda.
4. Aplikasi konsumen bisa **tarik penuh** dan **sinkron delta** dengan watermark + cursor.
5. Sistem ter-deploy di **VPS**.

Di luar fase 1: import Excel massal, realtime push, ubah master dari app lain, OAuth/SSO, modul bisnis app (nilai, absensi harian, dll.).

---

## 3. Pengguna & tanggung jawab

| Peran | Tanggung jawab fase 1 |
|-------|------------------------|
| Super Admin | Buat lembaga, buat/nonaktifkan Admin Lembaga, lihat semua lembaga, kelola API client/key lembaga |
| Admin Lembaga | CRUD master data miliknya: Guru, Siswa, Karyawan, Kelas, Tahun ajaran |
| Integrator app | Pakai API client/key sesuai scope + tombol Tarik / Sinkron di aplikasi masing-masing |

---

## 4. Tahapan kerja (tanpa coding dulu → lalu coding)

### Tahap A — Desain ✅ selesai

- [x] Klarifikasi kebutuhan
- [x] Audit UI/UX/backend/keamanan ([AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md))
- [x] Revisi dokumen sesuai audit (18 Jul 2026)
- [x] Konfirmasi D9 (import Excel → fase 2) & D10 (field SPEC §3)
- [x] Audit lengkap sebelum coding + revisi penguatan ([AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md))
- [x] **DISETUJUI — 18 Jul 2026**

### Tahap B — Persiapan teknis ← **mulai di sini**

- Setup repo Laravel
- Setup database PostgreSQL
- Desain migrasi sesuai SPEC, termasuk UUID, composite tenant constraints, partial unique, index sync, API client, MFA, dan audit log
- Setup deploy VPS (**Apache** + PHP + Postgres)
- Hardening keamanan sesuai RULES B4 (HTTPS, MFA Super Admin, firewall, rate limit, anti-DDoS/abuse, backup offsite)

### Tahap C — Inti Admin

- Bootstrap Super Admin pertama (`install:super-admin` atau seeder)
- Auth Super Admin & Admin Lembaga (+ reset password) dan MFA/TOTP Super Admin
- CRUD Lembaga + API client/key (scope resource, profil field, flow copy-once + konfirmasi rotate/revoke)
- CRUD master scoped lembaga — urutan UX: Tahun ajaran → Guru → Kelas → Siswa → Karyawan
- UI admin Bahasa Indonesia; list dengan search/pagination; empty & error state
- Audit log aksi kritis (user/API client/key/lembaga)

### Tahap D — API konsumsi + keamanan API

- Endpoint tarik penuh
- Endpoint sinkron delta (`since`, `watermark`, `cursor`)
- Scope resource, profil field, minimisasi PII, error code resmi
- Rate limiting berlapis & proteksi abuse
- Dokumentasi integrasi untuk app lain, termasuk retry dan penyimpanan watermark

### Tahap E — Review, tes, UAT & go-live VPS

- Review kode (jelas & jujur) → perbaiki temuan → baru tes
- Uji alur Super Admin → Admin Lembaga → App tarik/sinkron
- Checklist keamanan (HTTPS, firewall, throttle, isolasi lembaga, MFA, scoped API client)
- Hardening produksi (backup DB terenkripsi/offsite, env secrets, uji restore)

---

## 5. Deliverable fase 1

1. Dashboard web admin (Super Admin & Admin Lembaga) — layout, navigasi, form/list standar (SPEC §5)
2. REST API baca + sync untuk aplikasi konsumen (scope, field profile, limit, watermark/cursor, edge case terdefinisi)
3. Database PostgreSQL berisi master data + API client + audit log + constraint tenant
4. Dokumentasi integrasi tombol Tarik / Sinkron untuk app konsumen
5. Instance hidup di VPS (staging privat; produksi + Cloudflare)

---

## 6. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Field master kurang/berlebih | Koreksi SPEC sebelum coding |
| App lama sulit pakai delta | Sediakan juga tarik penuh |
| API key bocor | Key per API client, scope sempit, prefix + HMAC digest, rotate/revoke, HTTPS, audit log |
| Admin lembaga lihat data lembaga lain | Enforce scope `lembaga_id` di policy + composite tenant constraints DB + tes wajib |
| DDoS / abuse API | Cloudflare (direncanakan) + rate limit app + Apache + firewall/fail2ban; origin jangan dibypass |
| Skip review → bug lolos ke tes | RULES B1/B2: review wajib sebelum tes |
| Sync delta miss record / payload besar | Validasi `since`; watermark + cursor `(changed_at, id)`; fallback tarik penuh |
| Rotate API key putuskan app produksi | API client terpisah per aplikasi, modal peringatan, audit log, dokumentasi urutan rotate aman |
| Admin input ribuan siswa manual lambat | Import Excel **fase 2** (D9); fase 1: search + pagination + input form |
| Kebocoran PII ke integrasi yang tidak perlu | Field profile minimal/default; `contact` hanya dengan scope eksplisit |
| Kehilangan data produksi | Backup terenkripsi offsite, RPO max 24 jam, RTO target 4 jam, uji restore |

---

## 7. Cara koreksi dokumen ini

Saat review, sebutkan: **file + nomor bagian + usulan perubahan**.  
Contoh: `SPEC.md §3.2 Guru — tambah field NUPTK`.

Tidak ada implementasi kode sebelum Plan, Spec, Rule disetujui. **Status: sudah disetujui 18 Jul 2026 — coding boleh dimulai (Tahap B).**
