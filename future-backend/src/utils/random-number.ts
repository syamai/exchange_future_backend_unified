export const getRandomInt = (min: number = 1500, max: number = 5000): number => {
  return Math.floor(Math.random() * (max - min + 1)) + min;
};

export const getRandomDecimal = (min: number = 0.0002, max: number = 0.001): number => {
  return Math.random() * (max - min) + min;
};
