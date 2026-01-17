export class GetTradingMetricsAdminResponse {
  totalPosition: number;
  totalWins: string;
  totalLosses: string;
  currentPosition: number;

  totalProfit: string;
  totalPnlWin: string;
  totalLoss: string;
  totalPnlLoss: string;

  totalVolume: string;
  totalSize: string;
  averageSize: string;

  totalTradingFee: string;
  totalFundingFee: string;
  averageFee: string;
  totalCommission: string;

  totalMmProfit: string;
  mmPnl: string;
  totalMmFee: string;
  totalRewardVoucher: string;

  availableForWithdrawal: string;
}
