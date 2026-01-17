import { Injectable, Logger, NestMiddleware } from "@nestjs/common";
import { Request, Response } from "express";
import * as morgan from "morgan";
import { getConfig } from "src/configs";
import { InjectRepository } from "@nestjs/typeorm";
import { UserRepository } from "src/models/repositories/user.repository";
import { Like } from "typeorm";
import * as jwt from 'jsonwebtoken';

const env = getConfig().get<string>("app.node_env");

@Injectable()
export class LoggerMiddleware implements NestMiddleware {
  private readonly botIds: number[] = [];

  constructor(
    private readonly logger: Logger,
    @InjectRepository(UserRepository, "report")
    public readonly userRepoReport: UserRepository
  ) {
    this.logger.setContext("LoggerMiddleware");
    this.loadBotData();
  }

  private loadBotData = async () => {
    const botUsers = await this.userRepoReport.find({
      where: {
        email: Like("%bot%"),
      },
    });
    this.botIds.push(...botUsers.map((user) => user.id));
  };

  private isBotReq(req: Request): boolean {
    const token = req.headers["authorization"]?.split(" ")[1];
    if (!token) {
      return false;
    }
    const payload = jwt.decode(token);
    const userId = payload?.sub;

    return this.botIds.includes(userId);
  }

  // eslint-disable-next-line
  use(req: Request, res: Response, next: (err?: Error) => void) {
    return morgan(
      (_tokens, req: Request, res: Response): string => {
        if (this.isBotReq(req)) {
          return "";
        }

        const logInfo = `${req.method} ${req.url} - statusCode: ${res.statusCode}, statusMessage: ${res.statusMessage}`;
        return logInfo;
      },
      {
        stream: {
          write: (_logInfo) => {
            const logInfo = _logInfo.trim();
            if (logInfo && env != "develop") {
              console.log(logInfo);
            }
          },
        },
      }
    )(req, res, next);
  }
}
