<?php


namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class CheckFa extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return CheckFun::class;
    }
}
