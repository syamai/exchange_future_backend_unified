<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleRecaptchaRule implements Rule
{
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
        $client = new Client([
            'base_uri' => config('blockchain.google_recaptcha_uri')
        ]);

        $response = $client->post('siteverify', [
            'query' => [
                'secret' => config('blockchain.google_recaptcha_secret'),
                'response' => $value
            ]
        ]);

        Log::info("===================GoogleRecaptchaRule===============================");
        Log::info([$response, $response->getBody(), $response->getBody()->getContents()]);

        return json_decode($response->getBody())->success;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'google.recaptcha.errors';
    }
}
