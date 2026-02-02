import { Test, TestingModule } from "@nestjs/testing";
import { getRepositoryToken } from "@nestjs/typeorm";
import BigNumber from "bignumber.js";
import { FutureEventV2Service } from "src/modules/future-event-v2/future-event-v2.service";
import { EventSettingV2Entity, EventStatusV2 } from "src/models/entities/event-setting-v2.entity";
import { UserBonusV2Entity, BonusStatusV2 } from "src/models/entities/user-bonus-v2.entity";
import { UserBonusV2HistoryEntity } from "src/models/entities/user-bonus-v2-history.entity";
import { EventSettingV2Repository } from "src/models/repositories/event-setting-v2.repository";
import { UserBonusV2Repository } from "src/models/repositories/user-bonus-v2.repository";
import { UserBonusV2HistoryRepository } from "src/models/repositories/user-bonus-v2-history.repository";
import { KafkaClient } from "src/shares/kafka-client/kafka-client";

describe("FutureEventV2Service", () => {
  let service: FutureEventV2Service;
  let eventSettingRepoMaster: jest.Mocked<EventSettingV2Repository>;
  let eventSettingRepoReport: jest.Mocked<EventSettingV2Repository>;
  let userBonusRepoMaster: jest.Mocked<UserBonusV2Repository>;
  let userBonusRepoReport: jest.Mocked<UserBonusV2Repository>;
  let userBonusHistoryRepoMaster: jest.Mocked<UserBonusV2HistoryRepository>;
  let userBonusHistoryRepoReport: jest.Mocked<UserBonusV2HistoryRepository>;
  let kafkaClient: jest.Mocked<KafkaClient>;

  const mockEventSetting: EventSettingV2Entity = {
    id: 1,
    eventName: "Test Bonus Event",
    eventCode: "TEST_BONUS_100",
    status: EventStatusV2.ACTIVE,
    bonusRatePercent: "100",
    minDepositAmount: "100",
    maxBonusAmount: "10000",
    startDate: new Date("2026-01-01"),
    endDate: new Date("2026-12-31"),
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  const mockUserBonus: UserBonusV2Entity = {
    id: 1,
    userId: 123,
    accountId: 456,
    eventSettingId: 1,
    transactionId: 789,
    bonusAmount: "1000",
    originalDeposit: "1000",
    currentPrincipal: "1000",
    status: BonusStatusV2.ACTIVE,
    grantedAt: new Date(),
    liquidatedAt: null,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  beforeEach(async () => {
    const mockRepository = () => ({
      find: jest.fn(),
      findOne: jest.fn(),
      save: jest.fn(),
      create: jest.fn(),
      createQueryBuilder: jest.fn(() => ({
        andWhere: jest.fn().mockReturnThis(),
        where: jest.fn().mockReturnThis(),
        orderBy: jest.fn().mockReturnThis(),
        skip: jest.fn().mockReturnThis(),
        take: jest.fn().mockReturnThis(),
        getMany: jest.fn(),
        getManyAndCount: jest.fn(),
        getOne: jest.fn(),
      })),
    });

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        FutureEventV2Service,
        {
          provide: getRepositoryToken(EventSettingV2Repository, "master"),
          useFactory: mockRepository,
        },
        {
          provide: getRepositoryToken(EventSettingV2Repository, "report"),
          useFactory: mockRepository,
        },
        {
          provide: getRepositoryToken(UserBonusV2Repository, "master"),
          useFactory: mockRepository,
        },
        {
          provide: getRepositoryToken(UserBonusV2Repository, "report"),
          useFactory: mockRepository,
        },
        {
          provide: getRepositoryToken(UserBonusV2HistoryRepository, "master"),
          useFactory: mockRepository,
        },
        {
          provide: getRepositoryToken(UserBonusV2HistoryRepository, "report"),
          useFactory: mockRepository,
        },
        {
          provide: KafkaClient,
          useValue: {
            send: jest.fn(),
            consume: jest.fn(),
          },
        },
      ],
    }).compile();

    service = module.get<FutureEventV2Service>(FutureEventV2Service);
    eventSettingRepoMaster = module.get(getRepositoryToken(EventSettingV2Repository, "master"));
    eventSettingRepoReport = module.get(getRepositoryToken(EventSettingV2Repository, "report"));
    userBonusRepoMaster = module.get(getRepositoryToken(UserBonusV2Repository, "master"));
    userBonusRepoReport = module.get(getRepositoryToken(UserBonusV2Repository, "report"));
    userBonusHistoryRepoMaster = module.get(getRepositoryToken(UserBonusV2HistoryRepository, "master"));
    userBonusHistoryRepoReport = module.get(getRepositoryToken(UserBonusV2HistoryRepository, "report"));
    kafkaClient = module.get(KafkaClient);
  });

  describe("calculateBonusAmount", () => {
    it("should calculate bonus amount correctly with 100% rate", () => {
      const depositAmount = "1000";
      const result = service.calculateBonusAmount(depositAmount, mockEventSetting);
      expect(result).toBe("1000");
    });

    it("should calculate bonus amount correctly with 50% rate", () => {
      const depositAmount = "1000";
      const eventSetting = { ...mockEventSetting, bonusRatePercent: "50" };
      const result = service.calculateBonusAmount(depositAmount, eventSetting);
      expect(result).toBe("500");
    });

    it("should cap bonus at maxBonusAmount", () => {
      const depositAmount = "20000";
      const eventSetting = { ...mockEventSetting, maxBonusAmount: "5000" };
      const result = service.calculateBonusAmount(depositAmount, eventSetting);
      expect(result).toBe("5000");
    });

    it("should not cap bonus when maxBonusAmount is 0", () => {
      const depositAmount = "5000";
      const eventSetting = { ...mockEventSetting, maxBonusAmount: "0" };
      const result = service.calculateBonusAmount(depositAmount, eventSetting);
      expect(result).toBe("5000");
    });
  });

  describe("checkDepositEligibility", () => {
    it("should return true when deposit meets minimum amount", async () => {
      const result = await service.checkDepositEligibility(123, "500", mockEventSetting);
      expect(result).toBe(true);
    });

    it("should return false when deposit is below minimum amount", async () => {
      const result = await service.checkDepositEligibility(123, "50", mockEventSetting);
      expect(result).toBe(false);
    });
  });

  describe("createEventSetting", () => {
    it("should create event setting with INACTIVE status by default", async () => {
      const dto = {
        eventName: "New Event",
        eventCode: "NEW_EVENT",
        bonusRatePercent: "100",
        startDate: "2026-01-01T00:00:00Z",
        endDate: "2026-12-31T23:59:59Z",
      };

      eventSettingRepoMaster.save.mockResolvedValue({
        ...mockEventSetting,
        eventName: dto.eventName,
        eventCode: dto.eventCode,
        bonusRatePercent: dto.bonusRatePercent,
        startDate: new Date(dto.startDate),
        endDate: new Date(dto.endDate),
        status: EventStatusV2.INACTIVE,
      });

      const result = await service.createEventSetting(dto);

      expect(result.status).toBe(EventStatusV2.INACTIVE);
      expect(eventSettingRepoMaster.save).toHaveBeenCalled();
    });
  });

  describe("toggleEventStatus", () => {
    it("should toggle status from INACTIVE to ACTIVE", async () => {
      const inactiveEvent = { ...mockEventSetting, status: EventStatusV2.INACTIVE };
      eventSettingRepoMaster.findOne.mockResolvedValue(inactiveEvent);
      eventSettingRepoMaster.save.mockResolvedValue({
        ...inactiveEvent,
        status: EventStatusV2.ACTIVE,
      });

      const result = await service.toggleEventStatus(1);

      expect(result.status).toBe(EventStatusV2.ACTIVE);
    });

    it("should toggle status from ACTIVE to INACTIVE", async () => {
      eventSettingRepoMaster.findOne.mockResolvedValue(mockEventSetting);
      eventSettingRepoMaster.save.mockResolvedValue({
        ...mockEventSetting,
        status: EventStatusV2.INACTIVE,
      });

      const result = await service.toggleEventStatus(1);

      expect(result.status).toBe(EventStatusV2.INACTIVE);
    });

    it("should throw error if event setting not found", async () => {
      eventSettingRepoMaster.findOne.mockResolvedValue(null);

      await expect(service.toggleEventStatus(999)).rejects.toThrow(
        "Event setting with id 999 not found"
      );
    });
  });

  describe("grantBonus", () => {
    it("should create bonus with calculated amount", async () => {
      const dto = {
        userId: 123,
        accountId: 456,
        eventSettingId: 1,
        depositAmount: "1000",
      };

      eventSettingRepoMaster.findOne.mockResolvedValue(mockEventSetting);
      userBonusRepoMaster.save.mockResolvedValue(mockUserBonus);
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      const result = await service.grantBonus(dto);

      expect(result.bonusAmount).toBe("1000");
      expect(result.originalDeposit).toBe("1000");
      expect(result.currentPrincipal).toBe("1000");
      expect(userBonusHistoryRepoMaster.save).toHaveBeenCalled();
    });

    it("should use provided bonusAmount if specified", async () => {
      const dto = {
        userId: 123,
        accountId: 456,
        eventSettingId: 1,
        depositAmount: "1000",
        bonusAmount: "500",
      };

      eventSettingRepoMaster.findOne.mockResolvedValue(mockEventSetting);
      userBonusRepoMaster.save.mockImplementation((entity) => Promise.resolve(entity as any));
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      const result = await service.grantBonus(dto);

      expect(result.bonusAmount).toBe("500");
    });
  });

  describe("deductFromPrincipal", () => {
    it("should deduct amount from currentPrincipal", async () => {
      const bonusWithPrincipal = { ...mockUserBonus, currentPrincipal: "1000" };
      userBonusRepoReport.findOne.mockResolvedValue(bonusWithPrincipal);
      userBonusRepoMaster.save.mockImplementation((entity) => Promise.resolve(entity as any));
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      await service.deductFromPrincipal(456, "200", "TRADING_FEE", "txn-uuid-123");

      expect(userBonusRepoMaster.save).toHaveBeenCalledWith(
        expect.objectContaining({
          currentPrincipal: "800",
        })
      );
    });

    it("should trigger liquidation when principal becomes zero or negative", async () => {
      const bonusWithLowPrincipal = { ...mockUserBonus, currentPrincipal: "100" };
      userBonusRepoReport.findOne.mockResolvedValue(bonusWithLowPrincipal);
      userBonusRepoMaster.findOne.mockResolvedValue(bonusWithLowPrincipal);
      userBonusRepoMaster.save.mockImplementation((entity) => Promise.resolve(entity as any));
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      await service.deductFromPrincipal(456, "150", "REALIZED_PNL", "txn-uuid-456");

      expect(kafkaClient.send).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          accountId: 456,
          reason: "PRINCIPAL_DEPLETED",
        })
      );
    });

    it("should do nothing if no active bonus for account", async () => {
      userBonusRepoReport.findOne.mockResolvedValue(null);

      await service.deductFromPrincipal(999, "100", "TRADING_FEE");

      expect(userBonusRepoMaster.save).not.toHaveBeenCalled();
    });
  });

  describe("handleLiquidation", () => {
    it("should set bonus status to LIQUIDATED and send Kafka message", async () => {
      userBonusRepoMaster.findOne.mockResolvedValue(mockUserBonus);
      userBonusRepoMaster.save.mockImplementation((entity) => Promise.resolve(entity as any));
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      await service.handleLiquidation(456);

      expect(userBonusRepoMaster.save).toHaveBeenCalledWith(
        expect.objectContaining({
          status: BonusStatusV2.LIQUIDATED,
          currentPrincipal: "0",
        })
      );
      expect(kafkaClient.send).toHaveBeenCalled();
    });

    it("should do nothing if no active bonus found", async () => {
      userBonusRepoMaster.findOne.mockResolvedValue(null);

      await service.handleLiquidation(999);

      expect(kafkaClient.send).not.toHaveBeenCalled();
    });
  });

  describe("revokeBonus", () => {
    it("should set bonus status to REVOKED", async () => {
      userBonusRepoMaster.findOne.mockResolvedValue(mockUserBonus);
      userBonusRepoMaster.save.mockImplementation((entity) => Promise.resolve(entity as any));
      userBonusHistoryRepoMaster.save.mockResolvedValue({} as UserBonusV2HistoryEntity);

      await service.revokeBonus(1, "Admin decision");

      expect(userBonusRepoMaster.save).toHaveBeenCalledWith(
        expect.objectContaining({
          status: BonusStatusV2.REVOKED,
          currentPrincipal: "0",
        })
      );
    });

    it("should throw error if bonus not found", async () => {
      userBonusRepoMaster.findOne.mockResolvedValue(null);

      await expect(service.revokeBonus(999, "Test")).rejects.toThrow(
        "Bonus with id 999 not found"
      );
    });
  });
});
