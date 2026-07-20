<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Support\Authorization\TenantAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lembaga_sees_only_own_guru_via_global_scope(): void
    {
        [$adminA, $guruA, $guruB] = $this->seedTwoLembagaGurus();

        $this->actingAs($adminA);

        $visible = Guru::all();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->first()->is($guruA));
        $this->assertFalse($visible->contains(fn (Guru $guru) => $guru->is($guruB)));
    }

    public function test_admin_lembaga_gate_denies_view_of_foreign_guru(): void
    {
        [$adminA, $guruA, $guruB] = $this->seedTwoLembagaGurus();

        $this->assertTrue(Gate::forUser($adminA)->allows('view', $guruA));
        $this->assertFalse(Gate::forUser($adminA)->allows('view', $guruB));
    }

    public function test_super_admin_sees_gurus_from_all_lembaga(): void
    {
        [, $guruA, $guruB] = $this->seedTwoLembagaGurus();
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'lembaga_id' => null]);

        $this->actingAs($superAdmin);

        $visible = Guru::all();

        $this->assertCount(2, $visible);
        $this->assertTrue($visible->contains(fn (Guru $guru) => $guru->is($guruA)));
        $this->assertTrue($visible->contains(fn (Guru $guru) => $guru->is($guruB)));
    }

    public function test_tenant_authorizer_blocks_cross_tenant_view_and_audits(): void
    {
        [$adminA, , $guruB] = $this->seedTwoLembagaGurus();

        try {
            app(TenantAuthorizer::class)->authorizeView($adminA, $guruB);
            $this->fail('Expected HttpException with status 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $audit = AuditLog::query()
            ->where('event', 'authz.cross_tenant')
            ->where('result', 'blocked')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($adminA->id, $audit->user_id);
        $this->assertSame($adminA->lembaga_id, $audit->lembaga_id);
        $this->assertSame(Guru::class, $audit->metadata['subject_type']);
        $this->assertSame($guruB->id, $audit->metadata['subject_id']);
    }

    public function test_guest_guru_query_returns_empty(): void
    {
        $this->seedTwoLembagaGurus();

        $this->assertCount(0, Guru::all());
    }

    /**
     * @return array{0: User, 1: Guru, 2: Guru}
     */
    private function seedTwoLembagaGurus(): array
    {
        $lembagaA = Lembaga::factory()->create();
        $lembagaB = Lembaga::factory()->create();

        $adminA = User::factory()->adminLembaga($lembagaA->id)->create();

        $guruA = Guru::withoutGlobalScopes()->create([
            'lembaga_id' => $lembagaA->id,
            'nama' => 'Guru A',
        ]);
        $guruB = Guru::withoutGlobalScopes()->create([
            'lembaga_id' => $lembagaB->id,
            'nama' => 'Guru B',
        ]);

        return [$adminA, $guruA, $guruB];
    }
}
