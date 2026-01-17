import { InjectQueue } from "@nestjs/bull";
import {
  CACHE_MANAGER,
  HttpException,
  HttpStatus,
  Inject,
  Injectable,
} from "@nestjs/common";
import { Queue } from "bull";
import { Cache } from "cache-manager";
import { RedisService } from "nestjs-redis";
import { lastValueFrom, map } from "rxjs";
import { getConfig } from "src/configs";
import { UserEntity } from "src/models/entities/user.entity";
import { LiquidationCallDto } from "src/modules/mail/dto/liquidation-call.dto";
import { TestMailDto } from "src/modules/mail/dto/test-mail.dto";
import { UpdateEmailDto } from "src/modules/mail/dto/update-email.dto";
import { MAIL, TP_SL_NOTIFICATION } from "src/shares/enums/setting.enum";
import { httpErrors } from "src/shares/exceptions";
import { HttpService } from "@nestjs/axios";
import { CommandOutput } from "../matching-engine/matching-engine.const";
import { OrderEntity } from "src/models/entities/order.entity";
import { UserSettingeService } from "../user-setting/user-setting.service";
import { OrderTrigger, OrderType } from "src/shares/enums/order.enum";
import { InjectRepository } from "@nestjs/typeorm";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import * as moment from "moment";
import { LIST_SYMBOL_COINM } from "../transaction/transaction.const";
import { TranslateService } from "../translate/translate.service";
import * as KeyJSONTranslate from "../../i18n/en/translate.json";
import { UserRepository } from "src/models/repositories/user.repository";
import { RedisClient } from "src/shares/redis-client/redis-client";

@Injectable()
export class MailService {
  public static MAIL_DOMAIN = getConfig().get<string>("mail.domain");
  public static MAIL_PREFIX = "MAIL_CACHE_";
  public static MAIL_TTL = 1800; // 30 minutes

  public static WAIT_PREFIX = "MAIL_WAIT_";
  public static WAIT_TTL = 60; // 1 minutes

  constructor(
    @InjectQueue("mail") private readonly emailQueue: Queue,
    private readonly redisService: RedisService,
    @Inject(CACHE_MANAGER) private cacheManager: Cache,
    private readonly httpService: HttpService,
    private readonly userSettingService: UserSettingeService,

    @InjectRepository(UserSettingRepository, "master")
    public readonly userSettingMasterReport: UserSettingRepository,
    private readonly i18n: TranslateService,
    @InjectRepository(UserRepository, "master")
    private usersRepositoryMaster: UserRepository,
    private readonly redisClient: RedisClient
  ) {}

  async checkWaitTime(userId: number): Promise<string> {
    const isWait = await this.redisService
      .getClient()
      .get(`${MailService.WAIT_PREFIX}${userId}`);
    if (isWait) {
      throw new HttpException(
        {
          ...httpErrors.EMAIL_WAIT_TIME,
          waitUntil: Number(isWait) + 60000,
        },
        HttpStatus.BAD_REQUEST
      );
    }
    return isWait;
  }

  async getPendingEmail(userId: UserEntity): Promise<string> {
    const keys = await this.redisService
      .getClient()
      .keys(`${MailService.MAIL_PREFIX}*`);
    if (keys.length == 0) return null;
    for (let i = keys.length - 1; i >= 0; i--) {
      const dto: UpdateEmailDto = JSON.parse(
        await this.redisClient.getInstance().get(keys[i])
      );
      if (dto.oldEmail === userId.email) return dto.email;
    }
    return null;
  }

  async sendLiquidationCall(liquidationDto: LiquidationCallDto): Promise<void> {
    await this.emailQueue.add("sendLiquidationCall", {
      ...liquidationDto,
    });
  }

  async sendTestEmail(
    email: string,
    subject: string,
    content: string
  ): Promise<void> {
    const testMailDto: TestMailDto = { email, subject, content };
    await this.emailQueue.add("sendTestMail", {
      ...testMailDto,
    });
  }

  async getQueueStats(): Promise<{
    activeCount: number;
    failedCount: number;
    waitingCount: number;
  }> {
    const activeCount = await this.emailQueue.getActiveCount();
    const failedCount = await this.emailQueue.getFailedCount();
    const waitingCount = await this.emailQueue.getWaitingCount();
    return {
      activeCount,
      failedCount,
      waitingCount,
    };
  }

  public async sendMailTpslStopOrder(
    email: string,
    order: any,
    trigger: string,
    exchangeLink: string,
    emailSupport: string,
    phoneSupport: string,
    bannerImage: string,
    footerImage: string
  ) {
    try {
      const time = moment.utc(order.updatedAt).format("YYYY-MM-DD HH:mm:ss");
      const tpSlPrice = order.tpSLPrice;
      const type = order.type.toLowerCase();
      let contractType = "USD-M";
      const isCoiM = LIST_SYMBOL_COINM.includes(order.symbol);
      if (isCoiM) {
        contractType = "COIN-M";
      }
      const objI18nT = {};
      const user = await this.usersRepositoryMaster.findOne({
        where: { email },
      });
      for (const key of Object.keys(KeyJSONTranslate)) {
        objI18nT[key] = await this.i18n.translate(user, key, { email });
      }
      await this.emailQueue.add("sendTriggeredMail", {
        email,
        order,
        trigger,
        exchangeLink,
        contractType,
        emailSupport,
        phoneSupport,
        time,
        tpSlPrice,
        type,
        bannerImage,
        footerImage,
        ...objI18nT,
      });
    } catch (error) {
      console.log("======error add queue send mail", error);
    }
  }

  async sendMailFundingFee(
    email: string,
    fundingRate: string,
    symbols: string[]
  ): Promise<void> {
    let phoneSupport = await this.cacheManager.get<string>(
      `${MAIL.PHONE_SUPPORT}`
    );
    let emailSupport = await this.cacheManager.get<string>(
      `${MAIL.EMAIL_SUPPORT}`
    );
    let bannerImage = await this.cacheManager.get<string>(
      `${MAIL.BANNER_IMAGE}`
    );
    let footerImage = await this.cacheManager.get<string>(
      `${MAIL.FOOTER_IMAGE}`
    );

    const exchangeLink = MAIL.EXCHANGE_SITE;
    fundingRate = Number(fundingRate).toFixed(3);
    if (!phoneSupport || emailSupport) {
      const url = `${process.env.SPOT_URL_API}/api/v1/site-settings`;
      console.log("url: ", url);
      const result = await lastValueFrom(
        this.httpService.get(url).pipe(map((response) => response.data))
      );
      if (!result.data) return;
      if (result.data.contact_phone) {
        await this.cacheManager.set(
          <string>`${MAIL.PHONE_SUPPORT}`,
          result.data.contact_phone,
          {
            ttl: MailService.MAIL_TTL,
          }
        );
        phoneSupport = result.data.contact_phone;
      }
      if (result.data.contact_email) {
        await this.cacheManager.set(
          <string>`${MAIL.EMAIL_SUPPORT}`,
          result.data.contact_email,
          {
            ttl: MailService.MAIL_TTL,
          }
        );
        emailSupport = result.data.contact_email;
      }
    }
    if (!bannerImage || !footerImage) {
      const url = `${process.env.SPOT_URL_API}/api/v1/get-image-mail`;
      const result = await lastValueFrom(
        this.httpService.get(url).pipe(map((response) => response.data))
      );
      if (!result.data) return;
      await this.cacheManager.set(
        <string>`${MAIL.BANNER_IMAGE}`,
        result.data.header,
        {
          ttl: MailService.MAIL_TTL,
        }
      );
      await this.cacheManager.set(
        <string>`${MAIL.FOOTER_IMAGE}`,
        result.data.footer,
        {
          ttl: MailService.MAIL_TTL,
        }
      );
      bannerImage = result.data.header;
      footerImage = result.data.footer;
    }
    let singular = false;
    let many = false;
    if (symbols.length > 1) {
      many = true;
    }
    if (symbols.length == 1) {
      singular = true;
    }

    const objI18nT = {};
    const user = await this.usersRepositoryMaster.findOne({ where: { email } });
    for (const key of Object.keys(KeyJSONTranslate)) {
      objI18nT[key] = await this.i18n.translate(user, key, { email });
    }
    await this.emailQueue.add("sendMailFundingFee", {
      email,
      fundingRate,
      exchangeLink,
      phoneSupport,
      emailSupport,
      symbols,
      singular: singular,
      many: many,
      bannerImage,
      footerImage,
      ...objI18nT,
    });
  }

  async sendMailWhenTpSlOrderTriggered(command: CommandOutput) {
    for (const order of command.orders) {
      Object.assign(order, OrderEntity);
      const conditionSendMailTrigger =
        order.isTriggered &&
        (order.tpSLType == OrderType.TAKE_PROFIT_MARKET ||
          (order.tpSLType == OrderType.STOP_MARKET &&
            (order.isClosePositionOrder == true || order.isTpSlOrder == true)));
      if (conditionSendMailTrigger) {
        try {
          const {
            userNotificationSettings,
            user,
          } = await this.userSettingService.getUserSettingByUserId(
            order.userId as number
          );
          if (!userNotificationSettings || !user) {
            continue;
          }
          if (
            Number(userNotificationSettings.notificationQuantity) >=
            TP_SL_NOTIFICATION.QUANTITY
          ) {
            continue;
          }
          if (!userNotificationSettings.stopLossTrigger) {
            continue;
          }
          const trigger =
            order.trigger === OrderTrigger.ORACLE ? "Mark" : "Last";
          let phoneSupport = await this.cacheManager.get<string>(
            `${MAIL.PHONE_SUPPORT}`
          );
          let emailSupport = await this.cacheManager.get<string>(
            `${MAIL.EMAIL_SUPPORT}`
          );

          const exchangeLink = MAIL.EXCHANGE_SITE;
          if (!phoneSupport || !emailSupport) {
            const url = `${process.env.SPOT_URL_API}/api/v1/site-settings`;
            const result = await lastValueFrom(
              this.httpService.get(url).pipe(map((response) => response.data))
            );
            if (!result.data) {
              continue;
            }
            if (result.data.contact_phone) {
              await this.cacheManager.set(
                <string>`${MAIL.PHONE_SUPPORT}`,
                result.data.contact_phone,
                {
                  ttl: MailService.MAIL_TTL,
                }
              );
            }

            if (result.data.contact_email) {
              await this.cacheManager.set(
                <string>`${MAIL.EMAIL_SUPPORT}`,
                result.data.contact_email,
                {
                  ttl: MailService.MAIL_TTL,
                }
              );
            }
            phoneSupport = result.data.contact_phone;
            emailSupport = result.data.contact_email;
          }

          let bannerImage = await this.cacheManager.get<string>(
            `${MAIL.BANNER_IMAGE}`
          );
          let footerImage = await this.cacheManager.get<string>(
            `${MAIL.FOOTER_IMAGE}`
          );
          if (!bannerImage || !footerImage) {
            const url = `${process.env.SPOT_URL_API}/api/v1/get-image-mail`;
            const result = await lastValueFrom(
              this.httpService.get(url).pipe(map((response) => response.data))
            );
            if (!result.data) return;
            await this.cacheManager.set(
              <string>`${MAIL.BANNER_IMAGE}`,
              result.data.header,
              {
                ttl: MailService.MAIL_TTL,
              }
            );
            await this.cacheManager.set(
              <string>`${MAIL.FOOTER_IMAGE}`,
              result.data.footer,
              {
                ttl: MailService.MAIL_TTL,
              }
            );
            bannerImage = result.data.header;
            footerImage = result.data.footer;
          }
          await this.sendMailTpslStopOrder(
            user.email,
            order,
            trigger as string,
            exchangeLink,
            emailSupport,
            phoneSupport,
            bannerImage,
            footerImage
          );
          userNotificationSettings.notificationQuantity += 1;
          await this.userSettingMasterReport.save(userNotificationSettings);
        } catch (error) {
          console.log("======= error send mail", error);
          continue;
        }
      }
    }
  }
}
