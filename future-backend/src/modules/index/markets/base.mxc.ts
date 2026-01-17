/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  MXCResponse,
} from "src/modules/index/dto/index.dto";

export class MXC extends MarketCrawler {
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
    // https://contract.mexc.com/api/v1/contract/ticker?symbol=BTC_USDT
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/api/v1/contract/ticker?symbol=${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: MXCResponse): MarketData | undefined {
    if (!resp.success) {
      console.warn(
        `Corrupt data in ${__filename}, please check the mxc update`
      );
      return undefined;
    }

    const mxcData = resp.data;

    return {
      market: "mxc",
      bid: Number(mxcData.bid1),
      ask: Number(mxcData.ask1),
      index: Number(mxcData.lastPrice),
    };
  }
}
