import { getConfig } from "src/configs/index";
import { registerAs } from "@nestjs/config";

export default registerAs("redis", () => ({
  host: "localhost",
  port: 6380,
}));

export const redisConfig = {
  host: getConfig().get<string>("redis.host"),
  port: getConfig().get<number>("redis.port"),
};
console.log(redisConfig);
