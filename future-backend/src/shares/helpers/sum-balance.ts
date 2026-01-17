import BigNumber from "bignumber.js";

export const sumBalance = (...args: any[]): string => {
  let sum = "0";
  for (const arg of args) {
    sum = new BigNumber(arg).plus(sum).toString();
  }
  return sum;
};
