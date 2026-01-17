export const removeEmptyField = (entity: any): void => {
  if (entity) {
    Object.keys(entity).forEach((key) => {
      if (entity[key] === "") {
        delete entity[key];
      }
    });
  }
};
