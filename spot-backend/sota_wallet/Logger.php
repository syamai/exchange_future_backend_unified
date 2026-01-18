<?php

namespace SotaWallet;

class Logger
{
    const PACKAGE_NAME = 'SotaWallet';

    public function __call($method, $args)
    {
        list($class, $caller) = debug_backtrace(false, 2);
        return static::writeLog($caller, $method, $args);
    }

    public static function __callStatic($method, $args)
    {
        list($class, $caller) = debug_backtrace(false, 2);
        return static::writeLog($caller, $method, $args);
    }

    private static function writeLog($caller, $method, $args)
    {
        return app('log')->{$method}(self::getMessage($caller, array_shift($args)), ...$args);
    }

    private static function getMessage($caller, $message)
    {
        $str = '[' . self::PACKAGE_NAME . ']';
        if (!empty($caller)) {
            $class = class_basename(\Arr::get($caller, 'class'));
            $str .= ' [' . $class;

            $function = \Arr::get($caller, 'function');
            if ($function) {
                $str .= ':'. $function;
            }

            $str .= '] - ';
        }

        return $str . $message;
    }
}
