import {
  createParamDecorator,
  ExecutionContext,
} from "@nestjs/common";

export const IsTestingRequest = createParamDecorator(
  (data: string, ctx: ExecutionContext) => {
    const request = ctx.switchToHttp().getRequest();
    let isTesting = false;
    if (request.headers?.testing) isTesting = (request.headers?.testing as string) === 'true'; 
    return isTesting;
  }
);
