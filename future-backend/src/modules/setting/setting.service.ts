import { HttpException, HttpStatus, Injectable } from "@nestjs/common";
import { InjectRepository } from "@nestjs/typeorm";
import { SettingEntity } from "src/models/entities/setting.entity";
import { SettingRepository } from "src/models/repositories/setting.repository";
import { httpErrors } from "src/shares/exceptions";

@Injectable()
export class SettingService {
  constructor(
    @InjectRepository(SettingRepository, "report")
    public readonly settingRepoReport: SettingRepository,
    @InjectRepository(SettingRepository, "master")
    public readonly settingRepoMaster: SettingRepository
  ) {}

  async findAll(): Promise<SettingEntity[]> {
    const settings = await this.settingRepoReport.find();
    return settings;
  }

  async findBySettingKey(key: string): Promise<SettingEntity> {
    const setting = await this.settingRepoReport.findOne({
      where: {
        key: key,
      },
    });
    if (setting) return setting;
    else
      throw new HttpException(
        httpErrors.SETTING_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
  }

  async updateSettingByKey(key: string, value: string): Promise<SettingEntity> {
    const setting = await this.settingRepoReport.findOne({
      where: {
        key: key,
      },
    });
    if (setting) {
      setting.value = value;
      await this.settingRepoMaster.save(setting);
      return setting;
    } else {
      const newSetting = new SettingEntity();
      newSetting.key = key;
      newSetting.value = value;
      return newSetting;
    }
  }
}
