import { MarketCrawler } from "src/modules/index/markets/base";
import {
  CandleData,
  CandleResponseDTO,
  CoinbaseResponse,
  MarketData,
  MetaMarketDTO,
} from "src/modules/index/dto/index.dto";

export class Coinbase extends MarketCrawler {
  constructor() {
    super();
  }
  /**
   * This function should create request string base on input meta data
   * Example format:
   * curl --request GET \
   *      --url 'https://api.exchange.coinbase.com/products/BTC-USD/candles?granularity=300&start=1636793000&end=1636793211' \
   *      --header 'Accept: application/json'
   *
   *
   */
  createRequestString(metadata: MetaMarketDTO): Array<string> {
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();

      requestString += `/products/${
        metadata.pairs[Number(index)].symbol
      }/candles?`;

      if (metadata.granularity) {
        requestString += `granularity=${metadata.granularity}`;
      } else {
        requestString += "granularity=60";
      }
      requestStrings.push(requestString);
    }
    return requestStrings;
  }
  /*
  Example data
  [
      [
          1636797300,
          63442.3,
          63487.23,
          63487.23,       
          63469.1,
          2.997922
      ],[...][...]
      
  ]
  **/
  transformResponse(resp: CandleResponseDTO): Array<CandleData> {
    const respData = resp;
    const output = [];
    console.log(respData);
    for (const index in respData) {
      const data = respData[index];
      if (data.length != 6) {
        console.log(data);
        console.warn(
          `Corrupt data in ${__filename}, please check the  coinbase update`
        );
        continue;
      }
      output.push({
        market: "coinbase",
        timestamp: data[0] * 1000,
        low: data[1],
        high: data[2],
        open: data[3],
        close: data[4],
        volume: data[5],
      });
    }
    return output;
  }

  createRequestStringForMarket(metadata: MetaMarketDTO): Array<string> {
    console.log(metadata);
    // Anything else in inputValue does not need value in it
    const requestStrings = [];

    for (const index in metadata.pairs) {
      let requestString = metadata.baseUrl.trim();
      requestString += `/products/${metadata.pairs[index].symbol}/ticker?limit=1`;
      requestStrings.push(requestString);
    }

    return requestStrings;
  }

  transformResponseMaketIndex(resp: CoinbaseResponse): MarketData | undefined {
    if (
      !(
        Object.keys(resp).includes("price") &&
        Object.keys(resp).includes("bid") &&
        Object.keys(resp).includes("ask")
      )
    ) {
      console.log(resp);
      console.warn(
        `Corrupt data in ${__filename}, please check the  coinbase update`
      );
      return undefined;
    }
    return {
      market: "coinbase",
      bid: resp.bid,
      ask: resp.ask,
      index: resp.price,
    };
  }
}
