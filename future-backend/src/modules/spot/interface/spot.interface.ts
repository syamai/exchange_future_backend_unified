import {
  AssetType,
  SpotTransactionType,
} from "src/shares/enums/transaction.enum";

export interface ReferralOrReward {
  userId: number;
  amount: string;
  asset: AssetType;
  type: SpotTransactionType;
}

export interface SyncUser {
  id: number;
  email: string;
  role: string;
  status: string;
  uid: string;
  isBot: boolean;
}

export interface SynNotificationToken {
  userId: number;
  deviceToken: string;
}

export interface SyncAntiPhishingCode {
  id: number;
  antiPhishingCode: string;
}

export interface SyncLocaleUser {
  id: number;
  location: string;
}
