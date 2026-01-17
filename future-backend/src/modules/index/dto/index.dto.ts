import { IsNotEmpty } from "class-validator";
import { MarketCrawler } from "src/modules/index/markets/base";

export class FtxCandleDTO {
  low: number;
  high: number;
  open: number;
  close: number;
  volume: number;
}

export class Pair {
  @IsNotEmpty()
  symbol: string;
  @IsNotEmpty()
  group: string;
}

export class GeminiVolume {}
export class GeminiData {
  ask: string;
  bid: string;
  last: string;
  volume: GeminiVolume;
}

export class MetaMarketDTO {
  @IsNotEmpty()
  baseUrl: string;
  @IsNotEmpty()
  pairs: Array<Pair>;
  @IsNotEmpty()
  // eslint-disable-next-line
  base: MarketCrawler;
  resolution?: number | undefined;
  granularity?: number | undefined;
  interval?: number | undefined;
  limit?: number | undefined;
}

export class CandleResponseDTO {
  // eslint-disable-next-line
  success?: any;
  // eslint-disable-next-line
  result?: any;
}

export class CandleData {
  group?: string;
  symbol?: string;
  @IsNotEmpty()
  market: string;
  @IsNotEmpty()
  timestamp: number;
  @IsNotEmpty()
  low: number;
  @IsNotEmpty()
  high: number;
  @IsNotEmpty()
  open: number;
  @IsNotEmpty()
  close: number;
  @IsNotEmpty()
  volume: number;
}

export class MarketData {
  @IsNotEmpty()
  market: string;
  @IsNotEmpty()
  bid: number;
  @IsNotEmpty()
  ask: number;
  @IsNotEmpty()
  index: number;
  symbol?: string;
  group?: string;
}

export class MetadataCandleDTO {}
export class MetadataWeightGroupDTO {}
export class MetadataMarketDTO {}

// Reponse market data for base

export class BaseMarketResponseDTO {}

// Binance
export class BinanceResponse extends BaseMarketResponseDTO {
  bidPrice: number;
  askPrice: number;
  lastPrice: number;
}

// Coinbase
export class CoinbaseResponse extends BaseMarketResponseDTO {
  bid: number;
  ask: number;
  price: number;
}

// Gemini
export class GeminiResponse extends BaseMarketResponseDTO {
  last: number;
}

// Huobi
export class HuobiData {
  price?: number;
}

export class HuobiTick {
  ids: number;
  ts: number;
  data: HuobiData[];
}

export class HuobiResponse extends BaseMarketResponseDTO {
  status: string;
  tick: HuobiTick;
}

// OKX
export class OKXTicker {
  last: string;
  askPx: string;
  bidPx: string;
}

export class OKXTickerResponse extends BaseMarketResponseDTO {
  data: OKXTicker[];
}

// Bittrex
export class BittrexResponse extends BaseMarketResponseDTO {
  lastTradeRate: string;
  bidRate: string;
  askRate: string;
}

// Hitbtc
export class HitbtcData {
  ask: string;
  bid: string;
  last: string;
}
export class HitbtcResponse extends BaseMarketResponseDTO {
  [key: string]: HitbtcData;
}

// GateIO
export class GateIOResponse extends BaseMarketResponseDTO {
  highestBid: string;
  lowestAsk: string;
  last: string;
}

// Bitmax
export class BitmaxResponse extends BaseMarketResponseDTO {
  data: {
    // last traded price
    close: string;
    // the price and size at the current best ask level
    ask: string[];
    // the price and size at the current best bid level
    bid: string[];
  };
}

// MXC
export class MXCResponse extends BaseMarketResponseDTO {
  success: boolean;
  data: {
    lastPrice: number;
    bid1: number;
    ask1: number;
  };
}

// Bitfinex
export class BitfinexResponse extends BaseMarketResponseDTO {
  bid: string;
  ask: string;
  last_price: string;
}

// Bitstamp
export class BitstampResponse extends BaseMarketResponseDTO {
  bid: string;
  ask: string;
  last: string;
}

// Karen

export class KarenData {
  // Ask [<price>, <whole lot volume>, <lot volume>]
  a: string[];
  // Bid [<price>, <whole lot volume>, <lot volume>]
  b: string[];
  // Last trade closed [<price>, <lot volume>]
  c: string[];
}
export class KarenResponse extends BaseMarketResponseDTO {
  result: {
    [key: string]: KarenData;
  };
}
