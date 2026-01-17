import { Controller, Get, Inject } from "@nestjs/common";
import { Gauge, Histogram, Registry } from "prom-client";
import { MetricsService } from "./metrics.service";

@Controller("metric")
export class MetricsController {
  private myGauge1: Gauge<string>;
  private myGauge2: Gauge<string>;
  private httpReq: Histogram<string>;
  constructor(
    @Inject("PROM_REGISTRY")
    private registry: Registry,
    private metricService: MetricsService
  ) {}

  @Get()
  async getMetrics(): Promise<string> {
    this.registry.clear();
    const data = await this.metricService.healthcheckService();
    const queue = await this.metricService.healcheckRedis();

    this.myGauge1 = new Gauge({
      name: "future_healthcheck",
      help: "Check healthcheck service from furure",
      labelNames: ["name"],
      registers: [this.registry],
    });
    this.myGauge2 = new Gauge({
      name: "future_count_process_queue",
      help: "Count queue from furure",
      labelNames: ["name"],
      registers: [this.registry],
    });

    this.myGauge1.set(
      { name: "get_funding" },
      data.healthcheck_get_funding ? 1 : 0
    );
    this.myGauge1.set(
      { name: "pay_funding" },
      data.healthcheck_pay_funding ? 1 : 0
    );
    this.myGauge1.set(
      { name: "index_price" },
      data.healthcheck_index_price ? 1 : 0
    );
    this.myGauge1.set(
      { name: "coin_info" },
      data.healthcheck_coin_info ? 1 : 0
    );
    this.myGauge1.set(
      { name: "sync_candle" },
      data.healthcheck_sync_candle ? 1 : 0
    );
    this.myGauge1.set({ name: "process_queue" }, queue <= 500 ? 1 : 0);
    this.myGauge2.set({ name: "total_queue" }, queue);

    this.registry.registerMetric(this.myGauge1);

    // collectDefaultMetrics({ register: this.registry });

    const result = this.registry.metrics();

    return result;
  }
}
