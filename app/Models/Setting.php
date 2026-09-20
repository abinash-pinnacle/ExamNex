<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Defaults returned when a key has never been set. */
    public const DEFAULTS = [
        'org_name' => 'ExamNex',
        'tagline' => 'Test • Learn • Grow',
        'brand_color' => '#2563eb',
        'default_duration' => '60',
        'default_passing' => '40',
        'default_max_attempts' => '1',
        'default_result_visibility' => 'AFTER_REVIEW',
        'conducted_by' => 'NMIET B-SCHOOL',
        'conducted_by_sub' => 'Placement Drive 2026',
    ];

    protected static function map(): array
    {
        try {
            return Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            return []; // table not migrated yet
        }
    }

    public static function get(string $key, $default = null)
    {
        $val = static::map()[$key] ?? null;
        if ($val !== null && $val !== '') {
            return $val;
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    public static function putMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            static::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Cache::forget('settings.all');
    }

    // ---- Brand color as space-separated RGB channels (for CSS var / Tailwind alpha) ----
    public static function brandRgb(): string
    {
        return static::hexToRgb(static::get('brand_color', '#2563eb'));
    }

    public static function brandDarkRgb(): string
    {
        return static::hexToRgb(static::darken(static::get('brand_color', '#2563eb'), 0.82));
    }

    private static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '2563eb';
        }
        return hexdec(substr($hex, 0, 2)) . ' ' . hexdec(substr($hex, 2, 2)) . ' ' . hexdec(substr($hex, 4, 2));
    }

    private static function darken(string $hex, float $f): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            $hex = '2563eb';
        }
        $r = (int) (hexdec(substr($hex, 0, 2)) * $f);
        $g = (int) (hexdec(substr($hex, 2, 2)) * $f);
        $b = (int) (hexdec(substr($hex, 4, 2)) * $f);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
