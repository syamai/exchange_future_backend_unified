import { MarginMode } from "src/shares/enums/order.enum";

export class GetPagListPositionHistoryBySessionAdminResponse {
  id: number;
  traderEmail: string;
  traderId: number;
  positionId: number;
  openTime: string;
  closeTime: string;
  symbol: string;
  marginMode: MarginMode;
  leverage: string;
  side: string;
  avgEntryPrice: string;
  avgClosePrice: string;
  margin: string;
  size: string;
  value: string;
  realizedPnl: string;
  fee: string;
  realizedPnlPercent: string;
  status: string;
  checkingStatus: string;

  // Tooltip breakdowns
  realizedPnlDetail?: {
    closingProfits: string;
    fundingFee: string;
    openingFee: string;
    closingFee: string;
  };
  feeDetail?: {
    openingFee: string;
    closingFee: string;
  };
}
