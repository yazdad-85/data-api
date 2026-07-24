<?php

namespace App\Support\Api;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Opaque sync cursor: base64url JSON {"c":"<ISO8601 Z>","i":"<uuid>"}.
 */
final class ApiSyncCursor
{
    public static function encode(Carbon $changedAt, string $id): string
    {
        $payload = json_encode([
            'c' => $changedAt->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            'i' => $id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * @return array{changed_at: Carbon, id: string}
     */
    public static function decode(string $cursor): array
    {
        $padded = strtr($cursor, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode($padded, true);
        if ($json === false) {
            throw new InvalidArgumentException('Invalid cursor encoding.');
        }

        /** @var mixed $data */
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['c'], $data['i']) || ! is_string($data['c']) || ! is_string($data['i']) || $data['i'] === '') {
            throw new InvalidArgumentException('Invalid cursor payload.');
        }

        try {
            $changedAt = Carbon::parse($data['c'])->utc();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid cursor timestamp.');
        }

        return ['changed_at' => $changedAt, 'id' => $data['i']];
    }
}
