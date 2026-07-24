<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UpdateSettings
{
    public function handle(array $state): void
    {
        foreach (Arr::dot($state) as $key => $value) {
            if ($key === 'site.logo' && blank($value)) {
                continue;
            }

            [$plain, $type] = $this->normalizeValue($value);

            Setting::updateOrCreate([
                'key' => $key,
            ], [
                'value' => $plain,
                'type' => $type,
                'group' => str($key)->before('.')->value(),
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeValue(mixed $value): array
    {
        if ($value instanceof TemporaryUploadedFile) {
            return [$value->storePublicly('logos', 'public'), 'string'];
        }

        if (is_array($value)) {
            $firstItem = reset($value);

            return $firstItem instanceof TemporaryUploadedFile
                ? [$firstItem->storePublicly('logos', 'public'), 'string']
                : ['', 'string'];
        }

        if (is_bool($value)) {
            return [$value ? 'true' : 'false', 'boolean'];
        }

        if (is_scalar($value)) {
            return [(string) $value, 'string'];
        }

        return ['', 'string'];
    }
}
