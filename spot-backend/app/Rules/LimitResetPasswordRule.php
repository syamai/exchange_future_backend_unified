<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class LimitResetPasswordRule implements Rule
{
    protected $validTime;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $time = DB::table('password_resets')->where('email', $value)->max('created_at');

        if (empty($time)) {
            return true;
        }

        $this->validTime = config('app.range_time_reset_password', 120);

        $now = Carbon::now();
        $diffTime = $now->diffInSeconds($time);

        if ($diffTime > $this->validTime) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('validation.range_time_reset_password.', ['time' => $this->validTime]);
    }
}
