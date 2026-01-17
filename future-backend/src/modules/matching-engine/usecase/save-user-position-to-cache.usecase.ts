import { Injectable, Logger } from "@nestjs/common";
import { PositionEntity } from "src/models/entities/position.entity";
import { convertDateFields } from "../helper";
import { REDIS_COMMON_PREFIX } from "src/shares/redis-client/common-prefix";
import { RedisClient } from "src/shares/redis-client/redis-client";
import BigNumber from "bignumber.js";

@Injectable()
export class SaveUserPositionToCacheUseCase {
  constructor(private readonly redisClient: RedisClient) {}
  private readonly logger = new Logger(SaveUserPositionToCacheUseCase.name);
  private readonly recentOperationIdByPositionId: Map<number, [BigNumber, number]> = new Map(); // <positionId, [operationId, ttl]>

  public async execute(positionMessage: string): Promise<void> {
    const position = convertDateFields(
      new PositionEntity(),
      JSON.parse(positionMessage)
    );
    // Check if we have a recent operationId for this positionId
    const now = Date.now();
    if (position.operationId !== undefined && position.operationId !== null) {
      const currentOperationId = new BigNumber(position.operationId);
      const cacheEntry = this.recentOperationIdByPositionId.get(position.id);
      if (cacheEntry) {
        const [cachedOperationId, ttl] = cacheEntry;
        // If the cached operationId is greater than or equal, skip processing
        if (cachedOperationId.isGreaterThan(currentOperationId) && ttl > now) {
          this.logger.log(
            `Skip positionId=${position.id} operationId=${position.operationId} (cached operationId=${cachedOperationId.toString()})`
          );
          return;
        }
      }
      // Update cache with new operationId and set TTL to 5 minute from now
      this.recentOperationIdByPositionId.set(position.id, [currentOperationId, now + 5 * 60 * 1000]);
    }

    const redisKey = `${REDIS_COMMON_PREFIX.POSITIONS}:userId_${position.userId}:accountId_${position.accountId}:positionId_${position.id}`;
    await this.redisClient
      .getInstance()
      .set(redisKey, JSON.stringify(position), "EX", 86400);
    this.logger.log(
      `Save positionId=${position.id} operationId=${position.operationId}`
    );

    // Clean up expired entries in recentOperationIdByPositionId
    for (const [positionId, [, ttl]] of this.recentOperationIdByPositionId.entries()) {
      if (ttl <= now) {
        this.recentOperationIdByPositionId.delete(positionId);
      }
    }
  }
}
