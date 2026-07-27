# Design: Profil admin & ganti password

Status: **DISETUJUI — 27 Jul 2026**  
Tanggal: 27 Jul 2026  
Basis: SPEC §5.0–5.2, RULES B4.2 (password min 12), B4.3 (audit tanpa secret)

## 1. Tujuan

Menyediakan halaman **Profil** agar Super Admin dan Admin Lembaga dapat:

- melihat data akun sendiri;
- mengubah **nama**;
- mengganti **password** dengan password lama wajib, dan mengakhiri sesi di perangkat lain.

**Pengaturan aplikasi** (settings global) **di luar scope** — tahap terpisah.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Audience | Super Admin **dan** Admin Lembaga |
| Entry | Tautan di **header** (dekat nama user), bukan item sidebar |
| Field editable | **Nama** + **password** |
| Email | Read-only |
| Password policy | Min 12 karakter (`Password::min(12)`), beda dari password lama, konfirmasi cocok |
| Sesi setelah ganti password | Invalidate sesi DB user lain; sesi saat ini tetap login + regenerate |
| MFA re-challenge | Tidak (sudah dilindungi middleware `mfa` untuk Super Admin) |

## 3. Out of scope

- Pengaturan aplikasi / env UI
- Ubah email
- Regenerasi MFA / recovery codes dari UI
- Forgot-password via email
- Profil untuk role selain admin dashboard

## 4. Routes & otorisasi

| Method | Path | Name | Aksi |
|--------|------|------|------|
| GET | `/admin/profil` | `admin.profile.show` | Tampilkan halaman |
| PUT | `/admin/profil` | `admin.profile.update` | Update nama |
| PUT | `/admin/profil/password` | `admin.profile.password` | Ganti password |

Middleware group yang sama: `auth`, `active`, `mfa`.

Otorisasi: selalu beroperasi pada `auth()->user()` — tidak ada route `/{user}`. Tidak ada cara mengubah profil user lain lewat endpoint ini.

## 5. UI

### 5.1 Header

- Nama user menjadi tautan ke `admin.profile.show` (bukan item sidebar).
- Role badge dan tombol Keluar tetap.

### 5.2 Halaman `/admin/profil`

Dua bagian terpisah (dua form):

1. **Akun**
   - Email (read-only)
   - Role (read-only; label Indonesia)
   - Nama lembaga (hanya Admin Lembaga; read-only)
   - Nama (input) + Simpan

2. **Ganti password**
   - Password saat ini
   - Password baru
   - Konfirmasi password baru
   - Tombol Ganti password

Pola visual: layout admin Blade yang ada (`page-header`, field components, flash success/error).

## 6. Perilaku server

### 6.1 Update nama

- Validasi: `name` → `required|string|max:150`
- Simpan ke `users.name`
- Audit: `profile.update` / `success` dengan metadata field yang berubah (mis. `fields: ['name']`) — tanpa dump PII berlebih
- Flash: sukses singkat bahasa Indonesia

### 6.2 Ganti password

- Validasi:
  - `current_password` → `required|current_password`
  - `password` → `required|confirmed|Password::min(12)`
  - Password baru tidak boleh sama dengan password lama (rule `different:current_password` atau cek Hash)
- Setelah sukses:
  1. Update hash password
  2. `$request->session()->regenerate(true)` agar sesi aktif mendapat ID baru dan ID lama dihancurkan
  3. `SessionInvalidator::invalidateOtherSessions($userId, $request->session()->getId())` — hapus baris di tabel `sessions` untuk `user_id` kecuali ID sesi aktif yang baru
  4. Audit: `profile.password_change` / `success` — **tanpa** plain password
  5. Flash: memberitahu bahwa sesi perangkat lain telah diakhiri

### 6.3 SessionInvalidator

Perluas `App\Services\Auth\SessionInvalidator`:

- Method baru `invalidateOtherSessions(string $userId, string $exceptSessionId): void`
- Hanya aktif bila `session.driver === database` (driver produksi)
- **Jangan** memanggil logout pada user aktif (beda dari `invalidateUser`)

## 7. Testing

Feature tests minimal:

1. Guest → redirect login
2. Super Admin & Admin Lembaga dapat `GET /admin/profil` (200)
3. Update nama sukses; email di DB tidak berubah walau dikirim di payload
4. Password lama salah → validation error; hash tidak berubah
5. Password baru sukses → login dengan password baru OK; password lama gagal
6. Setelah ganti password, sesi DB user lain hilang; sesi current tetap ada
7. Response/audit tidak mengandung plain password

## 8. Dokumentasi ikut

- Tambah bullet singkat di `docs/SPEC.md` §5.0 atau §5.1/§5.2: profil akun (nama + ganti password)
- Tidak menambah milestone baru di TODO kecuali pemilik meminta; bisa dicatat sebagai item hardening/opsional pasca-M11 atau bagian M12 UX gap

## 9. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| `invalidateUser` ikut logout diri sendiri | Method terpisah `invalidateOtherSessions` |
| Session driver non-database di local | No-op hapus baris; tetap regenerate session |
| Brute-force password lama di form profil | Throttle route password (reuse `throttle:admin-login` atau dedicated) |
