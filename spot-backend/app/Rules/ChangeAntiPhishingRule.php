<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ChangeAntiPhishingRule implements Rule
{
    protected $isAntiPhishing;
    public $error;

    public function __construct($isAntiPhishing)
    {
        $this->isAntiPhishing = $isAntiPhishing;
        $this->error = '';
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
        if (!(int)$this->isAntiPhishing) {
            return true;
        }
        if (is_null($value)) {
            $this->error = 'code.require';
        }
        if (!ctype_alnum((string)$value)) {
            $this->error = 'code.invalid';
        }

        if ($this->error) {
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
        return $this->error;
    }
}
