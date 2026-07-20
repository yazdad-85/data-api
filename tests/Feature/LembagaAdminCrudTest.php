<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LembagaAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        config(['security.mfa.super_admin_required' => false]);

        return User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);
    }

    public function test_super_admin_creates_lembaga_and_sees_it_in_index_and_show(): void
    {
        $sa = $this->superAdmin();

        $response = $this->actingAs($sa)->post(route('admin.lembaga.store'), [
            'kode' => 'LBG-001',
            'nama' => 'SMA Lembaga Baru',
        ]);

        $lembaga = Lembaga::query()->where('kode', 'LBG-001')->firstOrFail();
        $response->assertRedirect(route('admin.lembaga.show', $lembaga));

        $this->actingAs($sa)->get(route('admin.lembaga.index'))
            ->assertOk()
            ->assertSee('SMA Lembaga Baru');

        $this->actingAs($sa)->get(route('admin.lembaga.show', $lembaga))
            ->assertOk()
            ->assertSee('SMA Lembaga Baru')
            ->assertSee('LBG-001');

        $log = AuditLog::query()->where('event', 'lembaga.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($lembaga->id, $log->subject_id);
        $this->assertSame('LBG-001', $log->metadata['kode'] ?? null);
    }

    public function test_super_admin_updates_lembaga_fields(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create(['kode' => 'LBG-002', 'nama' => 'Nama Lama']);

        $this->actingAs($sa)->put(route('admin.lembaga.update', $lembaga), [
            'kode' => 'LBG-002',
            'nama' => 'Nama Baru',
            'jenis' => 'madrasah',
            'kota' => 'Jakarta',
            'provinsi' => 'DKI Jakarta',
            'telepon' => '0211234567',
            'email' => 'lembaga@example.test',
            'alamat' => 'Jl. Contoh No. 1',
        ])->assertRedirect(route('admin.lembaga.show', $lembaga));

        $lembaga->refresh();
        $this->assertSame('Nama Baru', $lembaga->nama);
        $this->assertSame('madrasah', $lembaga->jenis);
        $this->assertSame('Jakarta', $lembaga->kota);
        $this->assertSame('DKI Jakarta', $lembaga->provinsi);
        $this->assertSame('0211234567', $lembaga->telepon);
        $this->assertSame('lembaga@example.test', $lembaga->email);
        $this->assertSame('Jl. Contoh No. 1', $lembaga->alamat);

        $this->assertSame('success', AuditLog::query()->where('event', 'lembaga.update')->value('result'));
    }

    public function test_deactivate_lembaga_records_counts_and_blocks_admin_login(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();

        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin1@example.test',
            'password' => 'StrongPassword123',
        ]);
        User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin2@example.test',
            'password' => 'StrongPassword123',
        ]);

        ApiClient::factory()->for($lembaga)->create();
        ApiClient::factory()->for($lembaga)->revoked()->create();

        $this->actingAs($sa)->get(route('admin.lembaga.show', $lembaga))
            ->assertOk()
            ->assertSee('Nonaktifkan lembaga?')
            ->assertSee('2</strong> Admin Lembaga aktif dan', false)
            ->assertSee('1</strong> API client aktif akan terdampak.', false);

        $this->actingAs($sa)->post(route('admin.lembaga.deactivate', $lembaga))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));

        $this->assertFalse($lembaga->refresh()->is_active);

        $log = AuditLog::query()->where('event', 'lembaga.deactivate')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame(2, $log->metadata['admins_aktif'] ?? null);
        $this->assertSame(1, $log->metadata['api_clients_aktif'] ?? null);

        $this->post('/logout');
        $this->clearAdminLoginRateLimiter('admin1@example.test');

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'admin1@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();
    }

    public function test_activate_lembaga_allows_active_admin_to_login_again(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->inactive()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin3@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->clearAdminLoginRateLimiter('admin3@example.test');
        $this->from(route('login'))->post('/login', [
            'email' => 'admin3@example.test',
            'password' => 'StrongPassword123',
        ])->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();

        $this->actingAs($sa)->post(route('admin.lembaga.activate', $lembaga))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertTrue($lembaga->refresh()->is_active);

        $this->post('/logout');
        $this->assertGuest();
        $this->clearAdminLoginRateLimiter('admin3@example.test');

        $this->post('/login', [
            'email' => 'admin3@example.test',
            'password' => 'StrongPassword123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_create_admin_shows_password_once_and_audits_without_plain(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();

        $response = $this->actingAs($sa)->followingRedirects()->post(
            route('admin.lembaga.admins.store', $lembaga),
            [
                'name' => 'Admin Baru',
                'email' => 'adminbaru@example.test',
            ]
        );

        $response->assertOk();

        $admin = User::query()->where('email', 'adminbaru@example.test')->firstOrFail();

        $plain = $this->extractPasswordFromHtml($response->getContent());
        $this->assertNotNull($plain);
        $this->assertGreaterThanOrEqual(12, strlen($plain));
        $this->assertTrue(Hash::check($plain, $admin->password));

        $log = AuditLog::query()->where('event', 'admin.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->result);
        $this->assertSame($admin->id, $log->subject_id);

        $payload = json_encode($log->toArray());
        $this->assertIsString($payload);
        $this->assertStringNotContainsString($plain, $payload);

        $second = $this->get(route('admin.lembaga.admins.password-once', [$lembaga, $admin]));
        $second->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertStringContainsString(
            'tidak tersedia',
            (string) $second->getSession()->get('status')
        );
    }

    public function test_deactivate_admin_blocks_login(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin6@example.test',
            'password' => 'StrongPassword123',
        ]);

        $this->actingAs($sa)->post(route('admin.lembaga.admins.deactivate', [$lembaga, $admin]))
            ->assertRedirect(route('admin.lembaga.show', $lembaga));

        $this->assertFalse($admin->refresh()->is_active);
        $this->assertSame('success', AuditLog::query()->where('event', 'admin.deactivate')->value('result'));

        $this->post('/logout');
        $this->clearAdminLoginRateLimiter('admin6@example.test');

        $response = $this->from(route('login'))->post('/login', [
            'email' => 'admin6@example.test',
            'password' => 'StrongPassword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();
    }

    public function test_reset_password_shows_new_plain_and_invalidates_old_password(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create([
            'email' => 'admin7@example.test',
            'password' => 'OldPassword123',
        ]);

        $response = $this->actingAs($sa)->followingRedirects()->post(
            route('admin.lembaga.admins.reset-password', [$lembaga, $admin])
        );
        $response->assertOk();

        $newPlain = $this->extractPasswordFromHtml($response->getContent());
        $this->assertNotNull($newPlain);
        $this->assertNotSame('OldPassword123', $newPlain);
        $this->assertTrue(Hash::check($newPlain, $admin->refresh()->password));

        $this->assertSame('success', AuditLog::query()->where('event', 'admin.reset_password')->value('result'));

        $this->post('/logout');
        $this->clearAdminLoginRateLimiter('admin7@example.test');

        $this->from(route('login'))->post('/login', [
            'email' => 'admin7@example.test',
            'password' => 'OldPassword123',
        ])->assertSessionHasErrors([
            'email' => AdminAuthenticator::FAILURE_MESSAGE,
        ]);
        $this->assertGuest();

        $this->clearAdminLoginRateLimiter('admin7@example.test');

        $this->post('/login', [
            'email' => 'admin7@example.test',
            'password' => $newPlain,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $second = $this->actingAs($sa)->get(route('admin.lembaga.admins.password-once', [$lembaga, $admin]));
        $second->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertStringContainsString(
            'tidak tersedia',
            (string) $second->getSession()->get('status')
        );
    }

    public function test_password_once_flash_does_not_leak_across_admins(): void
    {
        $sa = $this->superAdmin();
        $lembaga = Lembaga::factory()->create();

        $createA = $this->actingAs($sa)->post(route('admin.lembaga.admins.store', $lembaga), [
            'name' => 'Admin A',
            'email' => 'admina-flash@example.test',
        ]);
        $adminA = User::query()->where('email', 'admina-flash@example.test')->firstOrFail();
        $passwordA = $createA->getSession()->get('generated_password')['password'] ?? null;
        $this->assertIsString($passwordA);
        $createA->assertRedirect(route('admin.lembaga.admins.password-once', [$lembaga, $adminA]));

        $createB = $this->actingAs($sa)->post(route('admin.lembaga.admins.store', $lembaga), [
            'name' => 'Admin B',
            'email' => 'adminb-flash@example.test',
        ]);
        $adminB = User::query()->where('email', 'adminb-flash@example.test')->firstOrFail();
        $passwordB = $createB->getSession()->get('generated_password')['password'] ?? null;
        $this->assertIsString($passwordB);
        $this->assertNotSame($passwordA, $passwordB);
        $createB->assertRedirect(route('admin.lembaga.admins.password-once', [$lembaga, $adminB]));

        $response = $this->actingAs($sa)->get(route('admin.lembaga.admins.password-once', [$lembaga, $adminA]));
        $response->assertRedirect(route('admin.lembaga.show', $lembaga));
        $this->assertStringContainsString(
            'tidak tersedia',
            (string) $response->getSession()->get('status')
        );

        $show = $this->actingAs($sa)->get(route('admin.lembaga.show', $lembaga));
        $show->assertDontSee($passwordA);
        $show->assertDontSee($passwordB);
    }

    public function test_admin_lembaga_gets_forbidden_on_lembaga_index(): void
    {
        $lembaga = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($admin)->get(route('admin.lembaga.index'))
            ->assertForbidden();
    }

    public function test_admin_of_lembaga_a_cannot_be_updated_via_lembaga_b_url(): void
    {
        $sa = $this->superAdmin();
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();
        $admin = User::factory()->adminLembaga($lembagaA->id)->create([
            'name' => 'Admin Lembaga A',
            'email' => 'admina@example.test',
        ]);

        $this->actingAs($sa)->put(route('admin.lembaga.admins.update', [$lembagaB, $admin]), [
            'name' => 'Nama Diretas',
            'email' => 'diretas@example.test',
        ])->assertNotFound();

        $admin->refresh();
        $this->assertSame('Admin Lembaga A', $admin->name);
        $this->assertSame('admina@example.test', $admin->email);
    }

    public function test_index_search_matches_kode(): void
    {
        $sa = $this->superAdmin();
        Lembaga::factory()->create(['kode' => 'LBG-SEARCH', 'nama' => 'Yayasan Uji Coba']);
        Lembaga::factory()->create(['kode' => 'LBG-OTHER', 'nama' => 'Yayasan Lain']);

        $this->actingAs($sa)->get(route('admin.lembaga.index', ['q' => 'lbg-search']))
            ->assertOk()
            ->assertSee('Yayasan Uji Coba')
            ->assertDontSee('Yayasan Lain');
    }

    private function extractPasswordFromHtml(string $html): ?string
    {
        if (preg_match('/id="admin-password"[^>]*value="([^"]*)"/', $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1]);
    }

    private function clearAdminLoginRateLimiter(string $email, string $ip = '127.0.0.1'): void
    {
        $email = Str::lower($email);

        RateLimiter::clear(md5('admin-login'.$email.'|'.$ip));
        RateLimiter::clear(md5('admin-login'.'ip:'.$ip));
    }
}
