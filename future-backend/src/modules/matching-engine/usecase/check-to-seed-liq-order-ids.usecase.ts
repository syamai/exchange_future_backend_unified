import { Injectable, Logger } from "@nestjs/common";
import { CommandCode, CommandOutput } from "../matching-engine.const";
import { KafkaTopics } from "src/shares/enums/kafka.enum";
import { OrderService } from "src/modules/order/order.service";
import { Producer } from "kafkajs";

@Injectable()
export class CheckToSeedLiquidationOrderIdsUseCase {
  constructor(private readonly orderService: OrderService) {}
  private readonly logger = new Logger(
    CheckToSeedLiquidationOrderIdsUseCase.name
  );

  private lastSent: Date = null;

  public async execute(
    producer: Producer,
    commands: CommandOutput[]
  ): Promise<void> {
    const shouldSeed = commands.some(
      (cmd) =>
        cmd.shouldSeedLiquidationOrderId != null &&
        cmd.shouldSeedLiquidationOrderId === true
    );
    if (!shouldSeed) return;

    const now = new Date();
    if (this.lastSent && now.getTime() - this.lastSent.getTime() < 60 * 1000) {
      this.logger.log(
        "Seed liquidation order IDs: Skipped (less than 1 minute since last send)"
      );
      return;
    }
    this.lastSent = now;
    
    const liquidationOrderIds = await this.orderService.getLiquidationOrderIds(200);
    const command = {
      code: CommandCode.SEED_LIQUIDATION_ORDER_ID,
      data: {
        liquidationOrderIds,
      },
    };
    await producer.send({
      topic: KafkaTopics.matching_engine_input,
      messages: [{ value: JSON.stringify(command) }],
    });
    this.logger.log(`Seed lidOrderIds: ${liquidationOrderIds}`);
  }
}
