import { FUNDING_INTERVAL } from "./matching-engine.const";

export function convertDateFields<T>(
  object: T,
  entity: Record<string, unknown>
): T {
  if (entity.createdAt) {
    entity.createdAt = new Date(entity.createdAt as number);
  } else {
    entity.createdAt = new Date();
  }
  entity.updatedAt = new Date();

  if(entity.lastOpenTime) {
    entity.lastOpenTime = new Date(entity.lastOpenTime as number);
  }

  return Object.assign(object, entity);
}

export function convertFundingHistoriesDateFields<T>(
  object: T,
  entity: Record<string, unknown>
): T {
  entity.time = new Date(entity.time as number);
  entity.fundingInterval = FUNDING_INTERVAL;
  return this.convertDateFields(object, entity);
}

export function convertDateFieldsForOrders<T>(
  object: T,
  entity: Record<string, unknown>
): T {
  if (entity.createdAt) {
    entity.createdAt = new Date(entity.createdAt as number);
  } else {
    entity.createdAt = new Date();
  }

  if (entity.updatedAt) {
    entity.updatedAt = new Date(entity.updatedAt as number);
  } else {
    entity.updatedAt = new Date();
  }

  entity.orderMargin = entity.orderMargin ? entity.orderMargin : "0";
  entity.cost = entity.cost ? entity.cost : "0";
  entity.originalCost = entity.originalCost ? entity.originalCost : "0";
  entity.originalOrderMargin = entity.originalOrderMargin
    ? entity.originalOrderMargin
    : "0";
  return Object.assign(object, entity);
}
