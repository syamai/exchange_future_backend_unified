import { UserSettingEntity } from "src/models/entities/user-setting.entity";
import { NOTIFICATION_KEY } from "src/shares/enums/setting.enum";
import { EntityRepository, Repository } from "typeorm";

@EntityRepository(UserSettingEntity)
export class UserSettingRepository extends Repository<UserSettingEntity> {
  static FAVORITE_MARKET = "FAVORITE_MARKET";
  async getUserSettingToSendFundingFeeMail() {
    return this.createQueryBuilder("user_settings")
      .select("*")
      .innerJoin("users", "users", "users.id = user_settings.userId")
      .where("user_settings.key =:key", { key: NOTIFICATION_KEY.NOTIFICATION })
      .getRawMany();
  }
}
