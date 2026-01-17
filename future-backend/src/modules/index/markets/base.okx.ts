/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  OKXTickerResponse,
} from "src/modules/index/dto/index.dto";

export class OKX extends MarketCrawler {
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
    // https://www.okx.com/api/v5/market/ticker?instId=BTC-USD-221230
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      let symbol = metadata.pairs[index].symbol;
      if (symbol.startsWith(`1000PEPEUSD`)) symbol = `PEPEUSDT`; 
      else if (symbol.startsWith(`1000SHIBUSD`)) symbol = `SHIBUSDT`; 
      requestString += `/api/v5/market/ticker?instId=${symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: OKXTickerResponse): MarketData | undefined {
    if (resp.data.length === 0) {
      console.warn(
        `Corrupt data in ${__filename}, please check the okx update`
      );
      return undefined;
    }

    const okxData = resp.data[0];

    return {
      market: "okx",
      bid: Number(okxData.bidPx),
      ask: Number(okxData.askPx),
      index: Number(okxData.last),
    };
  }
}
