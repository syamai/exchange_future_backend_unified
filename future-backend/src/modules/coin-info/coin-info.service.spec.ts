import { Test, TestingModule } from "@nestjs/testing";
import { CoinInfoService } from "./coin-info.service";

describe("CoinInfoService", () => {
  let service: CoinInfoService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [CoinInfoService],
    }).compile();

    service = module.get<CoinInfoService>(CoinInfoService);
  });

  it("should be defined", () => {
    expect(service).toBeDefined();
  });
});
