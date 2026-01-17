import { RESOLUTION_MINUTE } from "src/modules/candle/candle.const";

export class CachedSymbols {}

export class CandleData {
  symbol: string;
  minute: number;
  resolution: number;
  low: string;
  high: string;
  open: string;
  close: string;
  volume: string;
  lastTradeTime: number;
}

export const EMPTY_CANDLE = {
  symbol: "",
  minute: 0,
  resolution: RESOLUTION_MINUTE,
  low: "0",
  high: "0",
  open: "0",
  close: "0",
  volume: "0",
  lastTradeTime: 0,
};

export interface TradeData {
  price: string;
  volume: string;
  updatedAt: number;
  symbol: string;
}
