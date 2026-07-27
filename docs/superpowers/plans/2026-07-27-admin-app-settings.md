# Admin App Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Super Admin **Pengaturan** page for UI branding (app name + logo with auto-favicon) and password-gated PostgreSQL dump download.

**Architecture:** Singleton `app_settings` row + public branding files. `AppSettingsService` feeds layouts via helper `app_branding()`. Logo processing uses PHP GD. Backup streams `pg_dump` through `DatabaseBackupExporter` without writing dumps to disk. Routes gated to Super Admin; backup throttled and re-authenticated.

**Tech Stack:** Laravel 13, Blade, FormRequest, Symfony Process (`pg_dump`), PHP GD, PHPUnit, `storage:link` public disk.

**Spec:** `docs/superpowers/specs/2026-07-27-admin-app-settings-design.md`

**PHP for tests:** `/usr/local/Cellar/php/8.5.8/bin/php`

---

## File map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_07_27_000001_create_app_settings_table.php` | Singleton settings table + seed row |
| `app/Models/AppSettings.php` | Eloquent model |
| `app/Services/Settings/AppSettingsService.php` | Read/write + request cache clear |
| `app/Services/Settings/BrandingLogoProcessor.php` | Store logo, generate favicon, delete |
| `app/Services/Settings/DatabaseBackupExporter.php` | Run `pg_dump`, return streamable content |
| `app/Support/helpers.php` | `app_branding()` helper |
| `app/Http/Controllers/Admin/SettingsController.php` | show / updateBranding / downloadBackup |
| `app/Http/Requests/Admin/UpdateBrandingRequest.php` | Validate branding |
| `app/Http/Requests/Admin/DownloadBackupRequest.php` | Validate current password |
| `app/Support/Navigation/AdminMenu.php` | Sidebar item Pengaturan |
| `app/Providers/AppServiceProvider.php` | Rate limiter + gate |
| `routes/web.php` | Register settings routes |
| `resources/views/admin/settings/show.blade.php` | Settings UI |
| `resources/views/layouts/admin.blade.php` | Dynamic name + favicon |
| `resources/views/layouts/guest.blade.php` | Dynamic name + favicon |
| `resources/views/partials/admin-sidebar.blade.php` | Brand name/logo |
| `resources/views/auth/login.blade.php` | Brand name/logo |
| `resources/views/partials/footer.blade.php` | Brand name in footer |
| `tests/Unit/BrandingLogoProcessorTest.php` | Logo/favicon unit tests |
| `tests/Unit/DatabaseBackupExporterTest.php` | Driver/command unit tests |
| `tests/Feature/AdminSettingsTest.php` | Feature coverage |
| `docs/SPEC.md` | §5.1 note |
| `docs/DEPLOYMENT.md` | Manual restore note |

---

### Task 1: Migration, model, AppSettingsService

**Files:**
- Create: `database/migrations/2026_07_27_000001_create_app_settings_table.php`
- Create: `app/Models/AppSettings.php`
- Create: `app/Services/Settings/AppSettingsService.php`
- Modify: `app/Support/helpers.php`
- Create: `tests/Unit/AppSettingsServiceTest.php`

- [ ] **Step 1: Write failing unit test**

Create `tests/Unit/AppSettingsServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\AppSettings;
use App\Services\Settings\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_seeded_default_name(): void
    {
        $settings = app(AppSettingsService::class)->current();

        $this->assertSame('Pusat Data', $settings->app_name);
        $this->assertNull($settings->logo_path);
        $this->assertNull($settings->favicon_path);
    }

    public function test_update_branding_persists_name(): void
    {
        $service = app(AppSettingsService::class);
        $service->updateBranding(appName: 'Data Yayasan', logoPath: null, faviconPath: null);

        $this->assertDatabaseHas('app_settings', [
            'id' => 1,
            'app_name' => 'Data Yayasan',
        ]);
        $this->assertSame('Data Yayasan', $service->current()->app_name);
    }
}
```

- [ ] **Step 2: Run test — expect fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AppSettingsServiceTest
```

Expected: FAIL (missing table/class).

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('app_name', 150);
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            'id' => 1,
            'app_name' => 'Pusat Data',
            'logo_path' => null,
            'favicon_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
```

- [ ] **Step 4: Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    protected $table = 'app_settings';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'app_name',
        'logo_path',
        'favicon_path',
    ];
}
```

- [ ] **Step 5: Service + helper**

`app/Services/Settings/AppSettingsService.php`:

```php
<?php

namespace App\Services\Settings;

use App\Models\AppSettings;
use Illuminate\Support\Facades\Storage;

class AppSettingsService
{
    private ?AppSettings $cached = null;

    public function current(): AppSettings
    {
        return $this->cached ??= AppSettings::query()->findOrFail(1);
    }

    public function updateBranding(?string $appName = null, ?string $logoPath = null, ?string $faviconPath = null, bool $clearLogo = false): AppSettings
    {
        $settings = $this->current();

        if ($appName !== null) {
            $settings->app_name = $appName;
        }

        if ($clearLogo) {
            $settings->logo_path = null;
            $settings->favicon_path = null;
        } else {
            if ($logoPath !== null) {
                $settings->logo_path = $logoPath;
            }
            if ($faviconPath !== null) {
                $settings->favicon_path = $faviconPath;
            }
        }

        $settings->save();
        $this->forget();

        return $this->current();
    }

    public function forget(): void
    {
        $this->cached = null;
    }

    /**
     * @return array{name: string, logo_url: string|null, favicon_url: string|null}
     */
    public function branding(): array
    {
        $settings = $this->current();

        return [
            'name' => $settings->app_name,
            'logo_url' => $settings->logo_path
                ? Storage::disk('public')->url($settings->logo_path)
                : null,
            'favicon_url' => $settings->favicon_path
                ? Storage::disk('public')->url($settings->favicon_path)
                : ($settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null),
        ];
    }
}
```

In `app/Support/helpers.php`, add:

```php
use App\Services\Settings\AppSettingsService;

if (! function_exists('app_branding')) {
    /**
     * @return array{name: string, logo_url: string|null, favicon_url: string|null}
     */
    function app_branding(): array
    {
        return app(AppSettingsService::class)->branding();
    }
}
```

Bind service as singleton in `AppServiceProvider::register()`:

```php
$this->app->singleton(\App\Services\Settings\AppSettingsService::class);
```

- [ ] **Step 6: Run tests — expect pass**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AppSettingsServiceTest
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_27_000001_create_app_settings_table.php \
  app/Models/AppSettings.php \
  app/Services/Settings/AppSettingsService.php \
  app/Support/helpers.php \
  app/Providers/AppServiceProvider.php \
  tests/Unit/AppSettingsServiceTest.php
git commit -m "$(cat <<'EOF'
feat(settings): add app_settings singleton and branding service

Store display name and logo paths in a single settings row for the
upcoming Super Admin pengaturan UI.
EOF
)"
```

---

### Task 2: BrandingLogoProcessor

**Files:**
- Create: `app/Services/Settings/BrandingLogoProcessor.php`
- Create: `tests/Unit/BrandingLogoProcessorTest.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Services\Settings\BrandingLogoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingLogoProcessorTest extends TestCase
{
    public function test_store_writes_logo_and_favicon(): void
    {
        Storage::fake('public');

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $file = UploadedFile::fake()->image('logo.png', 200, 100);

        $result = app(BrandingLogoProcessor::class)->store($file);

        Storage::disk('public')->assertExists($result['logo_path']);
        Storage::disk('public')->assertExists($result['favicon_path']);
        $this->assertStringStartsWith('branding/', $result['logo_path']);
        $this->assertSame('branding/favicon.png', $result['favicon_path']);
    }

    public function test_delete_removes_existing_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'x');
        Storage::disk('public')->put('branding/favicon.png', 'y');

        app(BrandingLogoProcessor::class)->delete('branding/logo.png', 'branding/favicon.png');

        Storage::disk('public')->assertMissing('branding/logo.png');
        Storage::disk('public')->assertMissing('branding/favicon.png');
    }
}
```

- [ ] **Step 2: Run — expect fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=BrandingLogoProcessorTest
```

- [ ] **Step 3: Implement processor**

```php
<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandingLogoProcessor
{
    /**
     * @return array{logo_path: string, favicon_path: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $disk->makeDirectory('branding');

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            throw new RuntimeException('Unsupported logo format.');
        }

        $logoPath = 'branding/logo.'.$extension;
        $disk->put($logoPath, file_get_contents($file->getRealPath()));

        $faviconPath = 'branding/favicon.png';
        $this->writeFaviconPng($file->getRealPath(), $disk->path($faviconPath));

        return [
            'logo_path' => $logoPath,
            'favicon_path' => $faviconPath,
        ];
    }

    public function delete(?string $logoPath, ?string $faviconPath): void
    {
        $disk = Storage::disk('public');

        foreach ([$logoPath, $faviconPath] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function writeFaviconPng(string $sourcePath, string $destPath): void
    {
        if (! extension_loaded('gd')) {
            // Fallback: copy source bytes so favicon_url still resolves.
            copy($sourcePath, $destPath);

            return;
        }

        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Unable to read logo image.');
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Unable to decode logo image.');
        }

        $canvas = imagecreatetruecolor(32, 32);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, 32, 32, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, 32, 32, imagesx($source), imagesy($source));
        imagepng($canvas, $destPath);
        imagedestroy($source);
        imagedestroy($canvas);
    }
}
```

- [ ] **Step 4: Run — expect pass**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=BrandingLogoProcessorTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Settings/BrandingLogoProcessor.php tests/Unit/BrandingLogoProcessorTest.php
git commit -m "$(cat <<'EOF'
feat(settings): process branding logo and auto-generate favicon

Store uploaded raster logos under public branding storage and derive a
32x32 PNG favicon without a separate upload field.
EOF
)"
```

---

### Task 3: DatabaseBackupExporter

**Files:**
- Create: `app/Services/Settings/DatabaseBackupExporter.php`
- Create: `tests/Unit/DatabaseBackupExporterTest.php`

- [ ] **Step 1: Write failing unit tests**

```php
<?php

namespace Tests\Unit;

use App\Services\Settings\DatabaseBackupExporter;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupExporterTest extends TestCase
{
    public function test_rejects_non_pgsql_driver(): void
    {
        config(['database.default' => 'sqlite']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL');

        app(DatabaseBackupExporter::class)->export();
    }

    public function test_builds_pg_dump_command_from_config(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'pusdatin',
                'username' => 'pusdatin',
                'password' => 'secret',
            ],
        ]);

        $exporter = app(DatabaseBackupExporter::class);
        $command = $exporter->buildCommand();

        $this->assertContains('pg_dump', $command);
        $this->assertContains('--host=127.0.0.1', $command);
        $this->assertContains('--port=5432', $command);
        $this->assertContains('--username=pusdatin', $command);
        $this->assertContains('--dbname=pusdatin', $command);
        $this->assertContains('--no-owner', $command);
        $this->assertContains('--no-acl', $command);
    }
}
```

- [ ] **Step 2: Run — expect fail**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=DatabaseBackupExporterTest
```

- [ ] **Step 3: Implement exporter**

```php
<?php

namespace App\Services\Settings;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupExporter
{
    /**
     * @return array{0: string, 1: string} [contents, suggestedFilename]
     */
    public function export(): array
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('Backup database hanya tersedia untuk PostgreSQL.');
        }

        $password = (string) config('database.connections.pgsql.password', '');
        $process = new Process($this->buildCommand(), null, [
            'PGPASSWORD' => $password,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Backup gagal dijalankan. Hubungi operator server.');
        }

        $filename = 'pusat-data-'.now()->format('Ymd-His').'.sql';

        return [$process->getOutput(), $filename];
    }

    /**
     * @return list<string>
     */
    public function buildCommand(): array
    {
        $cfg = config('database.connections.pgsql');

        return [
            'pg_dump',
            '--host='.(string) ($cfg['host'] ?? '127.0.0.1'),
            '--port='.(string) ($cfg['port'] ?? '5432'),
            '--username='.(string) ($cfg['username'] ?? ''),
            '--dbname='.(string) ($cfg['database'] ?? ''),
            '--no-owner',
            '--no-acl',
            '--clean',
            '--if-exists',
        ];
    }
}
```

Do **not** put the password on the command line; use `PGPASSWORD` env only.

- [ ] **Step 4: Run — expect pass**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=DatabaseBackupExporterTest
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Settings/DatabaseBackupExporter.php tests/Unit/DatabaseBackupExporterTest.php
git commit -m "$(cat <<'EOF'
feat(settings): add PostgreSQL dump exporter for admin backup

Build a pg_dump command from Laravel DB config and stream output
without writing dump files to the application disk.
EOF
)"
```

---

### Task 4: Settings UI — routes, controller, views, menu (TDD feature)

**Files:**
- Create: `tests/Feature/AdminSettingsTest.php`
- Create: `app/Http/Controllers/Admin/SettingsController.php`
- Create: `app/Http/Requests/Admin/UpdateBrandingRequest.php`
- Create: `app/Http/Requests/Admin/DownloadBackupRequest.php`
- Create: `resources/views/admin/settings/show.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php` (gate + rate limiter)
- Modify: `app/Support/Navigation/AdminMenu.php`
- Modify: layouts/sidebar/login/footer for `app_branding()`

- [ ] **Step 1: Write failing feature tests**

Create `tests/Feature/AdminSettingsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lembaga;
use App\Models\User;
use App\Services\Settings\DatabaseBackupExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        config(['security.mfa.super_admin_required' => false]);

        return User::factory()->create([
            'role' => 'super_admin',
            'lembaga_id' => null,
            'password' => 'OldPassword123!',
        ]);
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get(route('admin.settings.show'))->assertRedirect(route('login'));
    }

    public function test_admin_lembaga_cannot_access_settings(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->adminLembaga($lembaga->id)->create();

        $this->actingAs($user)
            ->get(route('admin.settings.show'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_settings_and_menu_item(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->get(route('admin.settings.show'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('Branding')
            ->assertSee('Backup database');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.settings.show').'"', false)
            ->assertSee('Pengaturan');
    }

    public function test_update_branding_name_updates_sidebar(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Yayasan Data',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHas('status');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertSee('Yayasan Data');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.branding_update',
            'result' => 'success',
        ]);
    }

    public function test_upload_logo_stores_files_and_rejects_svg(): void
    {
        Storage::fake('public');
        $user = $this->superAdmin();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required');
        }

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Pusat Data',
                'logo' => UploadedFile::fake()->image('brand.png', 120, 60),
            ])
            ->assertRedirect(route('admin.settings.show'));

        Storage::disk('public')->assertExists('branding/logo.png');
        Storage::disk('public')->assertExists('branding/favicon.png');

        $this->actingAs($user)
            ->put(route('admin.settings.branding'), [
                'app_name' => 'Pusat Data',
                'logo' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_backup_rejects_wrong_password(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->from(route('admin.settings.show'))
            ->post(route('admin.settings.backup'), [
                'current_password' => 'WrongPassword999!',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_backup_rejects_non_pgsql(): void
    {
        config(['database.default' => 'sqlite']);
        $user = $this->superAdmin();

        $this->actingAs($user)
            ->from(route('admin.settings.show'))
            ->post(route('admin.settings.backup'), [
                'current_password' => 'OldPassword123!',
            ])
            ->assertRedirect(route('admin.settings.show'))
            ->assertSessionHasErrors('backup');
    }

    public function test_backup_streams_download_when_exporter_succeeds(): void
    {
        $user = $this->superAdmin();

        $this->mock(DatabaseBackupExporter::class, function ($mock) {
            $mock->shouldReceive('export')
                ->once()
                ->andReturn(["-- SQL DUMP\n", 'pusat-data-test.sql']);
        });

        $response = $this->actingAs($user)
            ->post(route('admin.settings.backup'), [
                'current_password' => 'OldPassword123!',
            ]);

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('pusat-data-test.sql', $response->headers->get('content-disposition'));
        $this->assertSame("-- SQL DUMP\n", $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.backup_download',
            'result' => 'success',
        ]);

        $audit = AuditLog::query()->where('event', 'settings.backup_download')->first();
        $encoded = json_encode($audit->metadata ?? []);
        $this->assertStringNotContainsString('OldPassword123!', (string) $encoded);
    }
}
```

Adjust factory password casting if needed (same as profile tests — `hashed` cast accepts plain).

- [ ] **Step 2: Run — expect fail (missing route)**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter=AdminSettingsTest
```

- [ ] **Step 3: Gate + rate limiter**

In `AppServiceProvider::boot()`, after existing gates:

```php
Gate::define('manage-app-settings', fn (User $user) => $user->isSuperAdmin());
```

After `admin-profile-password` limiter:

```php
RateLimiter::for('admin-settings-backup', function (Request $request) {
    $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

    return [
        Limit::perMinute(3)->by('settings-backup:'.$userId),
        Limit::perMinute(10)->by('settings-backup-ip:'.$request->ip()),
    ];
});
```

- [ ] **Step 4: Form requests**

`UpdateBrandingRequest`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-app-settings') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
```

`DownloadBackupRequest`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DownloadBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-app-settings') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
        ];
    }
}
```

- [ ] **Step 5: Controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DownloadBackupRequest;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Services\AuditLogger;
use App\Services\Settings\AppSettingsService;
use App\Services\Settings\BrandingLogoProcessor;
use App\Services\Settings\DatabaseBackupExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly BrandingLogoProcessor $logoProcessor,
        private readonly DatabaseBackupExporter $backupExporter,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function show(Request $request): View
    {
        $this->authorize('manage-app-settings');

        return view('admin.settings.show', [
            'settings' => $this->settings->current(),
            'branding' => $this->settings->branding(),
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): RedirectResponse
    {
        $settings = $this->settings->current();
        $removeLogo = $request->boolean('remove_logo');
        $logoChanged = false;

        if ($removeLogo) {
            $this->logoProcessor->delete($settings->logo_path, $settings->favicon_path);
            $this->settings->updateBranding(
                appName: $request->validated('app_name'),
                clearLogo: true,
            );
            $logoChanged = true;
        } elseif ($request->hasFile('logo')) {
            $this->logoProcessor->delete($settings->logo_path, $settings->favicon_path);
            $paths = $this->logoProcessor->store($request->file('logo'));
            $this->settings->updateBranding(
                appName: $request->validated('app_name'),
                logoPath: $paths['logo_path'],
                faviconPath: $paths['favicon_path'],
            );
            $logoChanged = true;
        } else {
            $this->settings->updateBranding(appName: $request->validated('app_name'));
        }

        $this->auditLogger->record('settings.branding_update', 'success', [
            'fields' => ['app_name'],
            'logo_changed' => $logoChanged,
        ], user: $request->user(), request: $request);

        return redirect()
            ->route('admin.settings.show')
            ->with('status', 'Pengaturan branding berhasil disimpan.');
    }

    public function downloadBackup(DownloadBackupRequest $request): StreamedResponse|RedirectResponse
    {
        try {
            [$contents, $filename] = $this->backupExporter->export();
        } catch (RuntimeException $e) {
            $this->auditLogger->record('settings.backup_download', 'failure', [
                'reason' => 'exporter_failed',
            ], user: $request->user(), request: $request);

            return redirect()
                ->route('admin.settings.show')
                ->withErrors(['backup' => $e->getMessage()]);
        }

        $this->auditLogger->record('settings.backup_download', 'success', [], user: $request->user(), request: $request);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}
```

- [ ] **Step 6: Routes**

Inside `auth/active/mfa` admin group in `routes/web.php`:

```php
use App\Http\Controllers\Admin\SettingsController;

Route::get('/pengaturan', [SettingsController::class, 'show'])->name('admin.settings.show');
Route::put('/pengaturan/branding', [SettingsController::class, 'updateBranding'])->name('admin.settings.branding');
Route::post('/pengaturan/backup', [SettingsController::class, 'downloadBackup'])
    ->middleware('throttle:admin-settings-backup')
    ->name('admin.settings.backup');
```

- [ ] **Step 7: AdminMenu**

In Super Admin collection, add:

```php
['label' => 'Pengaturan', 'route' => 'admin.settings.show', 'available' => true],
```

- [ ] **Step 8: Blade view `resources/views/admin/settings/show.blade.php`**

Follow `admin/profile/show.blade.php` patterns:

- `@extends('layouts.admin')`, title/breadcrumb **Pengaturan**
- Flash status + errors
- Form 1: PUT branding — `app_name`, file `logo`, checkbox `remove_logo`, preview images if paths set
- Form 2: POST backup — `current_password`, short help text, button Unduh backup
- `enctype="multipart/form-data"` on branding form

- [ ] **Step 9: Wire branding into layouts**

Replace hardcodes with `app_branding()`:

- `layouts/admin.blade.php`: `$appName = app_branding()['name'];` + `<link rel="icon" href="{{ app_branding()['favicon_url'] }}">` when URL set
- `layouts/guest.blade.php`: default title + favicon from branding
- `partials/admin-sidebar.blade.php`: show logo img if `logo_url`, else text name
- `auth/login.blade.php`: use branding name (+ logo if present)
- `partials/footer.blade.php`: use branding name instead of hardcode

- [ ] **Step 10: Run feature + related shell tests**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test --filter='AdminSettingsTest|AppSettingsServiceTest|BrandingLogoProcessorTest|DatabaseBackupExporterTest|AdminShellTest'
```

Expected: PASS. Fix AdminShellTest if it hard-asserts only “Pusat Data” after branding changes (seed still defaults to Pusat Data — should still pass).

- [ ] **Step 11: Commit**

```bash
git add routes/web.php \
  app/Http/Controllers/Admin/SettingsController.php \
  app/Http/Requests/Admin/UpdateBrandingRequest.php \
  app/Http/Requests/Admin/DownloadBackupRequest.php \
  app/Providers/AppServiceProvider.php \
  app/Support/Navigation/AdminMenu.php \
  resources/views/admin/settings/show.blade.php \
  resources/views/layouts/admin.blade.php \
  resources/views/layouts/guest.blade.php \
  resources/views/partials/admin-sidebar.blade.php \
  resources/views/partials/footer.blade.php \
  resources/views/auth/login.blade.php \
  tests/Feature/AdminSettingsTest.php
git commit -m "$(cat <<'EOF'
feat(admin): add pengaturan page for branding and DB backup

Let Super Admin update app name/logo (auto favicon) from the sidebar
and download a PostgreSQL dump after re-entering their password.
EOF
)"
```

---

### Task 5: Docs sync

**Files:**
- Modify: `docs/SPEC.md` §5.1
- Modify: `docs/DEPLOYMENT.md` (short restore note)
- Optionally: `docs/IMPLEMENTATION_TODO.md`

- [ ] **Step 1: Update SPEC §5.1**

After Profil bullet, add:

```markdown
- Pengaturan aplikasi: nama tampilan + logo (favicon otomatis dari logo); unduh backup database PostgreSQL (wajib password saat ini; restore manual di server).
```

- [ ] **Step 2: DEPLOYMENT note**

Near backup section, add one sentence:

```markdown
UI Super Admin dapat mengunduh dump PostgreSQL dari **Pengaturan**; restore tetap dilakukan manual oleh operator (`psql` / panel), bukan dari aplikasi.
```

- [ ] **Step 3: Commit**

```bash
git add docs/SPEC.md docs/DEPLOYMENT.md docs/IMPLEMENTATION_TODO.md
git commit -m "$(cat <<'EOF'
docs: document Super Admin pengaturan branding and backup

Record the settings sidebar entry, auto-favicon rule, and
password-gated dump download with manual restore.
EOF
)"
```

---

### Task 6: Full verification

- [ ] **Step 1: Full suite**

```bash
/usr/local/Cellar/php/8.5.8/bin/php artisan test
```

Expected: all green (baseline ~297; expect +N new tests).

- [ ] **Step 2: Manual smoke (local)**

1. `php artisan migrate` + `php artisan storage:link`
2. Login Super Admin → sidebar **Pengaturan**
3. Ubah nama → terlihat di sidebar/login
4. Upload PNG logo → favicon muncul di tab
5. Backup dengan password salah → error
6. Backup di sqlite lokal → pesan PostgreSQL only
7. Login sebagai Admin Lembaga → tidak ada menu Pengaturan; URL langsung → 403

- [ ] **Step 3: Production deploy notes (when asked)**

```bash
cd /www/wwwroot/pusdatin.yasmumanyar.or.id
git pull origin main
/www/server/php/85/bin/php /usr/bin/composer install --no-dev --optimize-autoloader
/www/server/php/85/bin/php artisan migrate --force
/www/server/php/85/bin/php artisan storage:link
# ensure pg_dump on PATH for PHP/Apache user
/www/server/php/85/bin/php artisan optimize:clear
/www/server/php/85/bin/php artisan config:cache
/www/server/php/85/bin/php artisan route:cache
/www/server/php/85/bin/php artisan view:cache
```

Do not push unless owner requests.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Super Admin only + sidebar Pengaturan | Task 4 |
| Branding name + logo; auto favicon | Tasks 2, 4 |
| `app_settings` + public branding files | Tasks 1–2 |
| Do not rewrite `.env` APP_NAME | Task 1/4 (service only) |
| Backup dump only; no restore UI | Tasks 3–4 |
| current_password + throttle | Task 4 |
| Audit without secrets | Task 4 |
| SPEC/DEPLOYMENT docs | Task 5 |
| Reject SVG / size limit | Task 4 validation |
| Non-pgsql clear error | Tasks 3–4 |

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-27-admin-app-settings.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task + two-stage review  
2. **Inline Execution** — execute tasks in this session with checkpoints  

Which approach?
