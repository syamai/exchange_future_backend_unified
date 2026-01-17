/* eslint-disable @typescript-eslint/no-unused-vars */
import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
  GateIOResponse,
} from "src/modules/index/dto/index.dto";

export class GateIO extends MarketCrawler {
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
    // https://data.gateapi.io/api2/1/ticker/btc_usdt
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/api2/1/ticker/${metadata.pairs[index].symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: GateIOResponse): MarketData | undefined {
    if (!resp.highestBid || !resp.lowestAsk || !resp.last) {
      console.warn(
        `Corrupt data in ${__filename}, please check the gateio update`
      );
      return undefined;
    }

    return {
      market: "gateio",
      bid: Number(resp.highestBid),
      ask: Number(resp.lowestAsk),
      index: Number(resp.last),
    };
  }
}
