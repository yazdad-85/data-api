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

Tujuan: struktur data kuat sebelum UI/API.

- [ ] Buat migration UUID helper/pattern untuk semua tabel master.
- [ ] Update tabel `users`: role, `lembaga_id`, `is_active`, field MFA, constraint role.
- [ ] Buat tabel `lembaga`.
- [ ] Buat tabel `api_clients`.
- [ ] Buat tabel `tahun_ajaran`.
- [ ] Buat tabel `guru`.
- [ ] Buat tabel `kelas`.
- [ ] Buat tabel `siswa`.
- [ ] Buat tabel `karyawan`.
- [ ] Buat tabel `audit_logs`.
- [ ] Tambahkan soft delete di semua master.
- [ ] Tambahkan partial unique NIS/NISN siswa per lembaga.
- [ ] Tambahkan unique tahun ajaran per lembaga.
- [ ] Tambahkan unique kelas per lembaga + tahun ajaran + nama.
- [ ] Tambahkan composite unique `(lembaga_id, id)` pada tabel tenant.
- [ ] Tambahkan composite FK tenant untuk relasi kelas, siswa, wali kelas, tahun ajaran.
- [ ] Tambahkan index sync `(lembaga_id, updated_at, id)` dan `(lembaga_id, deleted_at, id)`.
- [ ] Buat Eloquent model, relation, casts, fillable/guarded, hidden field sensitif.

Review wajib:

- [ ] Review migration untuk tenant isolation dan constraint DB.
- [ ] Review model agar field sensitif tidak keluar di array/JSON.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Migration fresh berhasil di PostgreSQL.
- [ ] Constraint role user bekerja.
- [ ] Composite FK menolak relasi lintas lembaga.
- [ ] Partial unique NIS/NISN bekerja.
- [ ] Hanya satu tahun ajaran aktif per lembaga.

## Milestone 2 - Audit log, utility security, dan bootstrap Super Admin

Tujuan: tindakan penting tercatat sejak awal.

- [ ] Buat service audit log append-only.
- [ ] Buat middleware/request helper `request_id`.
- [ ] Buat redaction helper untuk secret/PII di metadata.
- [ ] Buat command `install:super-admin`.
- [ ] Command hanya boleh membuat Super Admin pertama jika belum ada.
- [ ] Password policy minimal 12 karakter.
- [ ] Siapkan struktur TOTP/MFA Super Admin.
- [ ] Simpan recovery code MFA dalam bentuk hash.

Review wajib:

- [ ] Review command bootstrap agar tidak bisa overwrite Super Admin.
- [ ] Review audit log agar tidak menyimpan password/API key/PII penuh.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] `install:super-admin` sukses saat belum ada Super Admin.
- [ ] `install:super-admin` ditolak saat Super Admin sudah ada.
- [ ] Audit log mencatat aksi kritis tanpa secret.
- [ ] Password pendek ditolak.

## Milestone 3 - Auth admin dan authorization multi-tenant

Tujuan: Super Admin dan Admin Lembaga punya batas akses yang benar.

- [ ] Implement login/logout session admin.
- [ ] Implement throttle login 5/menit/email+IP dan limit tambahan per IP.
- [ ] Implement MFA/TOTP wajib untuk Super Admin sebelum produksi publik.
- [ ] Implement middleware user aktif.
- [ ] Admin Lembaga lembaga nonaktif tidak bisa login.
- [ ] Implement Gate/Policy role.
- [ ] Implement tenant scope untuk semua CRUD master.
- [ ] Invalidate session saat user dinonaktifkan.
- [ ] Pesan login generik: "Email atau password salah".

Review wajib:

- [ ] Review semua middleware dan policy.
- [ ] Review query list/detail agar Admin Lembaga tidak bisa lintas lembaga.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Super Admin bisa akses semua lembaga.
- [ ] Admin Lembaga hanya akses lembaga sendiri.
- [ ] Admin Lembaga tidak bisa akses record lembaga lain via URL/manual request.
- [ ] Lembaga nonaktif membuat Admin Lembaga ditolak login.
- [ ] Login throttle bekerja.
- [ ] MFA Super Admin aktif dan wajib sebelum produksi publik.

## Milestone 4 - UI shell dan dashboard admin

Tujuan: fondasi UI konsisten sebelum CRUD detail.

- [ ] Buat layout Blade/Livewire admin.
- [ ] Sidebar per role.
- [ ] Header user/lembaga/logout.
- [ ] Breadcrumb dan judul halaman.
- [ ] Komponen tombol, input, select, badge, modal, table, pagination.
- [ ] Empty state, loading state, dan inline validation state.
- [ ] Dashboard Super Admin: jumlah lembaga aktif/nonaktif, API client aktif, ringkasan master.
- [ ] Dashboard Admin Lembaga: urutan input data dan ringkasan master.

Review wajib:

- [ ] Review UI untuk bahasa Indonesia, aksesibilitas dasar, dan konsistensi komponen.
- [ ] Review agar UI tidak menjadi satu-satunya enforcement otorisasi.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Smoke test halaman dashboard.
- [ ] Snapshot/manual review layout desktop dan tablet.
- [ ] Navigasi sidebar sesuai role.

## Milestone 5 - CRUD Super Admin

Tujuan: pengelolaan lembaga, admin lembaga, dan API client/key.

- [ ] CRUD lembaga.
- [ ] Aktif/nonaktif lembaga dengan modal dampak.
- [ ] CRUD Admin Lembaga.
- [ ] Aktif/nonaktif Admin Lembaga.
- [ ] Buat API client per aplikasi konsumen.
- [ ] Generate API key copy-once.
- [ ] Rotate API key dengan modal dampak; key lama langsung revoke.
- [ ] Revoke/nonaktifkan API client.
- [ ] Admin Lembaga hanya lihat nama client, prefix, scope, status, last used.
- [ ] Audit log untuk semua aksi kritis.

Review wajib:

- [ ] Review copy-once API key agar plain key tidak pernah tersimpan.
- [ ] Review digest HMAC + `hash_equals`.
- [ ] Review modal destruktif.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Plain API key hanya muncul saat create/rotate.
- [ ] DB hanya menyimpan prefix + digest.
- [ ] Rotate mematikan key lama.
- [ ] Admin Lembaga tidak bisa rotate/revoke.
- [ ] Audit log tercatat tanpa secret.

## Milestone 6 - CRUD master Admin Lembaga

Tujuan: master data fase 1 lengkap dan scoped.

- [ ] CRUD Tahun Ajaran.
- [ ] Aktifkan satu tahun ajaran per lembaga dalam transaksi.
- [ ] CRUD Guru.
- [ ] CRUD Kelas.
- [ ] Validasi kelas wajib punya tahun ajaran milik lembaga yang sama.
- [ ] Validasi wali kelas guru milik lembaga yang sama.
- [ ] Blok soft delete kelas yang masih dipakai siswa.
- [ ] CRUD Siswa.
- [ ] Siswa boleh tanpa kelas, tampil badge "Belum ada kelas".
- [ ] Validasi siswa `kelas_id` dan `tahun_ajaran_id` cocok.
- [ ] CRUD Karyawan.
- [ ] Search/pagination list siswa dan guru.
- [ ] Soft delete dengan modal konfirmasi.
- [ ] Audit log akses/view PII admin sesuai RULES.

Review wajib:

- [ ] Review semua query CRUD untuk tenant scope.
- [ ] Review validasi relasi lembaga.
- [ ] Review UX form dan pesan validasi bahasa Indonesia.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Admin Lembaga A tidak bisa CRUD data lembaga B.
- [ ] Kelas lintas lembaga ditolak.
- [ ] Wali kelas lintas lembaga ditolak.
- [ ] Delete kelas berisi siswa diblok.
- [ ] Satu tahun ajaran aktif per lembaga.
- [ ] Partial unique NIS/NISN berjalan.

## Milestone 7 - API client authentication

Tujuan: integrasi aplikasi konsumen aman dan scoped.

- [ ] Middleware API key mendukung `X-API-Key`.
- [ ] Opsional dukung `Authorization: Bearer`.
- [ ] Parse format `dc_live_<prefix>_<secret>`.
- [ ] Lookup prefix unik.
- [ ] Verifikasi digest HMAC dengan `hash_equals`.
- [ ] Tolak key invalid dengan 401 generik.
- [ ] Tolak API client inactive/revoked dengan 403.
- [ ] Tolak lembaga inactive dengan 403 `LEMBAGA_INACTIVE`.
- [ ] Update `last_used_at` dan `last_used_ip`.
- [ ] Rate limit 120/menit/key + limit tambahan per IP.
- [ ] Pastikan header auth tidak pernah masuk log aplikasi.

Review wajib:

- [ ] Review middleware untuk timing-safe compare dan secret handling.
- [ ] Review rate limit key/IP.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Tanpa key ditolak 401.
- [ ] Key salah ditolak 401.
- [ ] Key revoked ditolak 403.
- [ ] Lembaga inactive ditolak 403.
- [ ] Key A tidak bisa baca lembaga B.
- [ ] Rate limit bekerja.

## Milestone 8 - API tarik penuh

Tujuan: aplikasi konsumen bisa mengambil snapshot resource sesuai scope.

- [ ] `GET /api/v1/health` tanpa info internal.
- [ ] `GET /api/v1/me`.
- [ ] `GET /api/v1/guru`.
- [ ] `GET /api/v1/siswa`.
- [ ] `GET /api/v1/karyawan`.
- [ ] `GET /api/v1/kelas`.
- [ ] `GET /api/v1/tahun-ajaran`.
- [ ] Enforce scope resource, contoh `siswa:read`.
- [ ] Enforce field profile `minimal`, `academic`, `contact`.
- [ ] Query `include_deleted`.
- [ ] Query `active_only`.
- [ ] Query `fields` hanya jika diizinkan client.
- [ ] Pagination default 100, max 200.
- [ ] Response timestamp ISO-8601 UTC.
- [ ] Error body konsisten `message`, `code`, `request_id`.

Review wajib:

- [ ] Review field mapping agar tidak ada PII berlebih.
- [ ] Review query untuk N+1 dan index.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Scope resource membatasi endpoint.
- [ ] Field profile minimal tidak mengirim kontak/alamat/wali.
- [ ] `per_page > 200` dibatasi/ditolak sesuai implementasi.
- [ ] `include_deleted` mengirim soft deleted bila diizinkan.
- [ ] Error code resmi sesuai SPEC.

## Milestone 9 - API sync delta

Tujuan: sinkronisasi tidak melewatkan data saat perubahan banyak.

- [ ] `GET /api/v1/{resource}/sync`.
- [ ] Validasi `since` wajib ISO-8601 UTC.
- [ ] Tolak `since` masa depan.
- [ ] Tolak `since` lebih dari 90 hari dengan `SINCE_TOO_OLD`.
- [ ] Hitung `changed_at = greatest(updated_at, deleted_at)`.
- [ ] Buat watermark server pada awal sync.
- [ ] Query `changed_at > since` dan `changed_at <= watermark`.
- [ ] Cursor pagination berbasis `(changed_at, id)`.
- [ ] `next_cursor` dikirim jika masih ada data.
- [ ] Tombstone delete hanya field minimum.
- [ ] App integrator di docs diarahkan menyimpan watermark hanya saat `next_cursor = null`.

Review wajib:

- [ ] Review logic cursor untuk race condition.
- [ ] Review soft delete tombstone agar tidak dump PII.
- [ ] Review performa index.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Create/update/delete muncul di sync.
- [ ] Data tidak terlewat saat jumlah perubahan lebih dari `per_page`.
- [ ] Update baru setelah watermark tidak masuk batch lama.
- [ ] Cursor invalid ditolak.
- [ ] Tombstone delete minim PII.

## Milestone 10 - Hardening aplikasi

Tujuan: baseline security production-ready sebelum staging publik.

- [ ] Security headers middleware: HSTS production, X-Content-Type-Options, X-Frame-Options/CSP dasar, Referrer-Policy.
- [ ] CORS default deny untuk browser origin.
- [ ] APP_DEBUG false di production template/deploy notes.
- [ ] Session production: secure, httpOnly, same_site ketat.
- [ ] Logging redaction untuk auth/API key/password/token.
- [ ] Error production tidak bocor stack trace.
- [ ] Health endpoint tetap minimal.
- [ ] Tambah dokumentasi env production.

Review wajib:

- [ ] Review config production dan middleware header.
- [ ] Review log redaction.
- [ ] Perbaiki semua temuan review sebelum test.

Test wajib:

- [ ] Header keamanan muncul di response.
- [ ] CORS browser default deny.
- [ ] Secret tidak muncul di response/log aplikasi.
- [ ] Health endpoint hanya `{ "status": "ok" }`.

## Milestone 11 - Dokumentasi integrator dan operasional

Tujuan: aplikasi konsumen dan operator punya panduan jelas.

- [ ] Buat `docs/API_INTEGRATION.md`.
- [ ] Dokumentasikan auth header.
- [ ] Dokumentasikan scope dan field profile.
- [ ] Dokumentasikan tarik penuh.
- [ ] Dokumentasikan sync watermark/cursor.
- [ ] Dokumentasikan error code.
- [ ] Dokumentasikan retry untuk 429/5xx.
- [ ] Buat `docs/DEPLOYMENT.md`.
- [ ] Dokumentasikan Apache + PHP + PostgreSQL.
- [ ] Dokumentasikan Cloudflare wajib sebelum produksi publik.
- [ ] Dokumentasikan backup terenkripsi/offsite, RPO/RTO, restore test.
- [ ] Dokumentasikan response incident ringkas: rotate key, disable akun, blok IP, restore backup.

Review wajib:

- [ ] Review dokumentasi integrator dengan contoh request/response.
- [ ] Review deployment checklist agar tidak membuka DB ke publik.

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
