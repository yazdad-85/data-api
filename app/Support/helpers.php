<?php

use App\Services\Settings\AppSettingsService;
use App\Support\Security\RequestId;

if (! function_exists('request_id')) {
    function request_id(): ?string
    {
        return RequestId::current();
    }
}

if (! function_exists('app_branding')) {
    /**
     * @return array{name: string, logo_url: string|null, favicon_url: string|null}
     */
    function app_branding(): array
    {
        return app(AppSettingsService::class)->branding();
    }
}
