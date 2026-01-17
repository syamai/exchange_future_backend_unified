export const PREFIX_CACHE = "candle";
export const PREFIX_BINANCE_CACHE = "binance-candle";
export const CANDLE_TTL = 864000;
export const BINANCE_CANDLE_TTL = 5 * 864000; // 5 days
export const RESOLUTION_MINUTE = 60;
export const RESOLUTION_15MINUTES = 900;
export const RESOLUTION_HOUR = 3600;
export const KEY_CACHE_HEALTHCHECK_SYNC_CANDLE = "healthcheck_sync_candle";

export interface Candle {
  open: number;
  close: number;
  low: number;
  high: number;
  volume: number;
  time: number;
}
