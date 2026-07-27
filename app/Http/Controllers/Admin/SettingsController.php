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
        } catch (RuntimeException $exception) {
            $this->auditLogger->record('settings.backup_download', 'failed', [
                'reason' => 'exporter_failed',
            ], user: $request->user(), request: $request);

            return redirect()
                ->route('admin.settings.show')
                ->withErrors(['backup' => $exception->getMessage()]);
        }

        $this->auditLogger->record(
            'settings.backup_download',
            'success',
            [],
            user: $request->user(),
            request: $request,
        );

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }
}
