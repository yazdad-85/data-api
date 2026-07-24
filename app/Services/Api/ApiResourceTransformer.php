<?php

namespace App\Services\Api;

use App\Models\SiswaPenempatan;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps an Eloquent model into a plain array limited to the allowed field set
 * for the resolved profile, plus siswa lifecycle embeds (design §6.2, §6.3).
 *
 * Date rules (terkunci): columns cast as `date` render as `Y-m-d`; all other
 * date/times (timestamps, deleted_at) render as ISO-8601 UTC `Y-m-d\TH:i:s\Z`.
 */
final class ApiResourceTransformer
{
    /**
     * @param  list<string>  $allowedFields
     * @param  list<string>  $embeds
     * @return array<string, mixed>
     */
    public function transform(Model $model, array $allowedFields, array $embeds = []): array
    {
        $out = [];

        foreach ($allowedFields as $field) {
            $out[$field] = $this->formatValue($model, $field, $model->getAttribute($field));
        }

        if (in_array('penempatan_aktif', $embeds, true)) {
            $out['penempatan_aktif'] = $this->transformPenempatan($model->getAttribute('penempatanAktif'), false);
        }

        if (in_array('riwayat_penempatan', $embeds, true)) {
            $riwayat = $model->getAttribute('penempatans');
            $out['riwayat_penempatan'] = $riwayat === null
                ? []
                : $riwayat->sortBy('mulai_at')->values()
                    ->map(fn (SiswaPenempatan $p): array => $this->transformPenempatan($p, true))
                    ->all();
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function transformPenempatan(?SiswaPenempatan $penempatan, bool $withSelesai): ?array
    {
        if ($penempatan === null) {
            return null;
        }

        $data = [
            'id' => $penempatan->id,
            'kelas_id' => $penempatan->kelas_id,
            'tahun_ajaran_id' => $penempatan->tahun_ajaran_id,
            'mulai_at' => $this->formatValue($penempatan, 'mulai_at', $penempatan->mulai_at),
        ];

        if ($withSelesai) {
            $data['selesai_at'] = $this->formatValue($penempatan, 'selesai_at', $penempatan->selesai_at);
        }

        $data['jenis'] = $penempatan->jenis;

        return $data;
    }

    private function formatValue(Model $model, string $field, mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $carbon = Carbon::instance($value);

            return $this->isDateOnly($model, $field)
                ? $carbon->format('Y-m-d')
                : $carbon->utc()->format('Y-m-d\TH:i:s\Z');
        }

        return $value;
    }

    private function isDateOnly(Model $model, string $field): bool
    {
        $cast = $model->getCasts()[$field] ?? null;

        return in_array($cast, ['date', 'immutable_date'], true);
    }
}
