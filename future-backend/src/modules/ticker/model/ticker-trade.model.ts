export class TickerTrade {
  symbol: string;
  price: string;
  quantity: string;
  createdAt: any;

  buyerIsTaker: boolean;

  buyFee: string;
  sellFee: string;
  buyFeeRate: string;
  sellFeeRate: string;

  id: number;
  buyOrderId: number;
  sellOrderId: number;
}
