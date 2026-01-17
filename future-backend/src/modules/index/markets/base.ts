import {
  CandleData,
  CandleResponseDTO,
  MetaMarketDTO,
  BaseMarketResponseDTO,
  MarketData,
} from "src/modules/index/dto/index.dto";

export class MarketCrawler {
  // eslint-disable-next-line
  createRequestString(metadata: MetaMarketDTO): Array<string> {
    throw "Error dont use super function " + __filename;
  }

  // eslint-disable-next-line
  transformResponse(resp: CandleResponseDTO): Array<CandleData> {
    throw "Error dont use super function " + __filename;
  }

  // eslint-disable-next-line
  createRequestStringForMarket(metadata: MetaMarketDTO): Array<string> {
    throw "Error dont use super function " + __filename;
  }

  // eslint-disable-next-line
  transformResponseMaketIndex(resp: BaseMarketResponseDTO): MarketData {
    throw "Error when return null data " + __filename;
  }
}
