import { getConfig } from "src/configs/index";

interface IEmailConfig {
  auth: {
    user: string;
    pass: string;
  };
  from: {
    address: string;
    name: string;
  };
  service: string;
  enable: boolean;
  port: string;
  host: string;
}

export const mailConfig: IEmailConfig = {
  auth: {
    user: getConfig().get<string>("mail.account"),
    pass: getConfig().get<string>("mail.password"),
  },
  from: {
    address: getConfig().get<string>("mail.from_address"),
    name: getConfig().get<string>("mail.from_name"),
  },
  service: getConfig().get<string>("mail.service"),
  enable: getConfig().get<string>("mail.enable") === "true",
  port: getConfig().get<string>("mail.port"),
  host: getConfig().get<string>("mail.domain"),
};
