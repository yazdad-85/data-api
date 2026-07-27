# Design: Modernisasi halaman login & MFA

Status: **DISETUJUI — 27 Jul 2026**  
Tanggal: 27 Jul 2026  
Basis: SPEC §5, branding `app_settings`, auth + MFA flow yang sudah berjalan

## 1. Tujuan

Memodernisasi halaman **login** dan **verifikasi MFA** agar:

- memberi kesan visual yang lebih premium saat pertama dibuka;
- tetap cepat, ringan, dan aman untuk alur autentikasi admin;
- konsisten dengan branding aplikasi (`app_branding()`), termasuk logo dan nama aplikasi.

Redesign ini fokus pada **presentasi UI**, bukan perubahan logika autentikasi.

## 2. Keputusan yang disepakati

| Topik | Keputusan |
|-------|-----------|
| Layout utama | **Split layout**: hero kiri + form kanan |
| Gaya hero | **Karakter penjaga stylized** |
| Teknik visual | Ilustrasi utama + **partikel/orbit CSS** |
| Cakupan halaman | **`/login` dan `/login/mfa`** |
| Stack | **Blade + CSS/JS ringan via Vite**, tanpa Three.js |
| Motion | Halus dan terbatas; hormati `prefers-reduced-motion` |
| Auth behavior | **Tidak berubah** |

## 3. Out of scope

- Perubahan route, controller, validation, rate limit, session, atau MFA logic
- 3D runtime sungguhan (`Three.js`, GLB, WebGL scene)
- Redesign dashboard admin setelah login
- Forgot-password flow baru
- Dark mode atau theme switcher

## 4. Struktur halaman

### 4.1 Desktop

Pada layar desktop (`>= 960px`), halaman guest memakai komposisi dua kolom:

- **Kiri (~55%)**: hero visual penuh
- **Kanan (~45%)**: panel form

Hero kiri memuat:

- logo aplikasi;
- nama aplikasi;
- headline singkat;
- deskripsi 1 kalimat;
- ilustrasi penjaga stylized;
- aksen circuit/partikel/orbit.

Panel kanan memuat:

- judul halaman (`Masuk` / `Verifikasi MFA`);
- deskripsi singkat;
- error message jika ada;
- form yang sudah ada.

### 4.2 Mobile

Pada layar kecil:

- hero dipadatkan menjadi blok atas;
- karakter diperkecil;
- partikel/orbit disederhanakan;
- form turun ke bawah dalam satu kolom;
- tidak ada elemen dekoratif yang mengganggu pengisian form.

## 5. Visual direction

### 5.1 Hero kiri

Nuansa visual:

- dasar **teal / green deep** selaras branding saat ini;
- aksen **emas** mengikuti warna logo/perisai;
- gradient dan glow ringan agar terasa modern, bukan flat.

Konten hero:

- eyebrow kecil seperti “Pusat Data Terintegrasi” atau copy setara;
- headline besar yang menonjolkan keamanan dan konektivitas data;
- subheadline singkat;
- badge/mini bullet visual seperti “Aman”, “Terintegrasi”, “Siap sinkron”.

### 5.2 Karakter stylized

Karakter bukan foto realistis. Aset berupa ilustrasi/PNG/WebP transparan:

- figur penjaga/protector profesional;
- memegang atau berdampingan dengan perisai/data shield;
- warna menyatu dengan teal-emas brand;
- tetap sopan, institusional, dan tidak terasa seperti game.

### 5.3 Form kanan

Form harus tetap terasa serius dan mudah dipakai:

- card putih / elevated dengan shadow halus;
- spacing lega;
- input lebih besar dan rapi;
- tombol utama tetap kontras;
- error tetap jelas.

Kesan target: **modern premium**, bukan flashy berlebihan.

## 6. Animasi

Animasi yang diizinkan hanya 2-3 jenis ringan:

1. **Entrance animation** saat load untuk hero dan panel form  
2. **Floating** lembut pada ilustrasi penjaga  
3. **Orbit / pulse** ringan pada partikel dekoratif

Aturan:

- durasi halus dan tidak terlalu cepat;
- tidak mengganggu fokus input;
- jika `prefers-reduced-motion: reduce`, semua animasi loop dimatikan.

## 7. Perilaku server dan UX

### 7.1 Hal yang tetap

Semua perilaku auth existing harus tetap sama:

- POST login tetap ke route yang sama;
- field email/password tetap sama;
- halaman MFA tetap submit ke route yang sama;
- CSRF tetap ada;
- error message tetap tampil;
- rate limiting dan middleware tidak berubah.

### 7.2 Branding dinamis

UI guest harus memakai `app_branding()`:

- nama aplikasi;
- logo aplikasi jika tersedia;
- favicon tetap dari branding yang sudah ada.

Jika logo belum ada:

- halaman tetap terlihat bagus dengan brand text saja;
- hero tidak rusak atau kosong.

## 8. File utama

Perkiraan perubahan:

| File | Responsibility |
|------|----------------|
| `resources/views/layouts/guest.blade.php` | Shell split baru untuk halaman guest |
| `resources/views/partials/auth-hero.blade.php` | Hero kiri reusable untuk login dan MFA |
| `resources/views/auth/login.blade.php` | Form login di dalam panel baru |
| `resources/views/auth/mfa.blade.php` | Form MFA di dalam panel baru |
| `resources/css/app.css` | CSS auth shell, hero, panel, motion |
| `resources/js/app.js` | JS kecil jika perlu untuk class load / polish |
| `public/images/auth/guardian.webp` | Aset ilustrasi penjaga utama |
| `tests/Feature/AdminAuthTest.php` | Assertion guest UI bila perlu |

## 9. Testing

Testing minimal:

1. `GET /login` mengembalikan `200`
2. `GET /login/mfa` tetap bekerja sesuai behavior saat ini
3. Form login tetap punya field email/password + CSRF
4. Form MFA tetap punya field code + CSRF
5. Branding name tetap muncul
6. Tidak merusak test auth/hardening yang ada

Optional assertions:

- class/markup hero muncul;
- fallback text tetap tampil saat logo tidak ada.

## 10. Risiko & mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Halaman terlalu berat | Gunakan aset gambar tunggal + CSS animation ringan |
| Hero mengalahkan fokus form | Kontras dan hierarchy menjaga form tetap dominan di kanan |
| Motion mengganggu | Batasi animasi dan hormati `prefers-reduced-motion` |
| Asset belum siap | Gunakan placeholder lokal sementara dengan interface final yang sama |
| MFA page tertinggal dari login | Pakai partial hero bersama dan shell guest bersama |

## 11. Kriteria hasil

Redesign dianggap berhasil bila:

- login dan MFA terasa jauh lebih modern secara visual;
- branding PUSDATIN/YASMU langsung terasa;
- form tetap cepat dibaca dan digunakan;
- tidak ada perubahan behavior autentikasi;
- tampilan desktop dan mobile sama-sama rapi.
