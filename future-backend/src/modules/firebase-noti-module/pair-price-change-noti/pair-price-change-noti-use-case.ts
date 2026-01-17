import { Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import axios from "axios";
import BigNumber from "bignumber.js";
import { InstrumentRepository } from "src/models/repositories/instrument.repository";
import { UserSettingRepository } from "src/models/repositories/user-setting.repository";
import { FirebaseAdminService } from "../firebase-admin.service";

@Injectable()
export class PairPriceChangeNotiUseCase {
  constructor(
    private readonly firebaseAdminService: FirebaseAdminService,

    @InjectRepository(InstrumentRepository, "report")
    public readonly instrumentRepoReport: InstrumentRepository,

    @InjectRepository(UserSettingRepository, "report")
    public readonly userSettingRepoReport: UserSettingRepository
  ) {}

  async execute() {
    while (true) {
      // sleep till next noti in ms
      const { sleepMs, beforeMilestoneTime, nextMilestoneTime } = this.getTimeForNextNoti();
      console.log(`[execute] - sleepMs: ${sleepMs}, beforeMilestoneTime: ${beforeMilestoneTime}, nextMilestoneTime: ${nextMilestoneTime}`);
      
      await this.sleep(sleepMs);

      try {
        await this.performNoti(beforeMilestoneTime, nextMilestoneTime);
        console.log(`[execute] - Done!`);
      } catch (e) {
        console.log(`[execute] - error: ${e}`);
      }
    }
  }

  private async sleep(ms: number) {
    console.log(`[sleep] - Sleeping for ${ms}ms`);
    await new Promise((resolve) => setTimeout(resolve, ms));
    console.log(`[sleep] - Sleep completed`);
  }

  private getTimeForNextNoti() {
    console.log(`[getTimeForNextNoti] - Starting time calculation`);
    const now = new Date();
    const currentHour = now.getHours();
    console.log(`[getTimeForNextNoti] - Current hour: ${currentHour}`);

    // Milestones every 4h
    const milestones = [0, 4, 8, 12, 16, 20];
    console.log(`[getTimeForNextNoti] - Milestones: ${milestones}`);

    // ---- Find current milestone ----
    let currentMilestone = milestones
      .slice()
      .reverse()
      .find((m) => m <= currentHour);
    if (currentMilestone === undefined) currentMilestone = 0;
    console.log(`[getTimeForNextNoti] - Current milestone: ${currentMilestone}`);

    const beforeMilestoneTime = new Date(now);
    beforeMilestoneTime.setHours(currentMilestone, 0, 0, 0);

    // ---- Find next milestone ----
    let nextMilestone = milestones.find((m) => m > currentHour);
    if (nextMilestone === undefined) nextMilestone = 0; // next day
    console.log(`[getTimeForNextNoti] - Next milestone: ${nextMilestone}`);

    const nextMilestoneTime = new Date(now);
    if (nextMilestone === 0 && currentHour >= 20) {
      // Tomorrow at 0h
      nextMilestoneTime.setDate(nextMilestoneTime.getDate() + 1);
      nextMilestoneTime.setHours(0, 0, 0, 0);
      console.log(`[getTimeForNextNoti] - Next milestone is tomorrow at 0h`);
    } else {
      nextMilestoneTime.setHours(nextMilestone, 0, 0, 0);
    }

    // ---- Time difference ----
    const sleepMs = nextMilestoneTime.getTime() - now.getTime() + 5 * 60 * 1000;
    console.log(`[getTimeForNextNoti] - Sleep time: ${sleepMs}ms`);

    const result = {
      beforeMilestoneTime: beforeMilestoneTime.getTime(),
      nextMilestoneTime: nextMilestoneTime.getTime(),
      sleepMs,
    };
    console.log(`[getTimeForNextNoti] - Result:`, result);
    return result;
  }

  private async performNoti(timeBefore: number, timeAfter: number) {
    console.log(`[performNoti] - Starting with timeBefore: ${timeBefore}, timeAfter: ${timeAfter}`);
    
    // get all instruments
    console.log(`[performNoti] - Getting symbols from user settings`);
    const symbols = await this.getSymbolsFromUserSetting();
    console.log(`[performNoti] - Found ${symbols.length} symbols:`, symbols);

    if (!symbols.length) {
      console.log(`[performNoti] - No symbols found, returning early`);
      return;
    }

    // get list symbol and price if price changed
    console.log(`[performNoti] - Getting list of price changes`);
    const listPriceChanged = await this.getListPriceChanged(symbols, timeBefore, timeAfter);
    console.log(`[performNoti] - Found ${listPriceChanged.length} price changes:`, listPriceChanged);

    if (!listPriceChanged.length) {
      console.log(`[performNoti] - No price changes found, returning early`);
      return;
    }

    const keys = [];
    const priorityKeys = [];
    const usersSentCount = {};

    console.log(`[performNoti] - Processing price changes and categorizing keys`);
    for (const item of listPriceChanged) {
      const key = `${UserSettingRepository.FAVORITE_MARKET}_${item.symbol}`;
      console.log(`[performNoti] - Processing ${item.symbol}: price change = ${item.priceChange}, key = ${key}`);
      
      if (["BTCUSDT", "ETHUSDT"].includes(item.symbol)) {
        priorityKeys.push(key);
        console.log(`[performNoti] - Added ${key} to priority keys`);
      } else {
        keys.push(key);
        console.log(`[performNoti] - Added ${key} to regular keys`);
      }
    }

    console.log(`[performNoti] - Priority keys: ${priorityKeys.length}, Regular keys: ${keys.length}`);
    console.log(`[performNoti] - Priority keys:`, priorityKeys);
    console.log(`[performNoti] - Regular keys:`, keys);

    // if keys contain btc, eth, send first
    console.log(`[performNoti] - Sending notifications for priority keys first`);
    await this.sendNoti(usersSentCount, priorityKeys);
    console.log(`[performNoti] - Sending notifications for regular keys`);
    await this.sendNoti(usersSentCount, keys);
    console.log(`[performNoti] - All notifications sent`);
  }

  private async sendNoti(userSentCount: { [userId: number]: number }, keys: string[]) {
    console.log(`[sendNoti] - Starting with ${keys.length} keys:`, keys);
    
    if (!keys.length) {
      console.log(`[sendNoti] - No keys provided, returning early`);
      return;
    }
    
    // get list user setting;
    console.log(`[sendNoti] - Building query for user settings`);
    const qb = this.userSettingRepoReport
      .createQueryBuilder("us")
      .select(["us.userId userId", "u.notification_token notificationToken", "u.location location", "us.key key"])
      .leftJoin("users", "u", "u.id = us.userId")
      .where("us.key IN (:...keys)", { keys })
      .andWhere(`us.isFavorite = true`)
      .andWhere(`us.enablePriceChangeFireBase = true`)
      .orderBy("us.favoritedAt", "DESC");

    console.log(`[sendNoti] - Executing query for user settings`);
    const userSettings = await qb.getRawMany();
    console.log(`[sendNoti] - Found ${userSettings.length} user settings:`, userSettings);

    let notificationsSent = 0;
    let notificationsSkipped = 0;
    
    for (const us of userSettings) {
      const totalSent = userSentCount[us.userId];
      console.log(`[sendNoti] - Processing user ${us.userId}, total sent: ${totalSent || 0}`);
      
      if (totalSent && totalSent > 4) {
        console.log(`[sendNoti] - Skipping user ${us.userId}, already sent ${totalSent} notifications`);
        notificationsSkipped++;
        continue;
      }
      
      const symbol = us.key.split(`${UserSettingRepository.FAVORITE_MARKET}_`)[1];
      console.log(`[sendNoti] - Sending notification for symbol ${symbol} to user ${us.userId}`);
      
      const msg = this.getNotiMsg(symbol, us.location);
      console.log(`[sendNoti] - Message: ${msg}, Location: ${us.location}`);

      this.firebaseAdminService.sendMessageToToken(us.notificationToken, msg).catch((e) => {
        console.log(`[sendNoti] - Failed to send message to user ${us.userId}: ${e}`);
      });
      
      userSentCount[us.userId] = totalSent ? totalSent + 1 : 1;
      notificationsSent++;
      console.log(`[sendNoti] - Notification sent to user ${us.userId}, new total: ${userSentCount[us.userId]}`);
    }
    
    console.log(`[sendNoti] - Summary: ${notificationsSent} sent, ${notificationsSkipped} skipped`);
  }

  private async getBinanceClosePrice(symbol: string, time: number) {
    console.log(`[getBinanceClosePrice] - Getting price for ${symbol} at time ${time}`);
    const url = `https://fapi.binance.com/fapi/v1/klines?symbol=${symbol}&interval=1h&startTime=${time}&endTime=${time}`;
    console.log(`[getBinanceClosePrice] - URL: ${url}`);
    
    try {
      const result = await axios.get(url);
      console.log(`[getBinanceClosePrice] - Response received for ${symbol}, data length: ${result.data?.length || 0}`);
      
      if (result.data && result.data[0]) {
        const price = result.data[0][4];
        console.log(`[getBinanceClosePrice] - Price for ${symbol}: ${price}`);
        return price;
      } else {
        console.log(`[getBinanceClosePrice] - No data found for ${symbol}`);
        return null;
      }
    } catch (error) {
      console.log(`[getBinanceClosePrice] - Error getting price for ${symbol}: ${error}`);
      return null;
    }
  }

  private async getSymbolsFromUserSetting(): Promise<string[]> {
    console.log(`[getSymbolsFromUserSetting] - Starting to get symbols from user settings`);
    console.log(`[getSymbolsFromUserSetting] - FAVORITE_MARKET constant: ${UserSettingRepository.FAVORITE_MARKET}`);
    
    const qb = this.userSettingRepoReport
      .createQueryBuilder("us")
      .select(["distinct us.key"])
      .where(`us.key like '${UserSettingRepository.FAVORITE_MARKET}_%'`)
      .andWhere(`us.isFavorite = true`)
      .andWhere(`us.enablePriceChangeFireBase = true`);

    console.log(`[getSymbolsFromUserSetting] - Executing query for user settings`);
    const settings = await qb.getRawMany();
    console.log(`[getSymbolsFromUserSetting] - Raw settings found:`, settings);
    
    const symbols = settings.map((s) => s.key.split(`${UserSettingRepository.FAVORITE_MARKET}_`)[1]);
    console.log(`[getSymbolsFromUserSetting] - Extracted symbols:`, symbols);
    
    return symbols;
  }

  private async getListPriceChanged(
    symbols: string[],
    timeBefore: number,
    timeAfter: number
  ): Promise<{ symbol: string; priceBefore: string; priceAfter: string; priceChange: "increase" | "decrease" | "nochange" }[]> {
    console.log(`[getListPriceChanged] - Starting with ${symbols.length} symbols, timeBefore: ${timeBefore}, timeAfter: ${timeAfter}`);
    
    const listPriceChange = [];
    console.log(`[getListPriceChanged] - Processing symbols in parallel`);
    
    const results = await Promise.all(
      symbols.map(async (symbol) => {
        console.log(`[getListPriceChanged] - Processing symbol: ${symbol}`);
        
        const [priceBefore, priceAfter] = await Promise.all([
          this.getBinanceClosePrice(symbol, timeBefore),
          this.getBinanceClosePrice(symbol, timeAfter),
        ]);

        console.log(`[getListPriceChanged] - ${symbol}: priceBefore=${priceBefore}, priceAfter=${priceAfter}`);
        
        const priceChange = this.checkPriceChange(priceBefore, priceAfter);
        console.log(`[getListPriceChanged] - ${symbol}: priceChange=${priceChange}`);
        
        if (priceChange !== "nochange") {
          const result = {
            symbol,
            priceBefore,
            priceAfter,
            priceChange,
          };
          console.log(`[getListPriceChanged] - ${symbol}: Adding to results:`, result);
          return result;
        } else {
          console.log(`[getListPriceChanged] - ${symbol}: No significant price change, skipping`);
          return null;
        }
      })
    );
    
    // Filter out null results
    const filteredResults = results.filter(result => result !== null);
    console.log(`[getListPriceChanged] - Final results:`, filteredResults);
    
    return filteredResults;
  }

  private checkPriceChange(priceBefore: string, priceAfter: string): "increase" | "decrease" | "nochange" {
    console.log(`[checkPriceChange] - Checking price change: before=${priceBefore}, after=${priceAfter}`);
    
    const before = new BigNumber(priceBefore || "0");
    const after = new BigNumber(priceAfter || "0");
    console.log(`[checkPriceChange] - BigNumber before: ${before.toString()}, after: ${after.toString()}`);

    // Guard: avoid division by zero
    if (before.isZero()) {
      console.log(`[checkPriceChange] - Before price is zero, returning nochange`);
      return "nochange";
    }

    // Percentage change = (after - before) / before * 100
    const diffPercent = after.minus(before).dividedBy(before).multipliedBy(100);
    console.log(`[checkPriceChange] - Percentage change: ${diffPercent.toString()}%`);

    if (diffPercent.gte(5)) {
      console.log(`[checkPriceChange] - Price increased by ${diffPercent.toString()}% (>= 5%), returning increase`);
      return "increase";
    } else if (diffPercent.lte(-5)) {
      console.log(`[checkPriceChange] - Price decreased by ${Math.abs(diffPercent.toNumber())}% (<= -5%), returning decrease`);
      return "decrease";
    } else {
      console.log(`[checkPriceChange] - Price change ${diffPercent.toString()}% is within range (-5% to 5%), returning nochange`);
      return "nochange";
    }
  }

  private getNotiMsg(symbol: string, language: string, direction?: "increase" | "decrease") {
    console.log(`[getNotiMsg] - Getting notification message for symbol: ${symbol}, language: ${language}, direction: ${direction}`);
    
    const messages = {
      vi: `Giá ${symbol} biến động mạnh trong 4 giờ qua. Xem ngay!`,
      en: `The price of ${symbol} has fluctuated strongly in the past 4 hours. Check now!`,
      ko: `${symbol}의 가격이 지난 4시간 동안 크게 변동했습니다. 지금 확인하세요!`,
      de: `Der Preis von ${symbol} hat sich in den letzten 4 Stunden stark verändert. Jetzt ansehen!`,
      ja: `${symbol}の価格は過去4時間で大きく変動しました。今すぐ確認！`,
      id: `Harga ${symbol} telah berfluktuasi tajam dalam 4 jam terakhir. Periksa sekarang!`,
    };

    const selectedLanguage = language || "en";
    const message = messages[selectedLanguage] || messages["en"];
    console.log(`[getNotiMsg] - Selected language: ${selectedLanguage}, Message: ${message}`);
    
    return message;
  }
}
