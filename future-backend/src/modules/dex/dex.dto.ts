export class DexTradeDTO {
  rawParameter?: any;
  dexParameter?: any;
  kafkaOffset?: string;
  matcherAddress?: string;
  nonce?: string;
  txid?: string;
  rawTx?: string;
  sendStatus?: string;
  receiptStatus?: string;
}

export class DexWithdrawalDTO {
  fromWithdrawalId?: string;
  toWithdrawalId?: string;
  kafkaOffset?: string;
  withdrawerAddress?: string;
  nonce?: string;
  txid?: string;
  rawTx?: string;
  sendStatus?: string;
  receiptStatus?: string;
}
