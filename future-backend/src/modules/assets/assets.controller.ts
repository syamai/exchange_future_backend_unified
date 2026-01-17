import { Body, Controller, Delete, Get, Param, Post, Query } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { ContractType } from "src/shares/enums/order.enum";
import { AssetsService } from "./assets.service";
import { CreateAssetDto } from "./dto/create-asset.dto";

@Controller("assets")
@ApiTags("Assets")
export class AssetsController {
  constructor(private readonly assetsService: AssetsService) {}

  @Get("/")
  async getAllAssets(@Query("contractType") contractType: ContractType) {
    return await this.assetsService.findAllAssets(contractType);
  }

  @Post("/")
  async createAsset(@Body() dto: CreateAssetDto) {
    return await this.assetsService.createAsset(dto);
  }

  @Delete("/:id")
  async deleteAsset(@Param("id") id: number) {
    return await this.assetsService.deleteAsset(id);
  }
}
