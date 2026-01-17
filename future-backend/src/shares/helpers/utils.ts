// eslint-disable-next-line
const Web3 = require("web3");

export const sleep = (time: number): Promise<void> =>
  new Promise((resolve) => setTimeout(resolve, time));

export const emptyWeb3 = new Web3();

export const checkRecoverSameAddress = async ({
  address,
  signature,
  message,
}: {
  address: string;
  signature: string;
  message: string;
}): Promise<boolean> => {
  const recover = await emptyWeb3.eth.accounts.recover(message, signature);
  const recoverConvert = Web3.utils.toChecksumAddress(recover);
  const addressConvert = Web3.utils.toChecksumAddress(address);
  return addressConvert === recoverConvert;
};

export const getRandomDeviateNumber = (
  sourceNumber: number,
  fromDeviation: number,
  toDeviation: number
) => {
  const array = [1, -1];
  const randomIndex = Math.floor(Math.random() * array.length);
  const positiveOrNagative = array[randomIndex];

  const deviateNumber =
    fromDeviation + Math.random() * (toDeviation - fromDeviation);
  const randomDeviateNumber =
    sourceNumber + (sourceNumber * positiveOrNagative * deviateNumber) / 100;

  return randomDeviateNumber;
};
