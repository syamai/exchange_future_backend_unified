/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  GeminiResponse,
  MetaMarketDTO,
  GeminiData,
} from "src/modules/index/dto/index.dto";

export class Gemini extends MarketCrawler {
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
    // https://api.gemini.com/v1/pubticker/btcusd
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/v1/pubticker/${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: GeminiResponse): MarketData | undefined {
    if (resp.last == undefined) {
      console.log(resp);
      console.warn(
        `Corrupt data in ${__filename}, please check the gemini update`
      );
      return undefined;
    }

    return {
      market: "gemini",
      bid: Number(resp.last),
      ask: Number(resp.last),
      index: Number(resp.last),
    };
  }
}
