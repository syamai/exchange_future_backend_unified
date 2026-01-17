import * as config from "config";
import { PublicKey, Connection, Keypair } from "@solana/web3.js";
import * as anchor from "@project-serum/anchor";
import NodeWallet from "@project-serum/anchor/dist/cjs/nodewallet";
import { bs58 } from "@project-serum/anchor/dist/cjs/utils/bytes";
import { Program } from "@project-serum/anchor";
import { IDL as SOTADEX_IDL } from "src/idl/sotadex_solana";

const rpcHost = config.get<string>("sol_dex.rpc_host");
const matcherPrivateKey = config.get<string>("sol_dex.matcher_private_key");
const dexProgramId = new PublicKey(config.get<string>("sol_dex.program_id"));
// const dexId = (1 as unknown) as anchor.BN; // new anchor.BN(config.get<string>('sol_dex.id'));
const dexId = new anchor.BN(1);
const collateralDecimal = Number(
  config.get<string>("sol_dex.collateral_decimal")
);
const defaultScale = Number(config.get<string>("sol_dex.default_scale"));
const blockTimeInMs = Number(config.get<number>("sol_dex.block_time_in_ms"));
const actionBatchSize = Number(config.get<number>("sol_dex.action_batch_size"));
const usdcId = new PublicKey(config.get<number>("sol_dex.usdc_id"));

const matcherKeypair = Keypair.fromSecretKey(bs58.decode(matcherPrivateKey));
const processedConnection = new Connection(rpcHost, "processed");
const connection = new Connection(rpcHost, "confirmed"); // if you want to fetch account data, confirmed is needed
const finalizedConnection = new Connection(rpcHost, "finalized");
const matcherWallet = new NodeWallet(matcherKeypair);
const provider = new anchor.Provider(connection, matcherWallet, {
  commitment: "confirmed",
  preflightCommitment: "confirmed",
});
const dexProgram = new Program(SOTADEX_IDL, dexProgramId, provider);

export const SolDex = {
  processedConnection,
  connection,
  dexId,
  provider,
  dexProgram,
  matcherKeypair,
  matcherWallet,
  collateralDecimal,
  defaultScale,
  blockTimeInMs,
  actionBatchSize,
  usdcId,
  dexContract: undefined,
  finalizedConnection,
};
