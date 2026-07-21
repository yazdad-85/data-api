<?php

namespace App\Support\Api;

final class ApiClientScopes
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'tahun_ajaran:read',
            'guru:read',
            'kelas:read',
            'siswa:read',
            'karyawan:read',
        ];
    }
}
