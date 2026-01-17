export class GetPagListTradingDataByUserAdminResponse {
  userInfo: {
    email: string;
    uid: string;
    id: string;
  };
  totalDeposit: string;
  totalWithdrawal: string;
  balance: string;
  rewardBalance: {
    total: string;
    current: string;
  };
  tradingVolume: string;
  profit: {
    total: string;
    avg: string;
  };
  loss: {
    total: string;
    avg: string;
  };
  pnl: {
    total: string;
    avg: string;
  };
  tradingFee: string;
  fundingFee: string;
  pnlAfterFee: string;
  currentPosition: string;
  pendingPosition: string;
  tradingPairs: string[];
}
