import { Controller, Get } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { MasterDataService } from "./master-data.service";

@Controller("master-data")
@ApiTags("master-data")
export class MasterDataController {
  constructor(private readonly masterDataService: MasterDataService) {}

  @Get()
  async getMasterData() {
    return {
      data: await this.masterDataService.getMasterData(),
    };
  }
}
