import { Program, BN } from "@project-serum/anchor";
import {
  PublicKey,
  SystemProgram,
  Transaction,
  TransactionInstruction,
} from "@solana/web3.js";
import {
  ASSOCIATED_TOKEN_PROGRAM_ID,
  Token,
  TOKEN_PROGRAM_ID,
} from "@solana/spl-token";
import { SotadexSolana } from "src/idl/sotadex_solana";
import { DexLiquidationSide } from "src/modules/dex/dex.constant";

interface FundingParams {
  id: number;
  user: string;
  amount: string;
  operationId: number;
}

interface WithdrawParams {
  id: number;
  operationId: number;
  user: string;
  amount: string;
  fee: string;
}

interface TradeParams {
  id: number;
  operationId: number;
  instrumentId: string;
  buyer: string;
  price: string;
  seller: string;
  buyerFee: string;
  quantity: string;
  sellerFee: string;
  bankruptFee: string;
  bankruptPrice: string;
  liquidationSide: DexLiquidationSide;
}

export const SOTADEX_SEED = Buffer.from("sotadex");
export const SOTADEX_MINT_SEED = Buffer.from("sotadex-mint");
export const SOTADEX_MEMBER_SEED = Buffer.from("sotadex-member");
export const SOTADEX_POSITION_SEED = Buffer.from("sotadex-position");
export const LOG_START = "sotadex-log";

export class SotaDexWrapper {
  private existsMap = new Map<string, boolean>();
  private feeCollector: PublicKey;
  private insurance: PublicKey;

  constructor(
    public readonly program: Program<SotadexSolana>,
    public readonly dexId: BN,
    public readonly usdcId: PublicKey
  ) {}

  async getSotadexAccount(): Promise<[PublicKey, number]> {
    return PublicKey.findProgramAddress(
      [SOTADEX_SEED, this.dexId.toArrayLike(Buffer, "le", 8)],
      this.program.programId
    );
  }

  async getSotadexTokenAccount(): Promise<[PublicKey, number]> {
    return PublicKey.findProgramAddress(
      [SOTADEX_MINT_SEED, this.dexId.toArrayLike(Buffer, "le", 8)],
      this.program.programId
    );
  }

  async getMemberAccount(address: PublicKey): Promise<[PublicKey, number]> {
    return PublicKey.findProgramAddress(
      [
        SOTADEX_MEMBER_SEED,
        this.dexId.toArrayLike(Buffer, "le", 8),
        address.toBuffer(),
      ],
      this.program.programId
    );
  }

  async getUsdcTokenAccount(address: PublicKey) {
    return Token.getAssociatedTokenAddress(
      ASSOCIATED_TOKEN_PROGRAM_ID,
      TOKEN_PROGRAM_ID,
      this.usdcId,
      address
    );
  }

  async getMemberPositionAccount(
    address: PublicKey,
    instructionId: BN
  ): Promise<[PublicKey, number]> {
    return PublicKey.findProgramAddress(
      [
        SOTADEX_POSITION_SEED,
        this.dexId.toArrayLike(Buffer, "le", 8),
        address.toBuffer(),
        instructionId.toArrayLike(Buffer, "le", 2),
      ],
      this.program.programId
    );
  }

  async isAccountExist(address: PublicKey): Promise<boolean> {
    if (this.existsMap.get(address.toBase58())) {
      return true;
    }

    const exist = await this.program.provider.connection.getAccountInfo(
      address
    );
    if (exist) {
      this.existsMap.set(address.toBase58(), true);
    }
    return !!exist;
  }

  async getWithdrawInstruction(dexParameter: WithdrawParams) {
    await this.fetchOnchainInfo();
    const memberAddress = new PublicKey(dexParameter.user);

    const [sotadexAccount] = await this.getSotadexAccount();
    const [sotadexTokenAccount] = await this.getSotadexTokenAccount();
    const [sotadexMemberAccount] = await this.getMemberAccount(memberAddress);
    const [feeCollectorMemberAccount] = await this.getMemberAccount(
      this.feeCollector
    );
    const senderTokenAccount = await this.getUsdcTokenAccount(memberAddress);

    if (!this.isAccountExist(sotadexMemberAccount)) {
      throw new Error(
        `Member ${sotadexMemberAccount.toBase58()} account not exist, address=${memberAddress.toBase58()}`
      );
    }
    if (!this.isAccountExist(senderTokenAccount)) {
      throw new Error(
        `Token Account ${senderTokenAccount.toBase58()} account not exist, address=${memberAddress.toBase58()}`
      );
    }
    const withdrawArg = {
      id: new BN(dexParameter.id),
      operationId: new BN(dexParameter.operationId),
      user: memberAddress,
      amount: new BN(dexParameter.amount),
      fee: new BN(dexParameter.fee),
    };

    return this.program.instruction.withdraw(withdrawArg, {
      accounts: {
        sender: this.program.provider.wallet.publicKey,
        userTokenAccount: senderTokenAccount,
        sotadexAccount,
        sotadexTokenAccount,
        sotadexMemberAccount,
        feeCollectorMemberAccount,
        systemProgram: SystemProgram.programId,
        tokenProgram: TOKEN_PROGRAM_ID,
      },
    });
  }

  async getFundingInstruction(dexParameter: FundingParams) {
    const memberAddress = new PublicKey(dexParameter.user);

    const [sotadexAccount] = await this.getSotadexAccount();
    const [sotadexMemberAccount] = await this.getMemberAccount(memberAddress);

    if (!this.isAccountExist(sotadexMemberAccount)) {
      throw new Error(
        `Member ${sotadexMemberAccount.toBase58()} account not exist, address=${memberAddress.toBase58()}`
      );
    }
    const fundingArg = {
      id: new BN(dexParameter.id),
      operationId: new BN(dexParameter.operationId),
      user: memberAddress,
      amount: new BN(dexParameter.amount),
    };

    return this.program.instruction.funding(fundingArg, {
      accounts: {
        sender: this.program.provider.wallet.publicKey,
        sotadexAccount,
        sotadexMemberAccount,
        systemProgram: SystemProgram.programId,
      },
    });
  }

  async initPosition(instrumentId: BN, address: PublicKey) {
    const [sotadexAccount] = await this.getSotadexAccount();
    const [
      positionAccount,
      positionAccountBump,
    ] = await this.getMemberPositionAccount(address, instrumentId);

    return this.program.rpc.initPosition(
      instrumentId.toNumber(),
      positionAccountBump,
      {
        accounts: {
          sender: this.program.provider.wallet.publicKey,
          memberPubkey: address,
          sotadexAccount,
          positionAccount: positionAccount,
          systemProgram: SystemProgram.programId,
        },
      }
    );
  }

  async getTradeInstruction(dexParameter: TradeParams) {
    await this.fetchOnchainInfo();
    const buyerAddress = new PublicKey(dexParameter.buyer);
    const sellerAddress = new PublicKey(dexParameter.seller);
    const instrumentId = new BN(dexParameter.instrumentId);

    const [sotadexAccount] = await this.getSotadexAccount();
    const [buyerMemberAccount] = await this.getMemberAccount(buyerAddress);
    const [buyerPositionAccount] = await this.getMemberPositionAccount(
      buyerAddress,
      instrumentId
    );
    const [sellerMemberAccount] = await this.getMemberAccount(sellerAddress);
    const [sellerPositionAccount] = await this.getMemberPositionAccount(
      sellerAddress,
      instrumentId
    );
    const [feeCollectorMemberAccount] = await this.getMemberAccount(
      this.feeCollector
    );
    const [insuranceMemberAccount] = await this.getMemberAccount(
      this.insurance
    );

    if (!(await this.isAccountExist(buyerPositionAccount))) {
      throw new Error(
        `buyerPositionAccount ${buyerPositionAccount.toBase58()} is not exist, instrumentId=${instrumentId.toNumber()}, address=${buyerAddress.toBase58()}`
      );
      // console.log(`buyerPositionAccount ${buyerPositionAccount.toBase58()} account not exist`);
      // await this.initPosition(instrumentId, buyerAddress);
    }
    if (!(await this.isAccountExist(sellerPositionAccount))) {
      throw new Error(
        `sellerPositionAccount ${sellerPositionAccount.toBase58()} is not exist, instrumentId=${instrumentId.toNumber()}, address=${sellerAddress.toBase58()}`
      );
      // console.log(`sellerPositionAccount ${sellerPositionAccount.toBase58()} account not exist`);
      // await this.initPosition(instrumentId, sellerAddress);
    }
    const tradeArg = {
      id: new BN(dexParameter.id),
      operationId: new BN(dexParameter.operationId),
      buyer: buyerAddress,
      seller: sellerAddress,
      quantity: new BN(dexParameter.quantity),
      price: new BN(dexParameter.price),
      bankruptPrice: new BN(dexParameter.bankruptPrice),
      bankruptFee: new BN(dexParameter.bankruptFee),
      liquidationSide: dexParameter.liquidationSide,
      buyerFee: new BN(dexParameter.buyerFee),
      sellerFee: new BN(dexParameter.sellerFee),
      instrumentId: Number(dexParameter.instrumentId),
    };

    return this.program.instruction.trade(tradeArg, {
      accounts: {
        sender: this.program.provider.wallet.publicKey,
        sotadexAccount,
        buyerMemberAccount,
        buyerPositionAccount,
        sellerMemberAccount,
        sellerPositionAccount,
        feeCollectorMemberAccount,
        insuranceMemberAccount,
        systemProgram: SystemProgram.programId,
      },
    });
  }

  async newTransaction(instructions: TransactionInstruction[]) {
    const transaction = new Transaction();
    transaction.add(...instructions);
    transaction.feePayer = this.program.provider.wallet.publicKey;
    return transaction;
  }

  async fetchOnchainInfo() {
    if (this.feeCollector) {
      return;
    }

    const [sotadexAccount] = await this.getSotadexAccount();
    const sotadexInfo = await this.program.account.sotadexAccount.fetch(
      sotadexAccount
    );
    this.feeCollector = sotadexInfo.feeCollector;
    this.insurance = sotadexInfo.insurance;
  }

  extractEvents(logMessages: string[]) {
    const groupLogMessages = this._groupLogMessages(logMessages);
    const needLogs = groupLogMessages[this.program.programId.toBase58()] || [];
    const serializedLogMessages = [];
    const events = [];

    for (let i = 0; i < needLogs.length; i++) {
      const logMessage = needLogs[i];
      const jsonStartStr = `Program log: ${LOG_START}`;
      if (logMessage.startsWith(jsonStartStr)) {
        const serializedLog = needLogs[i + 1].slice("Program log: ".length);
        serializedLogMessages.push(serializedLog);
      }
    }

    for (let i = 0; i < serializedLogMessages.length; i++) {
      const log = serializedLogMessages[i];
      const decodedLog = this.program.coder.events.decode(log);

      if (!decodedLog) {
        continue;
      }
      events.push(decodedLog);
    }

    return events;
  }

  private _groupLogMessages(
    logMessages: string[]
  ): { [key: string]: string[] } {
    if (!logMessages.length) {
      return {};
    }

    const logMapping: { [key: string]: string[] } = {};
    const programStack: string[] = [];
    for (const logMessage of logMessages) {
      const [_program, _programId, _method] = logMessage.split(" ");

      if (["Deployed", "Upgraded"].includes(_program)) {
        continue;
      }

      if (_method === "invoke") {
        if (!logMapping[_programId]) {
          logMapping[_programId] = [];
        }
        logMapping[_programId].push(logMessage);
        programStack.push(_programId);
        continue;
      }

      if (_method === "consumed") {
        logMapping[_programId].push(logMessage);
        continue;
      }

      if (_method === "success") {
        logMapping[_programId].push(logMessage);
        programStack.pop();
        continue;
      }

      const lastProgramId = programStack[programStack.length - 1];
      logMapping[lastProgramId].push(logMessage);
    }
    return logMapping;
  }
}
