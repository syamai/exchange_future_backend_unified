/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  BitmaxResponse,
} from "src/modules/index/dto/index.dto";

export class Bitmax extends MarketCrawler {
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
    // https://bitmax.io/api/pro/v1/ticker?symbol=BTC/USDT
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/api/pro/v1/ticker?symbol=${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: BitmaxResponse): MarketData | undefined {
    const bitmaxData = resp?.data;

    if (!bitmaxData) {
      console.warn(
        `Corrupt data in ${__filename}, please check the bitmax update`
      );
      return undefined;
    }

    return {
      market: "bitmax",
      bid: Number(bitmaxData.bid[0]),
      ask: Number(bitmaxData.ask[0]),
      index: Number(bitmaxData.close),
    };
  }
}
