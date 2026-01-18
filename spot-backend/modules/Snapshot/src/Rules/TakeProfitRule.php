<?php

namespace Snapshot\Rules;

use App\Http\Services\HotWalletService;
use Illuminate\Contracts\Validation\Rule;

class TakeProfitRule implements Rule
{
    protected $currency;

    public function __construct($currency)
    {
        $this->currency = $currency;
    }

    public function passes($attribute, $value)
    {
        $service = new HotWalletService();
        $dataCoins = $service->totalBalance($this->currency);

        if ($value < $dataCoins) {
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
        return 'The validation error message.';
    }
}
