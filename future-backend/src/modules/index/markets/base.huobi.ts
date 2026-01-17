/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  HuobiResponse,
  MetaMarketDTO,
} from "src/modules/index/dto/index.dto";

export class Huobi extends MarketCrawler {
  constructor() {
    super();
  }
  /**
   *
   *
   */
  createRequestString(metadata: MetaMarketDTO): Array<string> {
    // throw 'Candle for this trading platform is not yet developed ' + __filename;
    return [];
  }
  /**
   *
   **/
  transformResponse(resp: CandleResponseDTO): Array<CandleData> {
    // throw 'Candle for this trading platform is not yet developed ' + __filename;
    return [];
  }

  createRequestStringForMarket(metadata: MetaMarketDTO): Array<string> {
    // console.log(metadata);
    // Anything else in inputValue does not need value in it
    // https://api.huobi.pro/market/trade?symbol
    const requestStrings = [];

    // https://api.huobi.pro/market/detail/merged?symbol=btcusdt
    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      let symbol = metadata.pairs[index].symbol;
      if (symbol.startsWith(`1000PEPEUSD`)) symbol = `PEPEUSDT`; 
      else if (symbol.startsWith(`1000SHIBUSD`)) symbol = `SHIBUSDT`; 
      requestString += `/market/trade?symbol=${symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: HuobiResponse): MarketData | undefined {
    if (
      !Object.keys(resp).includes("status") ||
      resp.status != "ok" ||
      !Object.keys(resp).includes("tick")
    ) {
      console.log(resp);
      console.warn(
        `Corrupt resp in ${__filename}, please check the  huobi update`
      );
      return undefined;
    }

    const data = resp.tick.data;
    if (data.length == 0) {
      console.warn(
        `Corrupt data in ${__filename}, please check the  huobi update`
      );
      return undefined;
    }
    return {
      market: "huobi",
      bid: data[0].price,
      ask: data[0].price,
      index: data[0].price,
    };
  }
}
