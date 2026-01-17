import { Controller, Get, Param, Query } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { OrderbookService } from "src/modules/orderbook/orderbook.service";

@Controller("orderbook")
@ApiTags("Orderbook")
export class OrderbookController {
  constructor(private readonly orderbookService: OrderbookService) {}

  @Get("/group-orderbook/:symbol")
  async getGroupOrderbook(@Param("symbol") symbol: string, @Query('tickSize') tickSize: string) {
    return await this.orderbookService.getGroupOrderbook(symbol, tickSize);
  }

  @Get("/:symbol")
  async get(@Param("symbol") symbol: string) {
    return await this.orderbookService.getOrderbook(symbol);
  }
}
