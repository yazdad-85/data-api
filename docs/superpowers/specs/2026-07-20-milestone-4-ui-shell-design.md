# Design — Milestone 4: UI Shell & Dashboard Admin

Status: **DISETUJUI — 20 Jul 2026**  
Tanggal: 2026-07-20  
Basis: [README.md](../../../README.md), [SPEC.md](../../SPEC.md) §5, [RULES.md](../../RULES.md) A12, [IMPLEMENTATION_TODO.md](../../IMPLEMENTATION_TODO.md) Milestone 4, [PLAN.md](../../PLAN.md)

## 1. Tujuan

Membangun fondasi UI admin yang **konsisten, ekspresif, dan siap dipakai CRUD** (M5/M6): layout Blade, sidebar per role, header, breadcrumb, komponen UI bersama, dashboard per role, serta **footer** di admin dan halaman auth — tanpa menjadikan UI sebagai satu-satunya enforcement otorisasi.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Arah visual | **Teal akademik** — teal dalam, permukaan off-white, tipografi serif display + sans UI |
| Implementasi shell | Layout + komponen **Blade**; Livewire menyusul saat interaksi CRUD butuh (M5+) |
| Layout | **App-rail klasik**: sidebar kiri + header + konten + footer |
| Footer | Tipe **B**: produk, © tahun, badge lingkungan (`APP_ENV`), `request_id` di `title` (tidak mencolok) |
| Visual companion | Tidak dipakai — desain/teks saja |
| Bahasa | Indonesia (label, empty state, validasi) |
| Responsif | Desktop-first; tablet: sidebar → drawer |

## 3. Di luar scope Milestone 4

- CRUD lembaga / admin / API client (M5)
- CRUD tahun ajaran, guru, kelas, siswa, karyawan (M6)
- Livewire search/pagination/modal destruktif penuh
- Dark mode, multi-tema, i18n selain ID
- Perubahan aturan otorisasi server (tetap M3 middleware/policy/scope)

## 4. Sistem visual

### 4.1 Token warna (CSS variables)

| Token | Peran | Contoh |
|-------|--------|--------|
| `--brand` | Aksen utama / CTA | `#0F6B6B` |
| `--brand-deep` | Sidebar / header brand | `#0A4F4F` |
| `--brand-soft` | Hover/selected lembut | `#D8EDED` |
| `--surface` | Latar konten | `#F4F7F6` |
| `--surface-elevated` | Panel form/list | `#FFFFFF` |
| `--ink` | Teks utama | `#12221F` |
| `--muted` | Teks sekunder | `#5C6F6B` |
| `--border` | Garis pemisah | `#C9D6D3` |
| `--ok` / `--warn` / `--danger` | Semantik status | hijau / amber / merah (bukan ungu) |

Hindari: ungu AI-default, cream+terracotta klise, glow, multi-layer shadow, `rounded-full` pill berlebihan, emoji sebagai ornamen.

### 4.2 Tipografi

- Judul / brand display: **Source Serif 4** (atau Fraunces)
- Body / UI: **Source Sans 3** (atau DM Sans)
- Muat via Vite/`@fontsource` atau link font di layout; jangan stack Inter/Roboto/Arial/system sebagai identitas.

### 4.3 Gerak (intentional, 2–3)

1. Indikator item aktif sidebar (slide/underline singkat)
2. Fade-in ringan area konten saat navigasi
3. Focus ring jelas pada kontrol (aksesibilitas keyboard)

## 5. Arsitektur layout

### 5.1 Admin (`layouts/admin.blade.php`)

```
┌──────────────┬────────────────────────────────┐
│ Sidebar      │ Header: user · role · lembaga · Keluar │
│ “Pusat Data” ├────────────────────────────────┤
│ menu/role    │ Breadcrumb · Judul · Aksi utama │
│              │ @yield('content')               │
│              ├────────────────────────────────┤
│              │ Footer (produk · © · env badge) │
└──────────────┴────────────────────────────────┘
```

- Sidebar desktop tetap; tablet: tombol “Menu” membuka drawer + backdrop.
- Konten: `max-width` nyaman untuk tabel; padding konsisten.
- Otorisasi menu dari role server (`@can` / helper), bukan hanya CSS hide.

### 5.2 Guest/auth (`layouts/guest.blade.php`)

- Restyle login/MFA ke token teal yang sama.
- Brand “Pusat Data” sebagai sinyal utama viewport guest.
- Footer guest (tanpa data user) dengan env + `request_id` di `title`.

### 5.3 Footer (kontrak)

| Elemen | Admin | Guest |
|--------|-------|-------|
| Nama produk Pusat Data | Ya | Ya |
| © tahun berjalan | Ya | Ya |
| Badge env: Lokal / Staging / Produksi (map dari `APP_ENV`) | Ya | Ya |
| `request_id` di `title` elemen footer | Ya | Ya |
| Nama user/lembaga | Tidak (sudah di header) | Tidak |

Mapping env: `local` → Lokal; `staging` → Staging; `production` → Produksi; lainnya tampilkan raw `APP_ENV` singkat.

## 6. Navigasi sidebar

### 6.1 Super Admin

1. Dashboard  
2. Lembaga *(M5 — link “Segera hadir” atau route placeholder)*  
3. Admin lembaga *(placeholder M5)*  
4. API client *(placeholder M5)*  

### 6.2 Admin Lembaga (urutan wajib SPEC §2.4 / §5.2)

1. Dashboard  
2. Tahun ajaran  
3. Guru  
4. Kelas  
5. Siswa  
6. Karyawan  
7. API client *(read-only M5 — placeholder di M4)*  

Item yang belum diimplementasi: halaman **Segera hadir** (judul + satu kalimat), bukan 404.

Menu dibangun dari config/array di `App\Support\Navigation\AdminMenu` (atau setara) agar satu sumber kebenaran untuk sidebar + test.

## 7. Komponen Blade

Prefix `resources/views/components/ui/`:

| Komponen | Fungsi |
|----------|--------|
| `button` | primary / secondary / danger / ghost; state disabled/loading |
| `input` | label, hint, error inline, required `*` |
| `select` | sama seperti input |
| `badge` | aktif / nonaktif / “Belum ada kelas” / role |
| `modal` | kerangka dialog + slot judul/isi/aksi (siap M5) |
| `table` | wrapper tabel + empty slot |
| `pagination` | wrapper panjang Laravel paginator (styled) |
| `empty-state` | judul + teks + slot CTA |
| `skeleton` | loading placeholder list |

Prinsip: tanpa card dekoratif. Panel statistik dashboard boleh punya latar `surface-elevated` + border tipis karena itu unit baca angka, bukan ornamen.

## 8. Dashboard

### 8.1 Super Admin

Query agregat (tanpa N+1 kasar):

- Jumlah lembaga aktif / nonaktif  
- Jumlah API client aktif (belum revoked)  
- Ringkasan master global: guru, siswa, karyawan (count)

Tampilkan sebagai deretan metrik + teks singkat. Tidak ada CRUD.

### 8.2 Admin Lembaga

- Panduan urutan pengisian 1–5 (Tahun ajaran → … → Karyawan)  
- Ringkasan count master **hanya** `lembaga_id` user (mengandalkan scope/policy M3)  
- CTA teks ke menu terkait (link placeholder jika belum ada CRUD)

### 8.3 Controller

Perluas `DashboardController`: load stats via service kecil `DashboardStats` (pisah Super Admin vs Admin Lembaga). Tetap di belakang middleware `auth` + `active` + `mfa`.

## 9. File utama yang diharapkan

| Path | Peran |
|------|--------|
| `resources/css/app.css` | Token + utility shell |
| `resources/views/layouts/admin.blade.php` | Shell admin |
| `resources/views/layouts/guest.blade.php` | Shell auth (restyle) |
| `resources/views/components/ui/*` | Komponen bersama |
| `resources/views/admin/dashboard.blade.php` | Dashboard |
| `resources/views/admin/coming-soon.blade.php` | Placeholder menu |
| `resources/views/partials/footer.blade.php` | Footer bersama |
| `app/Support/Navigation/AdminMenu.php` | Definisi menu per role |
| `app/Services/Dashboard/DashboardStats.php` | Agregasi angka |
| `tests/Feature/AdminShellTest.php` | Smoke + navigasi + footer |

## 10. Testing (setelah review kode bersih — RULES B1)

1. Login Super Admin → `/admin` 200; lihat menu SA; metrik dashboard ada.  
2. Login Admin Lembaga → menu berurutan SPEC; tidak ada menu “Lembaga” SA.  
3. Footer muncul di dashboard dan di `/login`; badge env ada.  
4. Placeholder “Segera hadir” 200 untuk route menu M5.  
5. Pastikan halaman admin tetap di belakang middleware; user nonaktif tidak lolos (regresi M3).

## 11. Acceptance criteria

Milestone 4 selesai hanya jika:

- Semua item checklist M4 di `IMPLEMENTATION_TODO.md` dapat dicentang setelah review + test hijau  
- Tema teal + tipografi + footer konsisten di admin dan guest  
- Sidebar sesuai role; dashboard menampilkan data scoped  
- UI tidak menggantikan Gate/Policy/middleware  
- Tidak ada CRUD M5/M6 yang diselundupkan
