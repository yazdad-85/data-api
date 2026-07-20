<?php

namespace Tests\Feature;

use App\Models\Lembaga;
use App\Models\User;
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

    public function test_admin_lembaga_coming_soon_guru_page_shows_segera_hadir(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->get(route('admin.coming-soon', ['feature' => 'guru']))
            ->assertOk()
            ->assertSee('Segera hadir');
    }

    public function test_guest_admin_redirects_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('login'));
    }
}
