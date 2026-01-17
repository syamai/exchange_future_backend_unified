import { HttpException, HttpStatus, Injectable } from "@nestjs/common";
import { UpdateCheckingStatusPhbsDto } from "../../dto/admin/update-checking-status-phbs.admin.dto";
import { InjectRepository } from "@nestjs/typeorm";
import { PositionHistoryBySessionEntity } from "src/models/entities/position_history_by_session.entity";
import { PositionHistoryBySessionRepository } from "src/models/repositories/position-history-by-session.repository";
import { httpErrors } from "src/shares/exceptions";

@Injectable()
export class UpdateCheckingStatusPhbsUseCase {
  constructor(
    @InjectRepository(PositionHistoryBySessionRepository, "report")
    private readonly positionHistoryBySessionRepoReport: PositionHistoryBySessionRepository,
    @InjectRepository(PositionHistoryBySessionRepository, "master")
    private readonly positionHistoryBySessionRepoMaster: PositionHistoryBySessionRepository
  ) {}

  public async execute(
    positionHistoryBySessionId: number,
    body: UpdateCheckingStatusPhbsDto
  ) {
    const existed = await this.positionHistoryBySessionRepoReport
      .createQueryBuilder("phbs")
      .where("phbs.id = :id", { id: positionHistoryBySessionId })
      .select(["phbs.id"])
      .getOne();
    if (!existed) {
      throw new HttpException(
        httpErrors.POSITION_HISTORY_NOT_FOUND,
        HttpStatus.NOT_FOUND
      );
    }

    await this.positionHistoryBySessionRepoMaster.update(
      positionHistoryBySessionId,
      { checkingStatus: body.status.toString() }
    );
    return true;
  }
}
