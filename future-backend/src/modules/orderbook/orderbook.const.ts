export const ORDERBOOK_TTL = Number.MAX_SAFE_INTEGER; // unlimited
export const ORDERBOOK_PREVIOUS_TTL = 3600;
export interface Orderbook {
  bids: string[][];
  asks: string[][];
  lastUpdatedAt?: number;
}

export interface OrderbookResponse {
  bidPercent: number;
  askPercent: number;
  orderbook: Orderbook;
}

export interface OrderbookMEBinance {
  bids: string[][]; // [price, size, meSize, binanceSize]
  asks: string[][]; // [price, size, meSize, binanceSize]
}

export interface OrderbookEvent {
  symbol: string;
  orderbook: Orderbook;
  changes: Orderbook;
}
