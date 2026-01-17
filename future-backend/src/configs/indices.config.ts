import * as config from "config";

const interval = Number(config.get<number>("indices.interval"));

export const IndicesConfig = {
  interval,
};
