/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  HitbtcResponse,
  HitbtcData,
} from "src/modules/index/dto/index.dto";

export class Hitbtc extends MarketCrawler {
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
    // https://api.hitbtc.com/api/3/public/ticker?symbols=BTCUSDT
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/api/3/public/ticker?symbols=${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: HitbtcResponse): MarketData | undefined {
    const symbols = Object.keys(resp);
    if (symbols.length === 0) {
      console.warn(
        `Corrupt data in ${__filename}, please check the bitbtc update`
      );
      return undefined;
    }

    const hitbtcData: HitbtcData = resp[symbols[0]];

    return {
      market: "hitbtc",
      bid: Number(hitbtcData.bid),
      ask: Number(hitbtcData.ask),
      index: Number(hitbtcData.last),
    };
  }
}
