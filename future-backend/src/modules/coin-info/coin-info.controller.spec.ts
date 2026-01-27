import { Test, TestingModule } from "@nestjs/testing";
import { CACHE_MANAGER } from "@nestjs/common";
import { getRepositoryToken } from "@nestjs/typeorm";
import { CoinInfoController } from "./coin-info.controller";
import { CoinInfoService } from "./coin-info.service";
import { CoinInfoRepository } from "../../models/repositories/coin-info.repository";

describe("CoinInfoController", () => {
  let controller: CoinInfoController;

  const mockCoinInfoRepository = {
    find: jest.fn(),
    findOne: jest.fn(),
    save: jest.fn(),
  };

  const mockCacheManager = {
    get: jest.fn(),
    set: jest.fn(),
    del: jest.fn(),
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [CoinInfoController],
      providers: [
        CoinInfoService,
        {
          provide: getRepositoryToken(CoinInfoRepository, "master"),
          useValue: mockCoinInfoRepository,
        },
        {
          provide: CACHE_MANAGER,
          useValue: mockCacheManager,
        },
      ],
    }).compile();

    controller = module.get<CoinInfoController>(CoinInfoController);
  });

  it("should be defined", () => {
    expect(controller).toBeDefined();
  });
});
