import * as config from "config";

const positionHistoryTime = Number(
  config.get<number>("matching.position_history_time")
);
const fundingHistoryTime = Number(
  config.get<number>("matching.funding_history_time")
);

export const MatchingEngineConfig = {
  positionHistoryTime,
  fundingHistoryTime,
};
