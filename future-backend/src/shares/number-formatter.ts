import BigNumber from "bignumber.js";
import { InstrumentEntity } from "src/models/entities/instrument.entity";

const formatNumber = (
  number: string | undefined,
  precision: number,
  zeroValue: string
): string => {
  if (
    number === undefined ||
    number === null ||
    number === "" ||
    Number.isNaN(Number(number))
  ) {
    return zeroValue;
  }
  return Number(number)
    .toFixed(precision)
    .replace(/\d(?=(\d{3})+\.)/g, "$&,");
};

export function formatPrice(
  number: string | undefined,
  instrument: InstrumentEntity | undefined,
  zeroValue = "-"
): string {
  const tickSize = instrument?.tickSize;
  const precision = -Math.ceil(Math.log10(Number(tickSize)));
  return formatNumber(number, precision, zeroValue);
}

export function formatQuantity(
  number: string | undefined,
  instrument: InstrumentEntity | undefined,
  zeroValue = "-"
): string {
  const contractSize = instrument?.contractSize;
  const lotSize = instrument?.lotSize;
  const minimumQuantity = Number(contractSize) * Number(lotSize);
  const precision = -Math.ceil(Math.log10(Number(minimumQuantity)));
  return formatNumber(number, precision, zeroValue);
}

export function formatOrderEnum(value: string | undefined): string {
  if (!value) {
    return "";
  }
  const parts = value.toLowerCase().split("_");
  return parts
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function getValueClassName(
  value: string | undefined,
  positiveClass = "App-positive-value",
  neutralClass = "",
  negativeClass = "App-negative-value"
): string {
  if (value === undefined || value === null) {
    return neutralClass;
  }

  const number = parseFloat(value);
  if (number > 0) {
    return positiveClass;
  } else if (number === 0) {
    return neutralClass;
  } else {
    return negativeClass;
  }
}

export const formatUSDAmount = (amount: string | undefined): string => {
  return formatNumber(amount, 6, "");
};

export const isNumber = (str: string): boolean => {
  return !new BigNumber(str).isNaN();
};

export const formatPercent = (
  percent: string | number,
  precision = 4
): string => {
  return `${formatNumber(
    `${percent}`,
    precision,
    `0.${"0".repeat(precision)}`
  )}%`;
};

// format number 8 decimal
export const formatNumberDecimal = (number: string | number): string => {
  return new BigNumber(number).toFormat(8, 1);
};

export const OPERATION_ID_DIVISOR = BigInt(1000000000000000); // For converting operationId from BigInt to Number because of stupid js

