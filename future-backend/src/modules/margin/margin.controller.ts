import { Controller } from "@nestjs/common";
import { ApiBearerAuth, ApiTags } from "@nestjs/swagger";
import { MarginService } from "./margin.service";

@Controller("margin")
@ApiTags("Margin")
@ApiBearerAuth()
export class MarginController {
  constructor(private readonly marginService: MarginService) {}
}
