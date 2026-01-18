<?php
/**
 * Created by PhpStorm.
 * Date: 7/22/19
 * Time: 10:34 AM
 */

namespace IPActive;

class Define
{
    /**
     * Action name
     */
    const REGISTER = 1;

    const RESEND_CONFIRMATION_EMAIL = 2;

    const SEND_RESET_PASSWORD = 3;

    const TRANSFER_BALANCE = 4;

    const LOGIN = 5;

    /**
     * Middleware name
     */

    const REGISTER_MIDDLEWARE = 'ip-active:' . self::REGISTER;

    const RESEND_CONFIRMATION_EMAIL_MIDDLEWARE = 'ip-active:' . self::RESEND_CONFIRMATION_EMAIL;

    const SEND_RESET_PASSWORD_MIDDLEWARE = 'ip-active:' . self::SEND_RESET_PASSWORD;

    const TRANSFER_BALANCE_MIDDLEWARE = 'ip-active:' . self::TRANSFER_BALANCE;

    const LOGIN_MIDDLEWARE = 'ip-active:' . self::LOGIN;

    public static function getListValidTime()
    {
        return [
            self::REGISTER => config('ip-active.register_time'),
            self::RESEND_CONFIRMATION_EMAIL => config('ip-active.resend_confirmation_email_time'),
            self::SEND_RESET_PASSWORD => config('ip-active.send_reset_password_time'),
            self::TRANSFER_BALANCE => config('ip-active.transfer_balance_time'),
            self::LOGIN => config('ip-active.login_time', 60)
        ];
    }

    public static function getValidTime($action)
    {
        return self::getListValidTime()[$action];
    }
}
