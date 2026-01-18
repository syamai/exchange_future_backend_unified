<?php
namespace App\Traits;

trait UsesUnixTimestamps
{
    public static function bootUsesUnixTimestamps()
    {
        static::creating(function ($model) {
            if ($model->timestamps !== false) {
                $now = round(microtime(true) * 1000);
                $model->created_at = $now;
                $model->updated_at = $now;
            }
        });

        static::updating(function ($model) {
            if ($model->timestamps !== false) {
                $model->updated_at = round(microtime(true) * 1000);
            }
        });
    }
}

