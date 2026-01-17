import {
  ValidationOptions,
  registerDecorator,
  ValidationArguments,
} from "class-validator";

export function IsNotHaveSpace(
  property?: string,
  validationOptions?: ValidationOptions
) {
  return function (object: any, propertyName: string) {
    registerDecorator({
      name: "IsNotHaveSpace",
      target: object.constructor,
      propertyName: propertyName,
      constraints: [property],
      options: validationOptions,
      validator: {
        validate(value: string) {
          return !value.includes(" ");
        },
        defaultMessage(args: ValidationArguments) {
          return args.property + " must not have space";
        },
      },
    });
  };
}
