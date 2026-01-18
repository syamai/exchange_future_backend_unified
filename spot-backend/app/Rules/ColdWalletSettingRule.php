<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ColdWalletSettingRule implements Rule
{
    public $lstErr;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->lstErr = '';
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
        foreach ($value as $item) {
            if (empty($item['address']) && empty($item['lowerThreshold']) && empty($item['upperThreshold'])) {
                continue;
            }
            if (empty($item['address']) || empty($item['lowerThreshold']) || empty($item['upperThreshold'])) {
                $this->lstErr .= '{';

                if (empty($item['address'])) {
                    $this->lstErr .= '"' . $item['currency'] . '_address": ["' . __('cold_wallet_setting.' . $item['currency'] . '_address.required') . '"],';
                }
                if (empty($item['lowerThreshold'])) {
                    $this->lstErr .= '"' . $item['currency'] . '_min_balance": ["' . __('cold_wallet_setting.' . $item['currency'] . '_min_balance.required') . '"],';
                }
                if (empty($item['upperThreshold'])) {
                    $this->lstErr .= '"' . $item['currency'] . '_max_balance": ["' . __('cold_wallet_setting.' . $item['currency'] . '_max_balance.required') . '"],';
                }

                $this->lstErr = substr($this->lstErr, 0, strlen($this->lstErr) - 1);

                $this->lstErr .= '},';
            }
        }

        if ($this->lstErr != '') {
            $this->lstErr = substr($this->lstErr, 0, strlen($this->lstErr) - 1);

            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->lstErr;
    }
}
