import { HttpException, HttpStatus } from "@nestjs/common";

export class ShardUnavailableException extends HttpException {
  constructor(shardId: string) {
    super(
      `Shard is not available: ${shardId}`,
      HttpStatus.SERVICE_UNAVAILABLE
    );
  }
}

export class SymbolPausedException extends HttpException {
  constructor(symbol: string) {
    super(
      `Symbol is paused for rebalancing: ${symbol}`,
      HttpStatus.SERVICE_UNAVAILABLE
    );
  }
}

export class UnknownShardException extends HttpException {
  constructor(shardId: string) {
    super(`Unknown shard: ${shardId}`, HttpStatus.BAD_REQUEST);
  }
}
