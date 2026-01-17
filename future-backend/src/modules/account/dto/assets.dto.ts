export class AssetTokenDto {
  asset: string;
  balance: string;
  estimateBTC: string;
  estimateUSD: string;
}

export class AssetsDto {
  assets: AssetTokenDto[];
  totalWalletBalance: string;
}
