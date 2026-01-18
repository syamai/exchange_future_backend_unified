<?php

namespace App\Http\Services\Blockchain;

use App\Http\Services\MasterdataService;
use App\Consts;
use App\Models\WalletCurrencyConfig;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Coin
{
    private $coin;
    private $type;
    private $walletId;
    private $conversionRate;
    private $basePrefix;
    private $contractAddress;
    private $port;
    private $network_id;
    private $network_symbol;
    private $network_code;

    public function __construct($coin, $walletId, $conversionRate, $type = 'coin', $contractAddress = null, $network_id = null, $network_symbol = null, $network_code = null)
    {
        $this->coin = $coin;
        $this->walletId = $walletId;
        $this->conversionRate = $conversionRate;
        $this->type = $type;
        $this->basePrefix = "/api/{$coin}";
        $this->contractAddress = $contractAddress;
        $this->port = config('blockchain.port_wallet');
        $this->network_id = $network_id;
        $this->network_code = $network_code;
        $this->network_symbol = $network_symbol;
    }

    public function getCoin()
    {
        return $this->coin;
    }

    public function getNetworkId()
    {
        return $this->network_id;
    }

    public function getWalletId()
    {
        if (!$this->walletId) {
            throw new  HttpException(422, __('exception.invalid_wallet_id', ['coin' => $this->coin]));
        }
        return $this->walletId;
    }

    public function getConversionRate()
    {
        if (!$this->conversionRate) {
            throw new HttpException(422, __('exception.unknown_conversion', ['coin' => $this->coin]));
        }
        return $this->conversionRate;
    }

    public function isEthErc20Token()
    {
        return $this->type === 'eth_token';
    }

    public function isBEP20Token()
    {
        return $this->type === 'bnb_token';
    }

    public function isTomoErc20Token()
    {
        return $this->type === 'tomo_token';
    }

    public function isBtcToken()
    {
        return $this->type === 'btc_token';
    }

    public function isToken()
    {
        return $this->type !== 'coin';
    }

    public function getRequestPathCreateAddress()
    {
        if ($this->coin != $this->contractAddress && !empty($this->network_symbol)){
            return "/api/{$this->network_symbol}.{$this->contractAddress}/address";
        }
//        if ($this->isEthErc20Token() && $this->contractAddress) {
//            return "/api/erc20.{$this->contractAddress}/address";
//        }
        return "{$this->basePrefix}/address";
    }

    public function getRequestPathCreateMultiAddress()
    {
        return "{$this->basePrefix}/address";
    }

    public function getRequestPathWalletInfo()
    {
        return $this->basePrefix;
    }

    public function getRequestPathToSend()
    {
        return "{$this->basePrefix}/sendcoins";
    }

    public function getRequiredConfirmations()
    {
        return MasterdataService::getCoinConfirmation($this->coin);
    }

    public function getCoinRequestPathTransaction()
    {
        $currencyTx = $this->getCurrentTx();

        return ":{$this->port}/api/{$currencyTx}/tx";
    }

    public function getRequestPathToUpdateColdWallet()
    {
        return ":{$this->port}/api/{$this->getCurrentThreshold()}/setting_threshold";
    }

    public function getRequestPathToResetColdWallet()
    {
        return ":{$this->port}/api/reset_cold_wallet_setting/{$this->getCurrentThreshold()}";
    }

    public function getRequestPathToGetColdWallet()
    {
        return ":{$this->port}/api/setting_threshold";
    }

    public function getRequestPathToGetColdWalletByCoin($coin)
    {
        return ":{$this->port}/api/$coin/cold_wallet";
    }

    public function getRequestPathToUpdateMail()
    {
        return ":{$this->port}/api/setting_mailer";
    }

    public function getRequestPathToUpdateMailV2()
    {
        return ":{$this->port}/api/cold_wallet_mailer";
    }

    public function getRequestPathToGetWalletBalance()
    {
        return ":{$this->port}/api/all/statistical_hotwallet";
    }

    public function isTagAttached()
    {
        //return $this->coin === Consts::CURRENCY_XRP || $this->coin === Consts::CURRENCY_EOS || $this->coin === Consts::CURRENCY_TRX;
        return false;
    }

    public function getApiCreateAddress()
    {
        $domain = $this->getDomainAPI();

        return  $domain . $this->getRequestPathCreateAddress();
    }

    public function isTypeEth()
    {
        return $this->isEthErc20Token() || $this->getCoin() == Consts::CURRENCY_ETH;
    }

    public function isTypeBtc()
    {
        return $this->isBtcToken() || $this->getCoin() == Consts::CURRENCY_BTC;
    }

    public function getDomainAPI()
    {
        return config('blockchain.api_wallet') . ':' . $this->port;
    }

    public function getPortAPI()
    {
        $internalEndpoint = WalletCurrencyConfig::getApiCreateAddress($this->coin);
        $urls = explode(':', $internalEndpoint);
        return array_pop($urls);
    }

    public function getCurrentTx()
    {
        /*if ($this->isEthErc20Token() || $this->isBEP20Token()) {
            $contract_address = DB::table('coins')->where('coin', $this->coin)
                ->where('env', config('blockchain.network'))
                ->value('contract_address');

            if ($this->isEthErc20Token()) {
                $tokenType = Consts::ERC20_WEBHOOK;
            } else {
                $tokenType = Consts::BEP20_WEBHOOK;
            }

            return "{$tokenType}.{$contract_address}";
        }*/

        if ($this->coin != $this->contractAddress && !empty($this->network_symbol)){
            return "{$this->network_symbol}.{$this->contractAddress}";
        }

        //        if($this->coin === Consts::CURRENCY_USDT) {
        //            return $this->UsdtNetSwitch();
        //        }
        return $this->coin;
        //return $this->getCurrencyPlatform();
    }

    private function UsdtNetSwitch()
    {
        if (config('blockchain.network') === 'testnet') {
            return Consts::OMNI2;
        }

        return Consts::OMNI31;
    }

    public function getCurrentThreshold()
    {
        /*if ($this->isEthErc20Token() || $this->isBEP20Token()) {
            $contract_address = DB::table('coins')->where('coin', $this->coin)
                ->where('env', config('blockchain.network'))
                ->value('contract_address');

            if ($this->isEthErc20Token()) {
                $tokenType = Consts::ERC20_WEBHOOK;
            } else {
                $tokenType = Consts::BEP20_WEBHOOK;
            }

            return "{$tokenType}.{$contract_address}";
        }

        return $this->coin;*/
        if ($this->coin != $this->contractAddress && !empty($this->network_symbol)){
            return "{$this->network_symbol}.{$this->contractAddress}";
        }
        return $this->coin;
    }

    public function getCurrencyPlatform()
    {
        if ($this->isTypeEth()) {
            return Consts::CURRENCY_ETH;
        }

        if ($this->isTypeBtc()) {
            return Consts::CURRENCY_BTC;
        }

        return $this->coin;
    }

    public function getTokenType()
    {
        return $this->type;
    }
}
