<?php

namespace Tests\Unit;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Support\Api\ApiResourceCatalog;
use PHPUnit\Framework\TestCase;

class ApiResourceCatalogTest extends TestCase
{
    public function test_slugs_returns_all_five_resources(): void
    {
        $slugs = (new ApiResourceCatalog)->slugs();

        $this->assertEqualsCanonicalizing(
            ['tahun-ajaran', 'guru', 'kelas', 'siswa', 'karyawan'],
            $slugs
        );
    }

    public function test_unknown_slug_returns_null(): void
    {
        $this->assertNull((new ApiResourceCatalog)->get('unknown'));
    }

    public function test_tahun_ajaran_entry(): void
    {
        $entry = (new ApiResourceCatalog)->get('tahun-ajaran');

        $this->assertSame(TahunAjaran::class, $entry['model']);
        $this->assertSame('tahun_ajaran:read', $entry['scope']);
        $this->assertSame('is_aktif', $entry['active_column']);

        $expected = [
            'id', 'lembaga_id', 'nama', 'tanggal_mulai', 'tanggal_selesai',
            'is_aktif', 'created_at', 'updated_at',
        ];
        $this->assertSame($expected, $entry['fields']['minimal']);
        $this->assertSame($expected, $entry['fields']['academic']);
        $this->assertSame($expected, $entry['fields']['contact']);
        $this->assertSame([], $entry['embeds']);
    }

    public function test_guru_entry(): void
    {
        $entry = (new ApiResourceCatalog)->get('guru');

        $this->assertSame(Guru::class, $entry['model']);
        $this->assertSame('guru:read', $entry['scope']);
        $this->assertSame('is_active', $entry['active_column']);

        $minimal = ['id', 'lembaga_id', 'niy', 'nama', 'foto_path', 'is_active', 'created_at', 'updated_at'];
        $this->assertSame($minimal, $entry['fields']['minimal']);

        foreach ($minimal as $field) {
            $this->assertContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }

        foreach (['peg_id', 'tahun_masuk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'status_kepegawaian', 'pendidikan_terakhir', 'instansi_pendidikan', 'jurusan', 'status_sertifikasi', 'status_inpasing', 'mapel_sertifikasi'] as $field) {
            $this->assertContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }
        $this->assertNotContains('nik', $entry['fields']['academic']);
        $this->assertNotContains('status_menikah', $entry['fields']['academic']);
        $this->assertNotContains('email', $entry['fields']['academic']);

        foreach (['nik', 'status_menikah', 'email', 'telepon', 'alamat'] as $field) {
            $this->assertNotContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }
    }

    public function test_karyawan_entry(): void
    {
        $entry = (new ApiResourceCatalog)->get('karyawan');

        $this->assertSame(Karyawan::class, $entry['model']);
        $this->assertSame('karyawan:read', $entry['scope']);
        $this->assertSame('is_active', $entry['active_column']);

        $minimal = ['id', 'lembaga_id', 'nik_pegawai', 'nama', 'is_active', 'created_at', 'updated_at'];
        $this->assertSame($minimal, $entry['fields']['minimal']);

        foreach (['tahun_masuk', 'jenis_kelamin', 'jabatan'] as $field) {
            $this->assertContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }
        $this->assertNotContains('email', $entry['fields']['academic']);

        foreach (['email', 'telepon', 'alamat'] as $field) {
            $this->assertNotContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }
    }

    public function test_kelas_entry(): void
    {
        $entry = (new ApiResourceCatalog)->get('kelas');

        $this->assertSame(Kelas::class, $entry['model']);
        $this->assertSame('kelas:read', $entry['scope']);
        $this->assertNull($entry['active_column']);

        $minimal = ['id', 'lembaga_id', 'tahun_ajaran_id', 'nama', 'created_at', 'updated_at'];
        $this->assertSame($minimal, $entry['fields']['minimal']);

        foreach (['tingkat', 'wali_kelas_guru_id'] as $field) {
            $this->assertContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }

        $this->assertSame($entry['fields']['academic'], $entry['fields']['contact']);
    }

    public function test_siswa_entry(): void
    {
        $entry = (new ApiResourceCatalog)->get('siswa');

        $this->assertSame(Siswa::class, $entry['model']);
        $this->assertSame('siswa:read', $entry['scope']);
        $this->assertSame('is_active', $entry['active_column']);

        $minimal = [
            'id', 'lembaga_id', 'nis', 'nama', 'status_siswa', 'is_active',
            'kelas_id', 'tahun_ajaran_id', 'created_at', 'updated_at',
        ];
        $this->assertSame($minimal, $entry['fields']['minimal']);

        $academicOnly = [
            'nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'status_at', 'status_alasan', 'status_asal', 'status_tujuan',
            'status_keluarga',
        ];
        foreach ($academicOnly as $field) {
            $this->assertContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }

        $contactOnly = ['email', 'telepon', 'alamat', 'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu', 'nama_wali', 'nama_kontak_wali', 'telepon_wali'];
        foreach ($contactOnly as $field) {
            $this->assertNotContains($field, $entry['fields']['academic']);
            $this->assertContains($field, $entry['fields']['contact']);
        }

        $this->assertSame(['penempatan_aktif'], $entry['embeds']['academic']);
        $this->assertSame(['penempatan_aktif', 'riwayat_penempatan'], $entry['embeds']['contact']);
    }

    public function test_all_entries_reference_existing_scope_naming_convention(): void
    {
        $catalog = new ApiResourceCatalog;

        foreach ($catalog->slugs() as $slug) {
            $entry = $catalog->get($slug);
            $this->assertStringEndsWith(':read', $entry['scope']);
            $this->assertTrue(class_exists($entry['model']));
        }
    }
}
