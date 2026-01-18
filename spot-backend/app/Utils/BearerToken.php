<?php

namespace App\Utils;

use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class BearerToken
{
    public static function fromRequest()
    {
        $header = request()->header('authorization');
        $jwt = trim(preg_replace('/^(?:\s+)?Bearer\s/', '', $header));
        return self::fromJWT($jwt);
    }

    public static function fromJWT($jwt)
    {
        $token = (new Parser(new JoseEncoder()))->parse($jwt);
        return Token::find($token->claims()->get('jti'));
    }
}
