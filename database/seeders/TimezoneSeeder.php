<?php

namespace Database\Seeders;

use App\Models\Timezone;
use Illuminate\Database\Seeder;

class TimezoneSeeder extends Seeder
{
    public function run(): void
    {
        $identifiers = \DateTimeZone::listIdentifiers();

        $timezones = collect($identifiers)->map(function (string $identifier): array {
            $tz = new \DateTimeZone($identifier);
            $offsetSeconds = $tz->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
            $offsetMinutes = (int) ($offsetSeconds / 60);
            $sign = $offsetMinutes >= 0 ? '+' : '-';
            $abs = abs($offsetMinutes);
            $hours = str_pad((string) floor($abs / 60), 2, '0', STR_PAD_LEFT);
            $minutes = str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT);
            $offset = "{$sign}{$hours}:{$minutes}";

            return [
                'name' => $identifier,
                'label' => "{$identifier} (UTC{$offset})",
                'offset' => $offset,
                'offset_minutes' => $offsetMinutes,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        // Insert in chunks to avoid hitting parameter limits
        foreach (array_chunk($timezones, 100) as $chunk) {
            Timezone::upsert($chunk, ['name'], ['label', 'offset', 'offset_minutes', 'updated_at']);
        }
    }
}
