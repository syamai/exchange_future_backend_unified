import { PositionEntity } from "src/models/entities/position.entity";
import BigNumber from "bignumber.js";

export function calcUnrealizedPNL(position: PositionEntity, oraclePrice: string) {
  const positionSize = position.currentQty;
}
