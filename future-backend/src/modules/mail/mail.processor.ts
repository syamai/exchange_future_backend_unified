import { MailerService } from "@nestjs-modules/mailer";
import { Process, Processor } from "@nestjs/bull";
import { Logger } from "@nestjs/common";
import { Job } from "bull";
import { getConfig } from "src/configs";
import { mailConfig } from "src/configs/mail.config";
import { LiquidationCallDto } from "src/modules/mail/dto/liquidation-call.dto";
import { TestMailDto } from "src/modules/mail/dto/test-mail.dto";
import { UpdateEmailDto } from "src/modules/mail/dto/update-email.dto";
import { UserService } from "src/modules/user/users.service";
import { FundingService } from "../funding/funding.service";
//import * as moment from 'moment';

@Processor("mail")
export class MailProcessor {
  public static MAIL_BANNER_LINK = `${getConfig().get<string>(
    "mail.domain"
  )}banner.png`;

  constructor(
    private readonly mailerService: MailerService,
    private readonly userService: UserService,
    private readonly fundingService: FundingService,

    private readonly logger: Logger
  ) {}

  @Process("sendUpdateEmail")
  async sendVerifyEmail({ data }: Job<UpdateEmailDto>): Promise<number> {
    this.logger.log(
      `Start job: sendUpdateEmail user ${data.userId} email ${data.email}`
    );
    const antiPhishingCode = await this.userService.getAntiPhishingCode(
      data.userId
    );
    const context = {
      email: data.email,
      confirmLink: data.confirmLink,
      bannerLink: MailProcessor.MAIL_BANNER_LINK,
      walletAddress: data.walletAddress,
    };
    if (antiPhishingCode) {
      context["antiPhishingCode"] = antiPhishingCode;
    }
    if (data.oldEmail) {
      context["oldEmail"] = data.oldEmail;
    }
    try {
      await this.mailerService.sendMail({
        from: mailConfig.from,
        to: data.email,
        subject: `Lagom - Email ${data.oldEmail ? "Update" : "Confirmation"}`,
        template: `src/modules/mail/templates/${
          data.oldEmail ? "update-email" : "verify-email"
        }.hbs`,
        context: context,
      });
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log(
      `Done job: sendUpdateEmail ${data.userId} email ${data.email}`
    );
    return 1;
  }

  @Process("sendLiquidationCall")
  async sendLiquidationCall(job: Job): Promise<number> {
    this.logger.debug("Start job: sendLiquidationCall");
    const liquidationDto: LiquidationCallDto = job.data;
    const user = await this.userService.findUserById(liquidationDto.userId);
    if (!user || !user.email) {
      this.logger.log(`User ${user.id} do not have an email`);
      this.logger.log("Done job: sendLiquidationCall");
      return 1;
    }
    this.logger.log(
      `Sending liquidation email of market ${liquidationDto.market} to ${user.email}`
    );
    try {
      await this.mailerService.sendMail({
        from: mailConfig.from,
        to: user.email,
        subject: "Lagom - Liquidation Notification",
        template: "src/modules/mail/templates/liquidation-call.hbs",
        context: {
          email: user.email,
          side: liquidationDto.side,
          size: liquidationDto.size,
          market: liquidationDto.market,
          bannerLink: MailProcessor.MAIL_BANNER_LINK,
          antiPhishingCode: user?.antiPhishingCode || null,
        },
      });
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log("Done job: sendLiquidationCall");
    return 1;
  }

  @Process("sendTestMail")
  async sendTestMail(job: Job): Promise<number> {
    this.logger.debug("Start job: sendTestMail");
    const testMailDto: TestMailDto = job.data;

    this.logger.log(`Sending test email to ${testMailDto.email}`);
    try {
      await this.mailerService.sendMail({
        from: mailConfig.from,
        to: testMailDto.email,
        subject: testMailDto.subject,
        template: "src/modules/mail/templates/test-email.hbs",
        context: {
          email: testMailDto.email,
          content: testMailDto.content,
          bannerLink: MailProcessor.MAIL_BANNER_LINK,
        },
      });
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log("Done job: sendTestMail");
    return 1;
  }

  @Process("sendMailFundingFee")
  async sendMailFundingFee(job: Job): Promise<number> {
    this.logger.debug("Start job: sendMailFundingFee");

    const {
      email,
      fundingRate,
      exchangeLink,
      phoneSupport,
      emailSupport,
      symbols,
      singular,
      footerImage,
      bannerImage,
      many,
      ...objI18nT
    } = job.data;
    this.logger.log(`Sending test email to ${email}`);
    try {
      await this.mailerService.sendMail({
        from: mailConfig.from,
        to: email,
        subject: objI18nT.key_funding_0,
        template: "src/modules/mail/templates/fundingFee.hbs",
        context: {
          email,
          fundingRate,
          exchangeLink,
          emailSupport,
          phoneSupport,
          symbols,
          singular,
          bannerImage,
          footerImage,
          many,
          antiPhishingCode: await this.userService.getAntiPhishingCodeByEmail(
            email
          ),
          ...objI18nT,
        },
      });
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log("Done job: sendMailFundingFee");
    return 1;
  }

  @Process("sendTriggeredMail")
  async sendTriggeredMail(job: Job): Promise<number> {
    this.logger.debug("Start job: sendTriggeredMail");
    const {
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
      ...objI18nT
    } = job.data;
    this.logger.log(`Sending test email to ${email}`);

    try {
      await this.mailerService.sendMail({
        to: email,
        from: mailConfig.from,
        subject: objI18nT.key_TpSl_0,
        template: "src/modules/mail/templates/TpslStopOrder.hbs",
        context: {
          email,
          order,
          trigger,
          time,
          contractType,
          exchangeLink,
          emailSupport,
          phoneSupport,
          tpSlPrice,
          type,
          antiPhishingCode: await this.userService.getAntiPhishingCodeByEmail(
            email
          ),
          ...objI18nT,
        },
      });
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log("Done job: sendTriggeredMail");
    return 1;
  }

  @Process("sendFundingMailAndAddToQueue")
  async sendFundingMailAndAddToQueue(job: Job): Promise<number> {
    this.logger.debug("Start job: sendFundingMailAndAddToQueue");
    const { dataFundingRates } = job.data;
    try {
      await this.fundingService.sendMailFundingFee(dataFundingRates);
    } catch (e) {
      this.logger.debug(e);
    }
    this.logger.log("Done job: sendTriggeredMail");
    return 1;
  }
}
