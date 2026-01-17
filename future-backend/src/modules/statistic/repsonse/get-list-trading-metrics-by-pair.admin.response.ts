export class GetListTradingMetricsByPairAdminResponse {
  pair: string;
  position: {
    total: number;
    win: number;
    loss: number;
  };
  tradingVolume: {
    total: string;
  };
  fee: {
    total: string;
    avg: string;
  };
  profit: {
    total: string;
    avg: string;
  };
  loss: {
    total: string;
    avg: string;
  };
  totalPnl: string;
}
