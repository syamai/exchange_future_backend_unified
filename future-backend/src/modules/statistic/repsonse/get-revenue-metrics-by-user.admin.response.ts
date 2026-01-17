export class GetRevenueMetricsByUserForAdminResponse {
  userId: number;
  totalValueVoucher: {
    used: string;
    total: string;
  };
  futureBalance: string;
  futureFee: string;
}
