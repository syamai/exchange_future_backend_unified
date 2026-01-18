<?php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class DataExport extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'DataExport';
    }
}