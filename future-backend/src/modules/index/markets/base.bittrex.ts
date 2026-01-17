/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  BittrexResponse,
} from "src/modules/index/dto/index.dto";

export class Bittrex extends MarketCrawler {
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
    // https://api.bittrex.com/v3/markets/BTC-USD/ticker
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/v3/markets/${metadata.pairs[index].symbol}/ticker`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: BittrexResponse): MarketData | undefined {
    if (!resp?.lastTradeRate || !resp?.bidRate || !resp?.askRate) {
      console.warn(
        `Corrupt data in ${__filename}, please check the bittrex update`
      );
      return undefined;
    }

    return {
      market: "bittrex",
      bid: Number(resp.bidRate),
      ask: Number(resp.askRate),
      index: Number(resp.lastTradeRate),
    };
  }
}
