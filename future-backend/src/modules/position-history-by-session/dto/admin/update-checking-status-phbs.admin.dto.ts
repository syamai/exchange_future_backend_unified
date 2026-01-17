import { ApiProperty } from "@nestjs/swagger";
import { PositionHistoryBySessionCheckingStatus } from "../../position-history-by-session.const";
import { IsNotEmpty } from "class-validator";

export class UpdateCheckingStatusPhbsDto {
  @ApiProperty({
    example: PositionHistoryBySessionCheckingStatus.NORMAL,
    enum: PositionHistoryBySessionCheckingStatus,
  })
  @IsNotEmpty()
  status: PositionHistoryBySessionCheckingStatus;
}
