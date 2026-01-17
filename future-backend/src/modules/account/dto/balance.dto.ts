import { PartialType } from "@nestjs/swagger";
import { AccountEntity } from "src/models/entities/account.entity";

export class BalanceDto extends PartialType(AccountEntity) {
  balance?: string;
  availableBalance?: string;
  crossBalance?: string;
  isolatedBalance?: string;
  maxAvailableBalance?: string;
}
