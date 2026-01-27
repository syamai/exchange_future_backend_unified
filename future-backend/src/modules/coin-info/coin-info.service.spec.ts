import { Test, TestingModule } from "@nestjs/testing";
import { CACHE_MANAGER } from "@nestjs/common";
import { getRepositoryToken } from "@nestjs/typeorm";
import { CoinInfoService } from "./coin-info.service";
import { CoinInfoRepository } from "../../models/repositories/coin-info.repository";

describe("CoinInfoService", () => {
  let service: CoinInfoService;

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

    service = module.get<CoinInfoService>(CoinInfoService);
  });

  it("should be defined", () => {
    expect(service).toBeDefined();
  });
});
