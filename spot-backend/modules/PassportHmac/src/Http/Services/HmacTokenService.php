<?php


namespace PassportHmac\Http\Services;

use App\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use PassportHmac\Define;
use App\Consts;

class HmacTokenService
{
	public function index($user_id)
	{
		$scopes = Define::SCOPES;
		$scopeFilters = [];

		foreach ($scopes as $scope) {
			$scopeFilters[] = "[\"{$scope}\"]";
		}

		$data = Token::where('user_id', $user_id)
			->where('expires_at', '>', now())
			->where('type', '=', 1)
			->orderBy('created_at', 'desc')
			->get();


		return $data->transform(function ($obj) use ($user_id) {
			$id = $this->encodeAPIKEY($obj->id);
			$qrCode = [
				'api_key' => $id,
				'secret_key' => $obj->secret,
				'name' => $obj->name
			];

			$obj->qr_code = Utils::makeQrCodeAccountApiKey(json_encode($qrCode), $user_id);
			$obj->id = $id;
			$obj->secret = $this->maskToken();

			return $obj;
		});
	}

	public static function getIpList($secret)
	{
		return Token::where('secret', $secret)->first();
	}

	private function maskToken()
	{
		return '***********************';
	}

	private function encodeAPIKEY($apiKey)
	{
		$encrypt = '6fe17230cd48b9a5';
		return strtr($apiKey, '0123456789abcdef', $encrypt);
	}

	private function decodeAPIKEY($apiKey)
	{
		$encrypt = '6fe17230cd48b9a5';
		return strtr($apiKey, $encrypt, '0123456789abcdef');
	}

	public function store($user, $scopes)
	{
		$tokenFactory = $user->createToken('', [$scopes]);
		$accessToken = $tokenFactory->accessToken;
		$secret = $this->secretGenerate($tokenFactory);
		return compact('accessToken', 'secret', 'scopes');
	}

	public function destroy($id, $userId)
	{
		if ($id == 'all') {
			$result = Token::where('user_id', $userId)
				->where('type', '=', 1)
				->delete();
		} else {
			$decodeId = $this->decodeAPIKEY($id);
			$token = Token::find($decodeId);
			if ($userId !== $token['user_id']) {
				throw new AuthorizationException('Unauthenticated.');
			}

			$result = Token::destroy($token->id);
		}

		return $result;
	}

    public function update($id, $userId, $params)
    {
        DB::beginTransaction();

        try {
            $decodeId = $this->decodeAPIKEY($id);
            $token = Token::find($decodeId);

            if ($userId !== $token['user_id']) {
                throw new AuthorizationException('Unauthenticated.');
            }

            $scopes = $params['scopes'];
            $ip_restricted = $params['ip_restricted'];
            $token->name = $params['name'];
            $token->scopes = [$scopes];
            /* @phpstan-ignore-next-line */
            $token->ip_restricted = $ip_restricted;
            /* @phpstan-ignore-next-line */
            $token->is_restrict = $params['is_restrict'];
            $token->save();
			$this->setScopeApiKeyToCache($id, $token->scopes);
            Token::query()->where([['secret', $token->secret], ['name', null]])->update([
                'scopes' => [$scopes]
            ]);

            DB::commit();

            return $token;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

	public function create($user, $params)
	{
		$scope = !isset($params['scope']) ? Define::READ : $params['scope'];
		// if ($scope < 4) {
		// 	throw new \Exception("Must include Read permission");
		// }

		$tokenFactory = $user->createToken($params['name'], [$scope]);
		$accessToken = $tokenFactory->accessToken;
		$secret_key = $this->secretGenerate($tokenFactory);

		$id = $tokenFactory->token->id;
		$api_key = $this->encodeAPIKEY($tokenFactory->token->id);
		// $api_key = $tokenFactory->token->id;
		$token = Token::on('master')->find($id);
		/* @phpstan-ignore-next-line */
		$token->type = 1;
		// $token->id = api_key
		$token->save();
		$name = $token->name;
		$qrCode = compact('api_key', 'secret_key', 'name');
//        $qrCodeUrl = $this->getQRCodeGoogleUrl($qrCode);
//        $qrCodeUrl = Utils::makeQrCodeApikey(json_encode($qrCode), $api_key);
		$qrCodeUrl = Utils::makeQrCodeAccountApiKey(json_encode($qrCode), $user->id);
		// $qrCodeUrl = 'Utils::makeQrCodeAccountApiKey(json_encode($qrCode), $user->id)';
		$token['id'] = $api_key;
		$token['secret'] = $secret_key;
		return compact('qrCodeUrl', 'accessToken', 'token');
	}

	public function createTokenPnlChart($user, $params)
	{
		$result = Token::where('user_id', $user->id)
			->where('type', '=', 2)
			->first();
		if (!isset($result)) {
			$tokenFactory = $user->createToken($params['name'], ["4"]);

			$id = $tokenFactory->token->id;
			$encode_id = $this->encodeAPIKEY($id);
			$token = Token::on('master')->find($id);
			/* @phpstan-ignore-next-line */
			$token->type = 2; //type 1 = api_key; 2 = pnl chart
			$token->save();
			$token['id'] = $encode_id;
		} else {
			$token = $result;
			$encode_id = $this->encodeAPIKEY($result->id);
			$token['id'] = $encode_id;
		}

		return $token;
	}

	public function getQRCodeGoogleUrl($input, $params = array())
	{
		$width = !empty($params['width']) && (int)$params['width'] > 0 ? (int)$params['width'] : 200;
		$height = !empty($params['height']) && (int)$params['height'] > 0 ? (int)$params['height'] : 200;
		$level = !empty($params['level']) && array_search($params['level'], array('L', 'M', 'Q', 'H')) !== false ? $params['level'] : 'L';

		$urlencoded = urlencode(json_encode($input));

		return "https://api.qrserver.com/v1/create-qr-code/?data=$urlencoded&size=${width}x${height}&ecc=$level";
	}

	protected function secretGenerate($tokenFactory)
	{
		$secret = Str::random(40);
		$token = $tokenFactory->token;
		$token->secret = $secret;
		$token->save();

		return $secret;
	}

	public function setScopeApiKeyToCache($apiKey, $scopes) {
		Cache::put(Consts::PERMISSION_SUB_KEY . $apiKey, $scopes);
	}
}
