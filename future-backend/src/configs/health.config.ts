import * as config from "config";

const rpcHost = config.get<string>("health.rpc_host");
const namespace = config.get<string>("health.namespace");
const insuranceAccountId = config.get<string>("insurance.account_id");

export const Health = {
  rpcHost,
  namespace,
  insuranceAccountId,
};
