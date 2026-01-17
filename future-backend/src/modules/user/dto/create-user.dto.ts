import { IsEnum, IsNotEmpty, IsOptional } from "class-validator";
import { ApiProperty } from "@nestjs/swagger";
import {
  UserIsLocked,
  UserRole,
  UserStatus,
  UserType,
} from "src/shares/enums/user.enum";

export class CreateUserDto {
  @ApiProperty({
    required: true,
    example: 1,
  })
  @IsNotEmpty()
  id: number;

  @ApiProperty({
    required: true,
    example: "abcd@gmail.com",
  })
  @IsNotEmpty()
  email: string;

  @ApiProperty({
    required: false,
  })
  @IsOptional()
  position: string;

  @ApiProperty({
    required: false,
    enum: [UserRole.ADMIN, UserRole.SUPER_ADMIN, UserRole.USER],
  })
  @IsEnum(UserRole)
  @IsOptional()
  role: string;

  @ApiProperty({
    required: false,
    enum: [UserType.RESTRICTED, UserType.UNRESTRICTED],
  })
  @IsEnum(UserType)
  @IsOptional()
  userType: string;

  @ApiProperty({
    required: false,
    enum: [UserIsLocked.LOCKED, UserIsLocked.UNLOCKED],
  })
  @IsEnum(UserIsLocked)
  @IsOptional()
  isLocked: string;

  @ApiProperty({
    required: false,
    enum: [UserStatus.ACTIVE, UserStatus.DEACTIVE],
  })
  @IsEnum(UserStatus)
  @IsOptional()
  status: string;
}
