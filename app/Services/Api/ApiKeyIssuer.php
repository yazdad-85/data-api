<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use Illuminate\Support\Str;
use RuntimeException;

final class ApiKeyIssuer
{
    /**
     * @return array{plain: string, prefix: string, digest: string}
     */
    public function issue(): array
    {
        $pepper = (string) config('security.api_key_pepper');
        if ($pepper === '') {
            throw new RuntimeException('API_KEY_PEPPER is not configured.');
        }

        $prefix = $this->uniquePrefix();
        $secret = Str::lower(Str::random(32));
        $plain = 'dc_live_'.$prefix.'_'.$secret;
        $digest = hash_hmac('sha256', $plain, $pepper);

        return [
            'plain' => $plain,
            'prefix' => $prefix,
            'digest' => $digest,
        ];
    }

    private function uniquePrefix(): string
    {
        for ($i = 0; $i < 8; $i++) {
            // 12 chars fits DB string(16); URL-safe lowercase
            $prefix = Str::lower(Str::random(12));
            if (! ApiClient::query()->where('api_key_prefix', $prefix)->exists()) {
                return $prefix;
            }
        }

        throw new RuntimeException('Unable to allocate unique API key prefix.');
    }
}
