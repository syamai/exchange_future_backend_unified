import { Injectable } from "@nestjs/common";
import IORedis from "ioredis";
import { RedisService } from "nestjs-redis";

@Injectable()
export class RedisClient {
  private static instance: IORedis.Redis;

  constructor(
    private readonly redisService: RedisService,
  ) {}

  public getInstance(): IORedis.Redis {
    if (!RedisClient.instance) {
      RedisClient.instance = this.redisService.getClient(); 
    }
    return RedisClient.instance;
  }
}
