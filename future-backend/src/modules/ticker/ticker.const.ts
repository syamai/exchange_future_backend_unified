export interface Ticker {
  symbol: string;
  priceChange: string;
  priceChangePercent: string;
  lastPrice: string;
  lastPriceChange: string;
  highPrice: string;
  lowPrice: string;
  volume: string;
  quoteVolume: string;
  indexPrice: string;
  oraclePrice: string;
  fundingRate: string;
  nextFunding: number;
  contractType?: string;
  trades?: any[];
  updatedAt?: number;
  lastUpdateAt?: number;
}

export interface TickerLastPrice {
  symbol: string;
  lastPrice: string;
  priceChange: string;
  priceChangePercent: string;
}

export interface Binance24hrTicker {
  symbol: string;
  priceChange: string;
  priceChangePercent: string;
  weightedAvgPrice: string;
  prevClosePrice: string;
  lastPrice: string;
  lastQty: string;
  bidPrice: string;
  askPrice: string;
  openPrice: string;
  highPrice: string;
  lowPrice: string;
  volume: string;
  quoteVolume: string;
  openTime: number;
  closeTime: number;
  firstId: number;
  lastId: number;
  count: number;
}

export const TICKER_TTL = 86400000;

export const TICKER_LAST_PRICE_TTL = 43200; // 12 hours

export const TICKERS_KEY = "tickers";

export const TICKERS_LAST_PRICE_KEY = "ticker_last_price";
