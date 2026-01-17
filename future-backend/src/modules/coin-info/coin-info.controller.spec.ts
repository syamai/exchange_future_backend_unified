import { Test, TestingModule } from "@nestjs/testing";
import { CoinInfoController } from "./coin-info.controller";

describe("CoinInfoController", () => {
  let controller: CoinInfoController;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [CoinInfoController],
    }).compile();

    controller = module.get<CoinInfoController>(CoinInfoController);
  });

  it("should be defined", () => {
    expect(controller).toBeDefined();
  });
});
