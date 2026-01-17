import { PartialType } from "@nestjs/swagger";
import { UserEntity } from "src/models/entities/user.entity";

export class ResponseLogin extends PartialType(UserEntity) {
  secret: string;
  accessToken: string;
  refreshToken: string;
}
