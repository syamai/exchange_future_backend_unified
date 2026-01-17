import { Module, forwardRef } from "@nestjs/common";
import { AccountsModule } from "src/modules/account/account.module";
import { TradeController } from "src/modules/trade/trade.controller";
import { TradeService } from "src/modules/trade/trade.service";
import TradeSeedCommand from "./trade.console";
import { ExcelService } from "../export-excel/services/excel.service";
import { BinanceCoinmDataConsole } from "./binance-coinm-data.console";
import { InstrumentModule } from "../instrument/instrument.module";
import { BinanceTradeService } from "./binance/binance-trade.service";

@Module({
  imports: [forwardRef(() => AccountsModule), forwardRef(() => InstrumentModule)],
  controllers: [TradeController],
  providers: [TradeService, TradeSeedCommand, ExcelService, BinanceCoinmDataConsole, BinanceTradeService],
  exports: [TradeService, BinanceTradeService],
})
export class TradeModule {}
