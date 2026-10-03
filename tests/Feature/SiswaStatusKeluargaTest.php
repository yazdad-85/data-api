<?php

namespace Tests\Feature;

use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiswaStatusKeluargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_defaults_to_lengkap_and_accepts_explicit_and_empty_statuses(): void
    {
        $this->withoutVite();
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();
        $this->actingAs($admin)->get(route('admin.siswa.create'))
            ->assertOk()
            ->assertSee('value="Lengkap" selected', false)
            ->assertSee('Lengkap: ayah dan ibu masih hidup.');

        foreach ([[], ['status_keluarga' => null], ['status_keluarga' => ''], ['status_keluarga' => 'Lengkap']] as $index => $fields) {
            $this->post(route('admin.siswa.store'), ['nama' => 'Siswa '.$index, ...$fields])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.siswa.index'));

            $this->assertDatabaseHas('siswa', ['nama' => 'Siswa '.$index, 'status_keluarga' => 'Lengkap']);
        }

        $siswa = Siswa::query()->firstOrFail();
        foreach (['Yatim', 'Lengkap', 'Piatu', ''] as $status) {
            $this->put(route('admin.siswa.update', $siswa), ['nama' => $siswa->nama, 'status_keluarga' => $status])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.siswa.index'));
            $this->assertSame($status === '' ? 'Lengkap' : $status, $siswa->refresh()->status_keluarga);
        }

        $this->get(route('admin.siswa.edit', $siswa))
            ->assertOk()
            ->assertSee('value="Lengkap" selected', false);

        $this->post(route('admin.siswa.store'), ['nama' => 'Tidak valid', 'status_keluarga' => 'Tidak valid'])
            ->assertSessionHasErrors('status_keluarga');
    }

    public function test_migration_fills_legacy_blanks_across_lembagas_and_preserves_known_statuses(): void
    {
        $migration = require database_path('migrations/2026_10_03_000001_default_siswa_status_keluarga_to_lengkap.php');
        $migration->down();

        $legacyIds = [];
        foreach ([null, '', '   '] as $status) {
            $siswa = Siswa::factory()->create();
            $legacyIds[] = $siswa->id;
            DB::table('siswa')->where('id', $siswa->id)->update(['status_keluarga' => $status]);
        }
        $siswa->delete();

        $known = [];
        foreach (['Yatim', 'Piatu', 'Yatim Piatu', 'Anak Guru, Staff, dan Karyawan', 'Lengkap'] as $status) {
            $known[$status] = Siswa::factory()->create(['status_keluarga' => $status])->id;
        }

        $migration->up();

        foreach ($legacyIds as $id) {
            $this->assertDatabaseHas('siswa', ['id' => $id, 'status_keluarga' => 'Lengkap']);
        }
        foreach ($known as $status => $id) {
            $this->assertDatabaseHas('siswa', ['id' => $id, 'status_keluarga' => $status]);
        }

        // Verify the database default independently of the Eloquent model default.
        $new = Siswa::factory()->make()->getAttributes();
        $new['id'] = (string) Str::uuid();
        unset($new['status_keluarga']);
        DB::table('siswa')->insert($new);
        $this->assertDatabaseHas('siswa', ['id' => $new['id'], 'status_keluarga' => 'Lengkap']);

        $migration->down();
        $this->assertDatabaseHas('siswa', ['id' => $legacyIds[0], 'status_keluarga' => 'Lengkap']);
    }
}
