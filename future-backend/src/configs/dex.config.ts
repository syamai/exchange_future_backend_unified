import * as config from "config";
import { Contract, providers, Wallet } from "ethers";
import * as dexAbi from "src/abis/SotaDex.json";

// const rpcHost = config.get<string>("dex.rpc_host");
const rpcHost =
  "https://special-quiet-snow.ethereum-holesky.quiknode.pro/0a93c1d6d9ae52537cc72caf9af282b8e7b3446d/";
// const matcherPrivateKey = config.get<string>("dex.matcher_private_key");
const matcherPrivateKey =
  "0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef";

// const withdrawerPrivateKey = config.get<string>("dex.withdrawer_private_key");
const withdrawerPrivateKey =
  "0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdee";
// const dexAddress = config.get<string>("dex.address");
const dexAddress =
  "0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef";
const collateralDecimal =
  Number(config.get<string>("dex.collateral_decimal")) ?? 123;
const defaultScale = Number(config.get<string>("dex.default_scale")) ?? 123;
const blockTimeInMs = Number(config.get<number>("dex.block_time_in_ms")) ?? 123;
const actionBatchSize =
  Number(config.get<number>("dex.action_batch_size")) ?? 123;
const chainId = Number(config.get<number>("dex.chain_id")) ?? 123;
const runningChain = config.get<string>("dex.running_chain") ?? "";
const provider = new providers.JsonRpcProvider(rpcHost);
const matcherWallet = new Wallet(matcherPrivateKey, provider);
const withdrawerWallet = new Wallet(withdrawerPrivateKey, provider);
const dexContract = (1 as unknown) as Contract;
console.log(dexContract);

export const Dex = {
  provider,
  dexContract,
  matcherWallet,
  withdrawerWallet,
  collateralDecimal,
  defaultScale,
  blockTimeInMs,
  chainId,
  actionBatchSize,
  runningChain,
};
