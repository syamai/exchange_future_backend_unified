import { HttpException, HttpStatus, Injectable, Logger } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { UserSettingEntity } from "src/models/entities/user-setting.entity";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import { httpErrors } from "src/shares/exceptions";
import * as moment from "moment";
import {
  NOTIFICATION_KEY,
  TP_SL_NOTIFICATION,
} from "src/shares/enums/setting.enum";
import { AccountRepository } from "src/models/repositories/account.repository";
import { UserRepository } from "src/models/repositories/user.repository";
import { UpdateNotificationSettingDto } from "./dto/user-setting-dto";
moment().format();
@Injectable()
export class UserSettingeService {
  constructor(
    @InjectRepository(UserSettingRepository, "report")
    public readonly userSettingRepoReport: UserSettingRepository,
    @InjectRepository(UserSettingRepository, "master")
    public readonly userSettingMasterReport: UserSettingRepository,
    @InjectRepository(AccountRepository, "report")
    public readonly accountRepoReport: AccountRepository,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository,
    private readonly logger: Logger
  ) {}

  async updateUserSettingByKey(
    key: string,
    body: UpdateNotificationSettingDto,
    userId: number
  ): Promise<UserSettingEntity> {
    const {
      limitOrder,
      marketOrder,
      stopLimitOrder,
      stopMarketOrder,
      traillingStopOrder,
      takeProfitTrigger,
      stopLossTrigger,
      fundingFeeTriggerValue,
      fundingFeeTrigger,
    } = body;
    const setting = await this.userSettingRepoReport.findOne({
      where: {
        key: key,
        userId,
      },
    });
    let newSetting;
    if (setting) {
      newSetting = await this.userSettingMasterReport.update(
        { userId: setting.userId, key: setting.key },
        {
          ...body,
        }
      );
    } else {
      newSetting = new UserSettingEntity();
      newSetting.key = key;
      newSetting.limitOrder = limitOrder;
      newSetting.marketOrder = marketOrder;
      newSetting.stopLimitOrder = stopLimitOrder;
      newSetting.stopMarketOrder = stopMarketOrder;
      newSetting.traillingStopOrder = traillingStopOrder;
      newSetting.takeProfitTrigger = takeProfitTrigger;
      newSetting.stopLossTrigger = stopLossTrigger;
      newSetting.fundingFeeTriggerValue = fundingFeeTriggerValue;
      newSetting.fundingFeeTrigger = fundingFeeTrigger;
      newSetting.userId = userId;
      await this.userSettingMasterReport.insert(newSetting);
    }
    return newSetting;
  }

  async getUserSettingByKey(
    key: string,
    userId: number
  ): Promise<UserSettingEntity> {
    const userSetting = await this.userSettingRepoReport.findOne({
      where: {
        key: key,
        userId,
      },
    });
    if (userSetting) {
      return userSetting;
    } else {
      const newSetting = new UserSettingEntity();
      newSetting.key = key;
      newSetting.limitOrder = false;
      newSetting.marketOrder = false;
      newSetting.stopLimitOrder = false;
      newSetting.stopMarketOrder = false;
      newSetting.traillingStopOrder = false;
      newSetting.takeProfitTrigger = false;
      newSetting.stopLossTrigger = false;
      newSetting.fundingFeeTriggerValue = 0.25;
      newSetting.fundingFeeTrigger = false;
      newSetting.userId = userId;
      await this.userSettingMasterReport.insert(newSetting);
      return newSetting;
    }
  }

  async updateNotificationSetting(key: string, userId: number) {
    const now = new Date().getTime();
    const userSetting = await this.userSettingRepoReport.findOne({
      where: {
        key: key,
        userId,
      },
    });

    if (!userSetting) {
      throw new HttpException(
        httpErrors.USER_SETTING_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }
    const endTime = moment().utc().endOf("day").toDate().getTime();

    const startTime = userSetting.time.getTime();
    if (
      now <= endTime &&
      userSetting.notificationQuantity === TP_SL_NOTIFICATION.QUANTITY
    ) {
      throw new HttpException(
        httpErrors.USER_SETTING_TP_SL_NOTIFICATION,
        HttpStatus.BAD_REQUEST
      );
    }
    if (now >= startTime && now <= endTime) {
      userSetting.notificationQuantity = userSetting.notificationQuantity + 1;
      await this.userSettingMasterReport.save(userSetting);
    }
    if (now > endTime) {
      userSetting.notificationQuantity = 0;
      await this.userSettingMasterReport.save(userSetting);
    }
    return;
  }

  async getUserSettingByUserId(userId: number) {
    try {
      const user = await this.userRepoReport.findOne({
        where: {
          id: userId,
        },
      });
      if (!user) {
        return {};
      }
      const userNotificationSettings = await this.userSettingRepoReport.findOne(
        {
          key: NOTIFICATION_KEY.NOTIFICATION,
          userId: userId,
        }
      );
      if (!userNotificationSettings) {
        return {};
      }
      return { userNotificationSettings, user };
    } catch (error) {
      this.logger.error(`Failed to find setting at error: ${error}`);
    }
  }
}
