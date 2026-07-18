# data-api - Pusat Data

Repositori aplikasi **Pusat Data** lembaga pendidikan.

## Status saat ini

**DISETUJUI - 18 Jul 2026.** Dokumen perencanaan sudah dipindahkan ke `docs/`; stack awal Laravel sudah terpasang.

Stack: **Laravel + PostgreSQL + Apache (VPS)** - disetujui; UI admin: **Blade + Livewire**.  
Keamanan: standar data center (RULES B4) + **Cloudflare** (wajib sebelum produksi publik). Proses kode: review wajib sebelum tes.

## Stack lokal

- Laravel 13
- PHP 8.5
- PostgreSQL 16
- Blade + Livewire 4
- Vite + Tailwind CSS

## Development

```bash
composer install
npm install
php artisan migrate
npm run build
php artisan test
```

Database lokal default memakai `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pusat_data
```

## Dokumen referensi

Semua dokumen konsep dan audit ada di folder [docs](./docs/).

| Dokumen | Fungsi |
|---------|--------|
| [PLAN.md](./docs/PLAN.md) | Rencana fase, deliverable, urutan kerja |
| [SPEC.md](./docs/SPEC.md) | Spesifikasi entitas, field, API, UI, deploy |
| [RULES.md](./docs/RULES.md) | Aturan bisnis & aturan implementasi |
| [RENCANA.md](./docs/RENCANA.md) | Riwayat kesepakatan diskusi awal |
| [AUDIT_LENGKAP_2026-07-18.md](./docs/AUDIT_LENGKAP_2026-07-18.md) | Hasil audit jujur dan revisi penguatan sebelum coding |
| [IMPLEMENTATION_TODO.md](./docs/IMPLEMENTATION_TODO.md) | Checklist rencana kerja coding fase 1 |

## Cara koreksi bersama

1. Baca PLAN -> SPEC -> RULES -> AUDIT_LENGKAP.
2. Kirim koreksi dengan format: `file + bagian + perubahan`.
3. Dokumen sudah **DISETUJUI** — perubahan requirement update SPEC/RULES dulu, baru kode.
