#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Models\Kelas;
use App\Models\Siswa;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$kelasId = array_shift($args);

if ($kelasId === null || in_array($kelasId, ['-h', '--help', 'help'], true)) {
    fwrite(STDERR, <<<TXT
Usage:
  php scripts/check-siswa-import.php <kelas_id> <nis> [nis...]
  printf "NIS-001\n6034/259.042\n" | php scripts/check-siswa-import.php <kelas_id>

Purpose:
  Diagnose why import siswa reports duplicated NIS for a target class.

TXT);
    exit($kelasId === null ? 1 : 0);
}

$stdinNis = [];
if (! posix_isatty(STDIN)) {
    $stdinNis = preg_split('/\R+/', trim((string) stream_get_contents(STDIN))) ?: [];
}

$nisList = array_values(array_filter(array_map(
    static fn (string $nis): string => trim($nis),
    [...$args, ...$stdinNis],
), static fn (string $nis): bool => $nis !== ''));

$kelas = Kelas::withoutGlobalScopes()
    ->withTrashed()
    ->with([
        'lembaga',
        'tahunAjaran' => fn ($query) => $query->withoutGlobalScopes(),
    ])
    ->find($kelasId);
if ($kelas === null) {
    fwrite(STDERR, "Kelas tidak ditemukan: {$kelasId}\n");
    exit(1);
}

echo "Kelas: {$kelas->nama}\n";
echo "Kelas ID: {$kelas->id}\n";
echo 'Lembaga: '.($kelas->lembaga?->nama ?? $kelas->lembaga_id)."\n";
echo 'Tahun ajaran: '.($kelas->tahunAjaran?->nama ?? $kelas->tahun_ajaran_id)."\n";
echo 'Deleted: '.($kelas->trashed() ? 'ya' : 'tidak')."\n";
echo "Commit terakhir: ".trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null'))."\n";
echo "Fix import ada: ".(str_contains((string) file_get_contents($root.'/app/Services/Siswa/SiswaImporter.php'), 'Update lewat import hanya untuk siswa di kelas ini') ? 'ya' : 'tidak')."\n";
echo "\n";

if ($nisList === []) {
    echo "Tidak ada NIS yang dicek. Kirim NIS sebagai argumen atau lewat stdin.\n";
    exit(0);
}

foreach ($nisList as $nis) {
    $matches = Siswa::withTrashed()
        ->withoutGlobalScopes()
        ->where('lembaga_id', $kelas->lembaga_id)
        ->where('nis', $nis)
        ->orderBy('deleted_at')
        ->get();

    if ($matches->isEmpty()) {
        echo "[BARU] {$nis}: belum ada di lembaga, import akan membuat siswa baru.\n";
        continue;
    }

    foreach ($matches as $siswa) {
        $deleted = $siswa->trashed() ? 'soft-deleted' : 'aktif/tidak terhapus';
        $sameClass = hash_equals((string) $siswa->kelas_id, (string) $kelas->id);
        $classInfo = $sameClass ? 'kelas sama' : 'kelas berbeda/kosong';
        $result = match (true) {
            $siswa->trashed() => 'DITOLAK: NIS pernah dipakai lalu dihapus',
            $sameClass => 'BISA UPDATE: siswa ada di kelas ini',
            default => 'DITOLAK: siswa ada di kelas lain atau belum punya kelas',
        };

        echo "[{$result}] {$nis}: {$siswa->nama}; status={$deleted}; {$classInfo}; siswa_id={$siswa->id}; kelas_id=".($siswa->kelas_id ?? '-')."\n";
    }
}
