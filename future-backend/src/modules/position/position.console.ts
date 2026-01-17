import { Injectable } from "@nestjs/common";
import { Command, Console } from "nestjs-console";
import { PositionService } from "./position.service";

@Console()
@Injectable()
export class PositionConsole {
  constructor(private positionService: PositionService) {}

  @Command({
    command: "position:update-new-account",
    description: "update new account in position",
  })
  async updatePositions(): Promise<void> {
    await this.positionService.updatePositions();
  }

  @Command({
    command: "position:close-all [symbol]",
    description: "Close all positions",
  })
  async closeAllPositionCommand(symbol?: string): Promise<void> {
    await this.positionService.closeAllPositionCommand(symbol);
  }

  @Command({
    command: "position:update-id-position",
    description: "Update id positions",
  })
  async updateIdPositionCommand(): Promise<void> {
    await this.positionService.updateIdPositionCommand();
  }
}
