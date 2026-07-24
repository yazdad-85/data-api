<?php

namespace App\Services\Api;

use App\Support\Api\ApiErrorResponse;
use App\Support\Api\ApiSyncCursor;
use Carbon\Carbon;

final class ApiSyncQueryValidator
{
    /**
     * @param  array{since?: mixed, cursor?: mixed, watermark?: mixed, per_page?: mixed}  $input
     * @return array{
     *   ok: true,
     *   since: Carbon,
     *   watermark: ?Carbon,
     *   cursor: ?array{changed_at: Carbon, id: string},
     *   per_page: int,
     *   is_first_page: bool
     * }|array{ok: false, code: string, status: int, message: string}
     */
    public function validate(array $input, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now('UTC'))->utc();

        $sinceRaw = $input['since'] ?? null;
        if (! is_string($sinceRaw) || trim($sinceRaw) === '') {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        try {
            $since = Carbon::parse($sinceRaw)->utc();
        } catch (\Throwable) {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        if ($since->greaterThan($now)) {
            return $this->fail(ApiErrorResponse::INVALID_SINCE, 'Parameter since tidak valid.', 400);
        }

        $maxDays = max(1, (int) config('security.api_sync_max_since_days', 90));
        if ($since->lt($now->copy()->subDays($maxDays))) {
            return $this->fail(
                ApiErrorResponse::SINCE_TOO_OLD,
                'Parameter since terlalu lama; gunakan tarik penuh.',
                400
            );
        }

        $cursorRaw = $input['cursor'] ?? null;
        $watermarkRaw = $input['watermark'] ?? null;
        $hasCursor = is_string($cursorRaw) && $cursorRaw !== '';

        if (! $hasCursor) {
            if (is_string($watermarkRaw) && $watermarkRaw !== '') {
                return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
            }

            $perPage = $this->clampPerPage($input['per_page'] ?? 100);

            return [
                'ok' => true,
                'since' => $since,
                'watermark' => null,
                'cursor' => null,
                'per_page' => $perPage,
                'is_first_page' => true,
            ];
        }

        if (! is_string($watermarkRaw) || $watermarkRaw === '') {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        try {
            $watermark = Carbon::parse($watermarkRaw)->utc();
            $cursor = ApiSyncCursor::decode($cursorRaw);
        } catch (\Throwable) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        if ($watermark->greaterThan($now)) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        if ($cursor['changed_at']->lte($since) || $cursor['changed_at']->greaterThan($watermark)) {
            return $this->fail(ApiErrorResponse::INVALID_CURSOR, 'Cursor atau watermark tidak valid.', 400);
        }

        return [
            'ok' => true,
            'since' => $since,
            'watermark' => $watermark,
            'cursor' => $cursor,
            'per_page' => $this->clampPerPage($input['per_page'] ?? 100),
            'is_first_page' => false,
        ];
    }

    private function clampPerPage(mixed $value): int
    {
        $n = is_numeric($value) ? (int) $value : 100;

        return min(max(1, $n), 200);
    }

    /**
     * @return array{ok: false, code: string, status: int, message: string}
     */
    private function fail(string $code, string $message, int $status): array
    {
        return ['ok' => false, 'code' => $code, 'status' => $status, 'message' => $message];
    }
}
