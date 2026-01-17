/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  BitstampResponse,
} from "src/modules/index/dto/index.dto";

export class Bitstamp extends MarketCrawler {
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
    // https://www.bitstamp.net/api/v2/ticker/btcusd
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/api/v2/ticker/${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: BitstampResponse): MarketData | undefined {
    if (!resp.ask || !resp.last || !resp.bid) {
      console.warn(
        `Corrupt data in ${__filename}, please check the bitstamp update`
      );
      return undefined;
    }

    return {
      market: "bitstamp",
      bid: Number(resp.ask),
      ask: Number(resp.bid),
      index: Number(resp.last),
    };
  }
}
