import { MarketCrawler } from "src/modules/index/markets/base";
import {
  BinanceResponse,
  CandleData,
  CandleResponseDTO,
  MarketData,
  MetaMarketDTO,
} from "src/modules/index/dto/index.dto";

export class Binance extends MarketCrawler {
  constructor() {
    super();
  }
  /**
   * This function should create request string base on input meta data
   * Example format:
   * curl --request GET \
   *      --url 'https://api1.binance.com/api/v3/klines?symbol=ETHUSDT&interval=1m&limit=100' \
   *      --header 'Accept: application/json'
   *
   *
   */
  createRequestString(metadata: MetaMarketDTO): Array<string> {
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();

      requestString += `/api/v3/klines?symbol=${
        metadata.pairs[Number(index)].symbol
      }`;
      if (metadata.interval) {
        requestString += `&interval=${metadata.interval}`;
      } else {
        requestString += "&interval=1m";
      }
      if (metadata.limit) {
        requestString += `&limit=${metadata.limit}`;
      } else {
        requestString += "&limit=100";
      }
      requestStrings.push(requestString);
    }
    return requestStrings;
  }
  /**
   * Process transfrom Response from each market data form to the same format
   *
   */
  /*
  Example data
  [
    [
      1499040000000,      // Open time
      "0.01634790",       // Open
      "0.80000000",       // High
      "0.01575800",       // Low
      "0.01577100",       // Close
      "148976.11427815",  // Volume
      1499644799999,      // Close time
      "2434.19055334",    // Quote asset volume
      308,                // Number of trades
      "1756.87402397",    // Taker buy base asset volume
      "28.46694368",      // Taker buy quote asset volume
      "17928899.62484339" // Ignore.
    ]
  ]
  **/
  transformResponse(resp: CandleResponseDTO): Array<CandleData> {
    const respData = resp;
    const output = [];
    console.log(respData);
    for (const index in respData) {
      const data = respData[index];
      if (data.length <= 6) {
        console.warn(
          `Corrupt data in ${__filename}, please check the  binance update`
        );
        continue;
      }
      output.push({
        market: "binance",
        timestamp: data[0],
        open: data[1],
        high: data[2],
        low: data[3],
        close: data[4],
        volume: data[5],
      });
    }
    return output;
  }

  createRequestStringForMarket(metadata: MetaMarketDTO): Array<string> {
    // Anything else in inputValue does not need value in it
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      let symbol = metadata.pairs[index].symbol;
      if (symbol.startsWith(`1000PEPEUSD`)) symbol = `PEPEUSDT`; 
      else if (symbol.startsWith(`1000SHIBUSD`)) symbol = `SHIBUSDT`; 
      requestString += `/api/v3/ticker/24hr?symbol=${symbol}`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: BinanceResponse): MarketData | undefined {
    if (
      !(
        Object.keys(resp).includes("bidPrice") &&
        Object.keys(resp).includes("askPrice") &&
        Object.keys(resp).includes("lastPrice")
      )
    ) {
      console.log(resp);
      console.warn(
        `Corrupt data in ${__filename}, please check the  coinbase update`
      );
      return undefined;
    }

    return {
      market: "binance",
      bid: resp.bidPrice,
      ask: resp.askPrice,
      index: resp.lastPrice,
    };
  }
}
