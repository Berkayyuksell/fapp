<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'zt_settings';
    protected $fillable = ['key', 'value', 'type', 'description'];

    /**
     * Ayar değerini al
     */
    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Ayar değerini kaydet
     */
    public static function set($key, $value, $type = 'string', $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description
            ]
        );
    }

    /**
     * Tüm ayarları key-value array olarak al
     */
    public static function getAllSettings()
    {
        return static::pluck('value', 'key')->toArray();
    }
}
