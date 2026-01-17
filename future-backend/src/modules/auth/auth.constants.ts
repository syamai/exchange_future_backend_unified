import * as config from "config";

export const jwtConstants = {
  accessTokenSecret: config.get<string>("app.jwt_access_token_secret"),
  accessTokenExpiry: parseInt(
    config.get<string>("app.jwt_access_token_expiration_time")
  ),
  refreshTokenExpiry: parseInt(
    config.get<string>("app.jwt_refresh_token_expiration_time")
  ),
};

export const AUTH_CACHE_PREFIX = "AUTH_CACHE_PREFIX_";

export enum SotaDexHeader {
  ADDRESS = "sotadex-address",
  SIGNATURE = "sotadex-signature",
  APIKEY = "sotadex-api-key",
  TIMESTAMP = "sotadex-timestamp",
}

export const API_KEY_PERMISSION = {
  ID: "4",
  NAME: "FUTURE_TRADING",
};

export const API_METHOD = ["POST", "PUT", "DELETE"];
export const EXPIRED = 60;
