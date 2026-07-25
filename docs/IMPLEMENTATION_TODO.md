# IMPLEMENTATION TODO - Pusat Data

Status: **Rencana kerja coding fase 1**  
Tanggal dibuat: 18 Jul 2026  
Basis requirement: [PLAN.md](./PLAN.md), [SPEC.md](./SPEC.md), [RULES.md](./RULES.md), [AUDIT_LENGKAP_2026-07-18.md](./AUDIT_LENGKAP_2026-07-18.md)

Dokumen ini adalah checklist eksekusi coding. Jika ada konflik, ikuti urutan: RULES -> SPEC -> PLAN -> dokumen ini.

## Prinsip kerja wajib

1. Setiap perubahan kode harus kecil, jelas, dan sesuai scope tahap aktif.
2. Setelah menulis kode: **review kode dulu**, perbaiki temuan, baru jalankan test.
3. Temuan keamanan, tenant isolation, secret leak, dan PII exposure tidak boleh ditunda.
4. `.env`, API key plain, secret, `vendor/`, `node_modules/`, dan build output tidak boleh masuk git.
5. Setiap tahap selesai hanya jika acceptance criteria dan test terkait sudah hijau.

## Siklus selesai task

Untuk setiap task coding:

1. Tulis/ubah kode sesuai milestone aktif.
2. Review kode secara jujur sesuai RULES B2.
3. Perbaiki semua temuan review.
4. Jalankan test yang relevan.
5. Jika test lulus, update checklist di dokumen ini.
6. Commit dengan pesan jelas.
7. Push ke remote.

Jika test gagal, jangan update checkbox selesai dan jangan commit hasil yang belum beres. Untuk perubahan dokumentasi saja, test aplikasi tidak wajib, tetapi isi dan link dokumen tetap harus direview sebelum commit/push.

## Milestone 0 - Baseline stack

Status: **Selesai**

- [x] Laravel terpasang di root repo.
- [x] Livewire terpasang.
- [x] PostgreSQL lokal terpasang dan database `pusat_data` dibuat.
- [x] `.env.example` memakai `DB_CONNECTION=pgsql`.
- [x] Dokumen referensi dipindahkan ke `docs/`.
- [x] `npm run build` lulus.
- [x] `php artisan test` bawaan lulus.
- [x] Baseline sudah commit dan push.

## Milestone 1 - Fondasi database dan model

Status: **Selesai**

Tujuan: struktur data kuat sebelum UI/API.

- [x] Buat migration UUID helper/pattern untuk semua tabel master.
- [x] Update tabel `users`: role, `lembaga_id`, `is_active`, field MFA, constraint role.
- [x] Buat tabel `lembaga`.
- [x] Buat tabel `api_clients`.
- [x] Buat tabel `tahun_ajaran`.
- [x] Buat tabel `guru`.
- [x] Buat tabel `kelas`.
- [x] Buat tabel `siswa`.
- [x] Buat tabel `karyawan`.
- [x] Buat tabel `audit_logs`.
- [x] Tambahkan soft delete di semua master.
- [x] Tambahkan partial unique NIS/NISN siswa per lembaga.
- [x] Tambahkan unique tahun ajaran per lembaga.
- [x] Tambahkan unique kelas per lembaga + tahun ajaran + nama.
- [x] Tambahkan composite unique `(lembaga_id, id)` pada tabel tenant.
- [x] Tambahkan composite FK tenant untuk relasi kelas, siswa, wali kelas, tahun ajaran.
- [x] Tambahkan index sync `(lembaga_id, updated_at, id)` dan `(lembaga_id, deleted_at, id)`.
- [x] Buat Eloquent model, relation, casts, fillable/guarded, hidden field sensitif.

Review wajib:

- [x] Review migration untuk tenant isolation dan constraint DB.
- [x] Review model agar field sensitif tidak keluar di array/JSON.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Migration fresh berhasil di PostgreSQL.
- [x] Constraint role user bekerja.
- [x] Composite FK menolak relasi lintas lembaga.
- [x] Partial unique NIS/NISN bekerja.
- [x] Hanya satu tahun ajaran aktif per lembaga.

## Milestone 2 - Audit log, utility security, dan bootstrap Super Admin

Status: **Selesai**

Tujuan: tindakan penting tercatat sejak awal.

- [x] Buat service audit log append-only.
- [x] Buat middleware/request helper `request_id`.
- [x] Buat redaction helper untuk secret/PII di metadata.
- [x] Buat command `install:super-admin`.
- [x] Command hanya boleh membuat Super Admin pertama jika belum ada.
- [x] Password policy minimal 12 karakter.
- [x] Siapkan struktur TOTP/MFA Super Admin.
- [x] Simpan recovery code MFA dalam bentuk hash.

Review wajib:

- [x] Review command bootstrap agar tidak bisa overwrite Super Admin.
- [x] Review audit log agar tidak menyimpan password/API key/PII penuh.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] `install:super-admin` sukses saat belum ada Super Admin.
- [x] `install:super-admin` ditolak saat Super Admin sudah ada.
- [x] Audit log mencatat aksi kritis tanpa secret.
- [x] Password pendek ditolak.

## Milestone 3 - Auth admin dan authorization multi-tenant

Status: **Selesai**

Tujuan: Super Admin dan Admin Lembaga punya batas akses yang benar.

- [x] Implement login/logout session admin.
- [x] Implement throttle login 5/menit/email+IP dan limit tambahan per IP.
- [x] Implement MFA/TOTP wajib untuk Super Admin sebelum produksi publik.
- [x] Implement middleware user aktif.
- [x] Admin Lembaga lembaga nonaktif tidak bisa login.
- [x] Implement Gate/Policy role.
- [x] Implement tenant scope untuk semua CRUD master (scope + policies foundation; CRUD UI di M5/M6).
- [x] Invalidate session saat user dinonaktifkan (middleware + SessionInvalidator; hook CRUD M5 menyusul).
- [x] Pesan login generik: "Email atau password salah".

Review wajib:

- [x] Review semua middleware dan policy.
- [x] Review query list/detail agar Admin Lembaga tidak bisa lintas lembaga.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Super Admin bisa akses semua lembaga.
- [x] Admin Lembaga hanya akses lembaga sendiri.
- [x] Admin Lembaga tidak bisa akses record lembaga lain via URL/manual request.
- [x] Lembaga nonaktif membuat Admin Lembaga ditolak login.
- [x] Login throttle bekerja.
- [x] MFA Super Admin aktif dan wajib sebelum produksi publik.

### Follow-up (belum dikerjakan — bukan blocker M3)

- [ ] Evolusi UI auth ke **hybrid Livewire** (opsi C): challenge MFA / form login interaktif bila dibutuhkan UX lebih kaya. **Jangan dibuka ulang sebagai “M3 belum selesai”.** Kerjakan saat spek hybrid disetujui (kemungkinan berdampingan M5/M6), dengan Blade+controller M3 tetap sebagai fondasi otorisasi/session.

## Milestone 4 - UI shell dan dashboard admin

Status: **Selesai**

Tujuan: fondasi UI konsisten sebelum CRUD detail.

- [x] Buat layout Blade/Livewire admin.
- [x] Sidebar per role.
- [x] Header user/lembaga/logout.
- [x] Breadcrumb dan judul halaman.
- [x] Komponen tombol, input, select, badge, modal, table, pagination.
- [x] Empty state, loading state, dan inline validation state (empty-state + skeleton; input/select mendukung prop `error` + `aria-invalid`).
- [x] Dashboard Super Admin: jumlah lembaga aktif/nonaktif, API client aktif, ringkasan master.
- [x] Dashboard Admin Lembaga: urutan input data dan ringkasan master.

Review wajib:

- [x] Review UI untuk bahasa Indonesia, aksesibilitas dasar, dan konsistensi komponen.
- [x] Review agar UI tidak menjadi satu-satunya enforcement otorisasi.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Smoke test halaman dashboard.
- [x] Snapshot/manual review layout desktop dan tablet (verifikasi browser lokal 20 Jul 2026; layout grid diperbaiki; responsive drawer ≤960px).
- [x] Navigasi sidebar sesuai role.

### Follow-up (belum dikerjakan — bukan blocker M4)

- [ ] Evolusi shell/komponen ke **hybrid Livewire** (opsi C): modal konfirmasi, table search/filter, form dinamis memakai Livewire di atas layout Blade M4. **Jangan mundur menandai M4 belum selesai.** Jadwalkan saat CRUD (M5/M6) membutuhkan interaksi itu; komponen Blade `x-ui.*` tetap dipakai atau dibungkus Livewire.

## Milestone 5 - CRUD Super Admin

Status: **M5a+M5b selesai**

Keputusan arah (sementara):
- Spek pertama: **Lembaga + Admin Lembaga** (Blade + controller/Form Request) — **M5a selesai** (spek [2026-07-20-milestone-5a-lembaga-admin-design.md](./superpowers/specs/2026-07-20-milestone-5a-lembaga-admin-design.md)).
- Generate/reset password Admin Lembaga copy-once selesai di M5a.
- **API client/key** → spek M5b terpisah (setelah blok lembaga/admin hijau) — **M5b selesai** (spek [2026-07-21-milestone-5b-api-client-design.md](./superpowers/specs/2026-07-21-milestone-5b-api-client-design.md)). Endpoint REST `/api/*` konsumen tetap terbuka di **Milestone 7**.
- UI CRUD M5a: **Blade murni** (opsi A); hybrid Livewire (opsi C) tercatat sebagai follow-up M3/M4 di atas, dikerjakan ketika modal/list interaktif benar-benar dibutuhkan — **bukan** dengan mengulang M3/M4 dari awal.

Tujuan: pengelolaan lembaga, admin lembaga, dan API client/key.

- [x] CRUD lembaga.
- [x] Aktif/nonaktif lembaga dengan modal dampak.
- [x] CRUD Admin Lembaga.
- [x] Aktif/nonaktif Admin Lembaga.
- [x] Generate/reset password Admin Lembaga copy-once.
- [x] Buat API client per aplikasi konsumen.
- [x] Generate API key copy-once.
- [x] Rotate API key dengan modal dampak; key lama langsung revoke.
- [x] Revoke/nonaktifkan API client.
- [x] Admin Lembaga hanya lihat nama client, prefix, scope, status, last used.
- [x] Audit log untuk aksi kritis lembaga/admin.
- [x] Audit log untuk aksi kritis API client/key (M5b).

Review wajib:

- [x] Review copy-once API key agar plain key tidak pernah tersimpan.
- [x] Review digest HMAC + `hash_equals`.
- [x] Review modal destruktif.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Plain API key hanya muncul saat create/rotate.
- [x] DB hanya menyimpan prefix + digest.
- [x] Rotate mematikan key lama.
- [x] Admin Lembaga tidak bisa rotate/revoke.
- [x] Audit log tercatat tanpa secret.

## Milestone 6 - CRUD master Admin Lembaga

Status: **M6 selesai (M6a + M6b)** — spek [M6a](./superpowers/specs/2026-07-21-milestone-6a-master-ta-guru-karyawan-design.md) · [M6b](./superpowers/specs/2026-07-22-milestone-6b-kelas-siswa-design.md)

Tujuan: master data fase 1 lengkap dan scoped.

Keputusan arah: Milestone 6 dipecah — spek M6a Tahun ajaran + Guru + Karyawan selesai. **M6b** = Kelas + Siswa (import kelas; import siswa dari detail kelas; NIS wajib; NISN opsional; hard delete kelas kosong).

UAT lokal (22 Jul 2026): edit & hapus guru berhasil; import guru + auto NIY dipakai operasional.

- [x] CRUD Tahun Ajaran.
- [x] Aktifkan satu tahun ajaran per lembaga dalam transaksi.
- [x] CRUD Guru. *(termasuk import Excel, template 2 sheet, auto NIY, tahun masuk; UAT edit/hapus OK)*
- [x] CRUD Kelas. *(M6b)*
- [x] Validasi kelas wajib punya tahun ajaran milik lembaga yang sama. *(M6b)*
- [x] Validasi wali kelas guru milik lembaga yang sama. *(M6b)*
- [x] Blok soft delete kelas yang masih dipakai siswa. *(M6b — hard delete kelas kosong)*
- [x] CRUD Siswa. *(M6b)*
- [x] Siswa boleh tanpa kelas, tampil badge "Belum ada kelas". *(M6b)*
- [x] Validasi siswa `kelas_id` dan `tahun_ajaran_id` cocok. *(M6b)*
- [x] CRUD Karyawan. *(disamakan dengan guru: import Excel, template 2 sheet, auto NIK format NIY, tahun masuk)*
- [x] Search/pagination list guru & siswa.
- [x] Soft delete dengan modal konfirmasi. *(tahun ajaran, guru, karyawan — UAT guru hapus OK)*
- [x] Audit log akses/view PII admin sesuai RULES. *(`master.view` pada show guru/karyawan, tanpa dump PII)*

Review wajib (M6a):

- [x] Review semua query CRUD tahun ajaran/guru/karyawan untuk tenant scope.
- [x] Review validasi relasi lembaga (guru/karyawan scoped `lembaga_id` dari auth, bukan input klien).
- [x] Review UX form dan pesan validasi bahasa Indonesia.
- [x] Perbaiki semua temuan review sebelum test.

Review wajib (M6b):

- [x] Review Kelas/Siswa dan relasinya.

Test wajib:

- [x] Admin Lembaga A tidak bisa CRUD data lembaga B. *(tahun ajaran/guru/karyawan/kelas/siswa)*
- [x] Kelas lintas lembaga ditolak. *(M6b)*
- [x] Wali kelas lintas lembaga ditolak. *(M6b)*
- [x] Delete kelas berisi siswa diblok. *(M6b)*
- [x] Satu tahun ajaran aktif per lembaga.
- [x] Partial unique NIS/NISN berjalan. *(M6b)*

## Milestone 6c - Lifecycle siswa

Status: **Selesai** — spek [2026-07-22-milestone-6c-siswa-lifecycle-design.md](./superpowers/specs/2026-07-22-milestone-6c-siswa-lifecycle-design.md)

Tujuan: lifecycle siswa (status operasional + histori penempatan/enrollment + kenaikan kelas batch + aksi per siswa) untuk Admin Lembaga, tanpa endpoint REST (tetap M7). Disisipkan **sebelum Milestone 7**; Milestone 6 (a+b) tetap dianggap selesai.

Keputusan arah: hybrid data — `status_siswa` + metadata singkat di `siswa`; tabel `siswa_penempatan` sebagai enrollment penuh; `kelas_id`/`tahun_ajaran_id` tetap snapshot terkini yang di-mirror dari penempatan terbuka. UI **Admin Lembaga saja**; Super Admin → 403. Stack Blade + controller + Form Request.

- [x] Migration: kolom status siswa (`status_siswa`, `status_at`, `status_alasan`, `status_asal`, `status_tujuan`) + index `(lembaga_id, status_siswa)`.
- [x] Migration: tabel `siswa_penempatan` (composite FK tenant, partial unique satu baris terbuka per siswa) + backfill `jenis=awal` untuk siswa berkelas.
- [x] Model `SiswaPenempatan` + factory (baris terbuka/tertutup).
- [x] `SiswaLifecycleService`: transisi status, tutup/buka penempatan, sync snapshot `kelas_id`/`tahun_ajaran_id`, row-lock siswa saat mutasi.
- [x] Aksi per siswa di UI show: tempatkan, pindah kelas, mutasi keluar, luluskan, set status; panel riwayat penempatan read-only.
- [x] Blok perubahan kelas lewat edit master biasa (harus lewat aksi lifecycle).
- [x] Guard activate/deactivate agar tidak menabrak status lifecycle (`mutasi_keluar`/`lulus`).
- [x] Import siswa dari detail kelas menghasilkan `status=aktif` + enrollment `jenis=awal`.
- [x] Filter list siswa by `status_siswa` + badge status + badge "Belum ada kelas".
- [x] `KenaikanKelasService`: kenaikan/mutasi batch atomik (validasi semua baris → satu transaksi; gagal → rollback + pesan per baris).
- [x] Wizard kenaikan batch (entry dari detail kelas) untuk Admin Lembaga; Super Admin 403.
- [x] Audit log event lifecycle & kenaikan tanpa dump PII.
- [x] Update SPEC §3.8 (status + `siswa_penempatan` §3.8.1) dan relokasi item enrollment di §7.
- [x] Sisipkan checklist M6c ini sebelum Milestone 7.

Review wajib:

- [x] Review transisi status & atomicity batch.
- [x] Review tenant scope service lifecycle & kenaikan (lembaga A tidak bisa proses lembaga B).
- [x] Review konsistensi snapshot `kelas_id`/`tahun_ajaran_id` vs penempatan terbuka.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Tenant: lembaga A tidak bisa lifecycle siswa lembaga B.
- [x] Transisi status ilegal ditolak.
- [x] Paling banyak satu penempatan terbuka per siswa.
- [x] Snapshot `kelas_id`/`tahun_ajaran_id` konsisten setelah naik/pindah/keluar/lulus.
- [x] Batch kenaikan atomic (semua sukses atau rollback penuh).
- [x] Import siswa ke kelas menghasilkan `aktif` + enrollment awal.
- [x] Backfill migration tidak merusak siswa tanpa kelas.
- [x] Super Admin ditolak (403) dari route kenaikan/lifecycle.

## Milestone 7 - API client authentication — **selesai**

Tujuan: integrasi aplikasi konsumen aman dan scoped.

Spek: [2026-07-23-milestone-7-api-client-auth-design.md](./superpowers/specs/2026-07-23-milestone-7-api-client-auth-design.md).

Catatan: smoke endpoint `GET /api/v1/health` dan `GET /api/v1/me` sudah disertakan di M7 sebagai smoke autentikasi; endpoint daftar resource (`/api/v1/guru`, dst.) tetap di **Milestone 8**.

- [x] Middleware API key mendukung `X-API-Key`.
- [x] Opsional dukung `Authorization: Bearer`.
- [x] Parse format `dc_live_<prefix>_<secret>`.
- [x] Lookup prefix unik.
- [x] Verifikasi digest HMAC dengan `hash_equals`.
- [x] Tolak key invalid dengan 401 generik.
- [x] Tolak API client inactive/revoked dengan 403.
- [x] Tolak lembaga inactive dengan 403 `LEMBAGA_INACTIVE`.
- [x] Update `last_used_at` dan `last_used_ip`.
- [x] Rate limit 120/menit/key + limit tambahan per IP.
- [x] Pastikan header auth tidak pernah masuk log aplikasi.

Review wajib:

- [x] Review middleware untuk timing-safe compare dan secret handling.
- [x] Review rate limit key/IP.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Tanpa key ditolak 401.
- [x] Key salah ditolak 401.
- [x] Key revoked ditolak 403.
- [x] Lembaga inactive ditolak 403.
- [x] Key A tidak bisa baca lembaga B.
- [x] Rate limit bekerja.

## Milestone 8 - API tarik penuh — **selesai**

Tujuan: aplikasi konsumen bisa mengambil snapshot resource sesuai scope.

Spek: [2026-07-24-milestone-8-api-full-pull-design.md](./superpowers/specs/2026-07-24-milestone-8-api-full-pull-design.md).

Catatan: `GET /api/v1/health` dan `GET /api/v1/me` sudah dikerjakan di **M7** (tidak diulang). Arsitektur M8: `ApiResourceCatalog` + `ApiFieldProfiler` + `ApiResourceLister` + `ApiResourceTransformer` di belakang satu `ResourceListController` (`GET /api/v1/{resource}`).

- [x] `GET /api/v1/health` tanpa info internal. _(M7)_
- [x] `GET /api/v1/me`. _(M7)_
- [x] `GET /api/v1/guru`.
- [x] `GET /api/v1/siswa`.
- [x] `GET /api/v1/karyawan`.
- [x] `GET /api/v1/kelas`.
- [x] `GET /api/v1/tahun-ajaran`.
- [x] Enforce scope resource, contoh `siswa:read`.
- [x] Enforce field profile `minimal`, `academic`, `contact`.
- [x] Query `include_deleted`.
- [x] Query `active_only`.
- [x] Query `fields` hanya jika diizinkan client.
- [x] Pagination default 100, max 200 (clamp, bukan 422).
- [x] Response timestamp ISO-8601 UTC.
- [x] Error body konsisten `message`, `code`, `request_id`.

Review wajib:

- [x] Review field mapping agar tidak ada PII berlebih (field list per profil terkunci di catalog §6.2).
- [x] Review query untuk N+1 dan index (eager load `penempatanAktif`/`penempatans` `withoutGlobalScopes`).
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Scope resource membatasi endpoint.
- [x] Field profile minimal tidak mengirim kontak/alamat/wali.
- [x] `per_page > 200` dibatasi/ditolak sesuai implementasi (clamp ke 200).
- [x] `include_deleted` mengirim soft deleted bila diizinkan.
- [x] Error code resmi sesuai SPEC.
- [x] Embed siswa bertingkat (`penempatan_aktif` di academic, `riwayat_penempatan` di contact).
- [x] Isolasi tenant lewat `withoutGlobalScopes()` + `lembaga_id`.

## Milestone 9 - API sync delta

Status: **Selesai** — spek [2026-07-24-milestone-9-api-sync-delta-design.md](./superpowers/specs/2026-07-24-milestone-9-api-sync-delta-design.md)

Tujuan: sinkronisasi tidak melewatkan data saat perubahan banyak.

- [x] `GET /api/v1/{resource}/sync`.
- [x] Validasi `since` wajib ISO-8601 UTC.
- [x] Tolak `since` masa depan.
- [x] Tolak `since` lebih dari 90 hari dengan `SINCE_TOO_OLD`.
- [x] Hitung `changed_at = greatest(updated_at, deleted_at)`.
- [x] Buat watermark server pada awal sync.
- [x] Query `changed_at > since` dan `changed_at <= watermark`.
- [x] Cursor pagination berbasis `(changed_at, id)`.
- [x] `next_cursor` dikirim jika masih ada data.
- [x] Tombstone delete hanya field minimum.
- [x] App integrator di docs diarahkan menyimpan watermark hanya saat `next_cursor = null`.

Review wajib:

- [x] Review logic cursor untuk race condition.
- [x] Review soft delete tombstone agar tidak dump PII.
- [x] Review performa index.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Create/update/delete muncul di sync.
- [x] Data tidak terlewat saat jumlah perubahan lebih dari `per_page`.
- [x] Update baru setelah watermark tidak masuk batch lama.
- [x] Cursor invalid ditolak.
- [x] Tombstone delete minim PII.

## Milestone 10 - Hardening aplikasi

Status: **Selesai** — spek [2026-07-25-milestone-10-app-hardening-design.md](./superpowers/specs/2026-07-25-milestone-10-app-hardening-design.md)

Tujuan: baseline security production-ready sebelum staging publik.

- [x] Security headers middleware: HSTS production, X-Content-Type-Options, X-Frame-Options/CSP dasar, Referrer-Policy.
- [x] CORS default deny untuk browser origin.
- [x] APP_DEBUG false di production template/deploy notes.
- [x] Session production: secure, httpOnly, same_site=lax.
- [x] Logging redaction untuk auth/API key/password/token.
- [x] Error production tidak bocor stack trace.
- [x] Health endpoint tetap minimal.
- [x] Tambah dokumentasi env production.

Review wajib:

- [x] Review config production dan middleware header.
- [x] Review log redaction.
- [x] Perbaiki semua temuan review sebelum test.

Test wajib:

- [x] Header keamanan muncul di response.
- [x] CORS browser default deny.
- [x] Secret tidak muncul di response/log aplikasi.
- [x] Health endpoint hanya `{ "status": "ok" }`.

## Milestone 11 - Dokumentasi integrator dan operasional

Status: **Selesai** — spek [2026-07-25-milestone-11-docs-integrator-ops-design.md](./superpowers/specs/2026-07-25-milestone-11-docs-integrator-ops-design.md)

Tujuan: aplikasi konsumen dan operator punya panduan jelas.

- [x] Buat `docs/API_INTEGRATION.md`.
- [x] Dokumentasikan auth header.
- [x] Dokumentasikan scope dan field profile.
- [x] Dokumentasikan tarik penuh.
- [x] Dokumentasikan sync watermark/cursor.
- [x] Dokumentasikan error code.
- [x] Dokumentasikan retry untuk 429/5xx.
- [x] Buat `docs/DEPLOYMENT.md`.
- [x] Dokumentasikan Apache + PHP + PostgreSQL.
- [x] Dokumentasikan Cloudflare wajib sebelum produksi publik.
- [x] Dokumentasikan backup terenkripsi/offsite, RPO/RTO, restore test.
- [x] Dokumentasikan response incident ringkas: rotate key, disable akun, blok IP, restore backup.

Review wajib:

- [x] Review dokumentasi integrator dengan contoh request/response.
- [x] Review deployment checklist agar tidak membuka DB ke publik.

## Milestone 12 - Final review, UAT, dan go-live checklist

Tujuan: memastikan fase 1 layak digunakan.

- [ ] Review kode penuh per kategori: correctness, security, tenant isolation, performance, PII.
- [ ] Perbaiki semua temuan review.
- [ ] Jalankan full test suite.
- [ ] UAT alur Super Admin.
- [ ] UAT alur Admin Lembaga.
- [ ] UAT tarik penuh dari API client.
- [ ] UAT sync delta multi-page.
- [ ] UAT rotate/revoke API key.
- [ ] UAT lembaga inactive.
- [ ] Verifikasi backup restore.
- [ ] Verifikasi HTTPS + Cloudflare + firewall + fail2ban di VPS.
- [ ] Tag release fase 1 setelah disetujui.

## Definisi selesai fase 1

Fase 1 dianggap selesai hanya jika:

- [ ] Semua milestone 1-12 selesai.
- [ ] Tidak ada temuan kritis/tinggi yang belum diperbaiki.
- [ ] Test wajib di RULES B7 hijau.
- [ ] Dokumentasi integrator tersedia.
- [ ] Checklist hardening produksi hijau.
- [ ] Pemilik kebutuhan menyetujui UAT.
