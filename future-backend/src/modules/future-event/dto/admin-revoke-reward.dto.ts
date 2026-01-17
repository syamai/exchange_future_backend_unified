import { ApiProperty } from "@nestjs/swagger";
import { IsNotEmpty, IsNumber, IsString } from "class-validator";

export class AdminRevokeRewardDto {
  @ApiProperty({
    description: "Amount to revoke",
    example: "100",
  })
  @IsNotEmpty()
  @IsString()
  amount: string;

  @ApiProperty({
    description: "User ID to revoke reward from",
    example: 1,
  })
  @IsNotEmpty()
  @IsNumber()
  userId: number;
} 