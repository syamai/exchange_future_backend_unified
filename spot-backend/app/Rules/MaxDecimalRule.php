<?php

namespace App\Rules;

use App\Consts;
use App\Models\Coin;
use Illuminate\Contracts\Validation\Rule;

class MaxDecimalRule implements Rule
{
    protected $currency;
    protected $maxDecimal;

    public function __construct($currency)
    {
        $this->currency = $currency;
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
        $coin = Coin::query()->where('coin', $this->currency)->first();

        if (!$coin) {
            return false;
        }

        $decimal = $coin->decimal;
        if ($coin->coin == Consts::CURRENCY_XRP || $coin->coin == Consts::CURRENCY_EOS) {
            $decimal = Consts::EOS_XRP_DECIMAL;
        }

        if ($this->getMaxDecimal($decimal) >= $this->getDecimal($value)) {
            return true;
        }

        return false;
    }

    protected function getMaxDecimal($decimal)
    {
        if ($decimal >= 8) {
            return $this->maxDecimal = 8;
        }

        return $this->maxDecimal = $decimal;
    }


    protected function getDecimal($value)
    {
        $value = (string) $value;

        $numberArr = explode('.', $value);

        if (count($numberArr) === 1) {
            return 0;
        }

        return strlen($numberArr[1]);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('exception.max_decimal_msg', ['max_decimal' => $this->maxDecimal]);
    }
}
