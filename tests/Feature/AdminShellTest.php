<?php

namespace Tests\Feature;

use App\Models\Lembaga;
use App\Models\User;
use App\Support\Navigation\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_shows_shell_and_sa_menu(): void
    {
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pusat Data');
        $response->assertSee('Lembaga');
        $response->assertDontSee('Admin lembaga');
        $response->assertDontSee('Tahun ajaran');
        $response->assertSee('Lembaga aktif');
        $response->assertSee('API client aktif');
        $response->assertDontSee('admin-sidebar__badge');

        $menu = app(AdminMenu::class)->forUser($user);
        $this->assertSame(['Dashboard', 'Lembaga'], $menu->pluck('label')->all());
    }

    public function test_admin_lembaga_dashboard_shows_ordered_menu_without_sa_items(): void
    {
        $lembaga = Lembaga::factory()->create(['nama' => 'Sekolah Contoh']);
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tahun ajaran');
        $response->assertDontSee('Admin lembaga');
        $response->assertSee('Sekolah Contoh');
        $response->assertSee('API client');
        $response->assertDontSee('admin-sidebar__badge');

        $menu = app(AdminMenu::class)->forUser($user);
        $apiClientItem = $menu->firstWhere('label', 'API client');
        $this->assertNotNull($apiClientItem);
        $this->assertTrue($apiClientItem['available']);
        $this->assertSame('admin.api-clients.index', $apiClientItem['route']);

        foreach ([
            'Tahun ajaran' => 'admin.tahun-ajaran.index',
            'Guru' => 'admin.guru.index',
            'Kelas' => 'admin.kelas.index',
            'Siswa' => 'admin.siswa.index',
            'Karyawan' => 'admin.karyawan.index',
        ] as $label => $expectedRoute) {
            $item = $menu->firstWhere('label', $label);
            $this->assertNotNull($item, "Menu item {$label} not found");
            $this->assertTrue($item['available'], "Menu item {$label} should be available");
            $this->assertSame($expectedRoute, $item['route']);
        }

        $html = $response->getContent();
        $tahunPos = strpos($html, 'Tahun ajaran');
        $guruPos = strpos($html, 'Guru');
        $this->assertNotFalse($tahunPos);
        $this->assertNotFalse($guruPos);
        $this->assertLessThan($guruPos, $tahunPos);
    }

    public function test_dashboard_footer_shows_local_env_badge_and_copyright_year(): void
    {
        config(['app.env' => 'local']);
        config(['security.mfa.super_admin_required' => false]);

        $user = User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Lokal')
            ->assertSee('&copy; '.now()->year, false);
    }

    public function test_admin_lembaga_kelas_index_is_reachable_from_menu(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->get(route('admin.kelas.index'))
            ->assertOk()
            ->assertSee('Kelas');
    }

    public function test_guest_admin_redirects_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('login'));
    }
}
