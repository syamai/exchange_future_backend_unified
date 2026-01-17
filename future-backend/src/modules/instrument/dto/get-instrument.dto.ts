import { ApiProperty } from "@nestjs/swagger";
import { IsEnum, IsOptional } from "class-validator";
import { InstrumentTypes } from "src/shares/enums/instrument.enum";

export class GetInstrumentDto {
  @ApiProperty({ enum: InstrumentTypes })
  @IsOptional()
  @IsEnum(InstrumentTypes)
  type: string;
}
