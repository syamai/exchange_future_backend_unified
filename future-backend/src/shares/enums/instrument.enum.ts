import { enumize } from "src/shares/enums/enumize";

export const InstrumentTypes = enumize("COIN_M", "USD_M");

export const InstrumentDeleverageable = enumize(
  "UNDELEVERAGEABLED",
  "DELEVERAGEABLED"
);

export const InstrumentHasLiquidity = enumize(
  "HAS_LIQUIDITY",
  "HAS_NOT_LIQUIDITY"
);

export enum INSTRUMENT {
  MULTIPLIER_DEFAULT_VALUE = "1",
}
