import * as config from "config";

export enum USER_ID_INSURANCE_ACCOUNT {
  DEFAULT = config.get<number>("insurance_account.user_id"),
}
