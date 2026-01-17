/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  KarenResponse,
  KarenData,
} from "src/modules/index/dto/index.dto";

export class Kraken extends MarketCrawler {
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
    // https://api.kraken.com/0/public/Ticker?pair=BTCUSD
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/0/public/Ticker?pair=${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: KarenResponse): MarketData | undefined {
    if (!resp.result) {
      console.warn(
        `Corrupt data in ${__filename}, please check the kraken update`
      );
      return undefined;
    }

    const symbols = Object.keys(resp.result);

    if (symbols.length === 0) {
      console.warn(
        `Corrupt data in ${__filename}, please check the kraken update`
      );
      return undefined;
    }

    const krakenData: KarenData = resp.result[symbols[0]];

    return {
      market: "kraken",
      bid: Number(krakenData.b[0]),
      ask: Number(krakenData.a[0]),
      index: Number(krakenData.c[0]),
    };
  }
}
