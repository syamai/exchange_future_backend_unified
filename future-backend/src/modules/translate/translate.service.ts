import { Injectable } from "@nestjs/common";
import { I18nService } from "nestjs-i18n";
import { UserEntity } from "src/models/entities/user.entity";

@Injectable()
export class TranslateService {
  constructor(private readonly i18n: I18nService) {}

  async translate(
    user: UserEntity,
    key: string,
    args?: ({ [k: string]: any } | string)[] | { [k: string]: any }
  ) {
    let lang = "";
    switch (user.location) {
      case "vi": {
        lang = "vi";
        break;
      }
      case "ko": {
        lang = "kr";
        break;
      }
      default:
        lang = "en";
        break;
    }

    return await this.i18n.translate(`translate.${key}`, {
      lang,
      args,
    });
  }
}
