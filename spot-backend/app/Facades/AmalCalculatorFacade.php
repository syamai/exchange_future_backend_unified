<?php
/**
 * Created by PhpStorm.
 * Date: 4/24/19
 * Time: 10:49 AM
 */

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class AmalCalculatorFacade extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'AmalCalculator';
    }
}
