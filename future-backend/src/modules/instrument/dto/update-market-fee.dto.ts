import { PartialType } from "@nestjs/swagger";
import { CreateMarketFeeDto } from "./create-market-free.dto";

export class UpdateMarketFeeDto extends PartialType(CreateMarketFeeDto) {}
