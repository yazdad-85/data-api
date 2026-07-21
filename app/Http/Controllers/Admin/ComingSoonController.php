<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function show(string $feature): View
    {
        $titles = [
            'lembaga' => 'Lembaga',
            'admin-lembaga' => 'Admin lembaga',
            'tahun-ajaran' => 'Tahun ajaran',
            'guru' => 'Guru',
            'kelas' => 'Kelas',
            'siswa' => 'Siswa',
            'karyawan' => 'Karyawan',
        ];

        abort_unless(isset($titles[$feature]), 404);

        return view('admin.coming-soon', [
            'title' => $titles[$feature],
            'user' => request()->user(),
        ]);
    }
}
