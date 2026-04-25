<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Services\FireImageUpdatedEvent;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, FireImageUpdatedEvent;

    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => 'string',
        'value' => 'encrypted', // auto decrypt/encrypt
    ];

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            $setting->forgetCachedValues();
        });

        static::deleted(function (self $setting): void {
            $setting->forgetCachedValues();
        });
    }

    /**
     * Retrieve a setting value by its key, using cache and a fallback default.
     *
     * @param  string       $key      The setting key.
     * @param  mixed|null   $default  The default value to return if not found.
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        return cache()->remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->value('value');
        }) ?? $default;
    }

    /**
     * Retrieve a setting value as a boolean, using cache and a fallback default.
     *
     * @param  string       $key      The setting key.
     * @param  mixed|null   $default  The default value to use if not found.
     * @return bool
     */
    public static function getBooleanValue(string $key, $default = false): bool
    {
        return filter_var(
            static::getValue($key, $default),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function forgetCachedValues(): void
    {
        $keys = array_filter([
            $this->key,
            $this->getOriginal('key'),
        ]);

        foreach (array_unique($keys) as $key) {
            cache()->forget("setting.{$key}");
        }
    }
}
