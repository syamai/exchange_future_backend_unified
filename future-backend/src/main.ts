import * as dotenv from "dotenv";
dotenv.config();

import { Logger } from "@nestjs/common";
import { NestFactory } from "@nestjs/core";
import { DocumentBuilder, SwaggerModule } from "@nestjs/swagger";
import * as Sentry from "@sentry/node";
import * as config from "config";
import * as helmet from "helmet";
import { RedisIoAdapter } from "src/adapters/redis.adapter";
import { AppModules } from "src/app.module";
import { HttpExceptionFilter } from "src/shares/filters/http-exception.filter";
import { ResponseTransformInterceptor } from "src/shares/interceptors/response.interceptor";
import { SentryInterceptor } from "src/shares/interceptors/sentry.interceptor";
import { BodyValidationPipe } from "src/shares/pipes/body.validation.pipe";
import { NestExpressApplication } from "@nestjs/platform-express";

const appPort = config.get<number>("app.port");
const prefix = config.get<string>("app.prefix");
import * as cluster from "cluster";
import * as os from "os";
// Enable cluster mode for high throughput - use all available CPU cores
const numCPUs = os.cpus().length;

if (cluster.isMaster) {
  console.log(`Master process ${process.pid} is running`);

  // Fork workers
  for (let i = 0; i < numCPUs; i++) {
    cluster.fork();
  }

  // Restart a worker if it crashes
  cluster.on("exit", (worker, code, signal) => {
    console.log(`Worker ${worker.process.pid} died. Restarting...`);
    cluster.fork();
  });
} else {
  async function bootstrap(): Promise<void> {
    const app = await NestFactory.create<NestExpressApplication>(AppModules, {
      cors: true,
    });

    app.setGlobalPrefix(prefix);

    app.use((req, res, next) => {
      if (req.originalUrl === "/metric") {
        req.url = `/${prefix}/metric`;
      }
      next();
    });

    app.enableCors();
    app.useGlobalInterceptors(new SentryInterceptor());
    app.useGlobalInterceptors(new ResponseTransformInterceptor());
    app.useGlobalPipes(new BodyValidationPipe());
    app.useGlobalFilters(new HttpExceptionFilter());
    app.useWebSocketAdapter(new RedisIoAdapter(app));

    const appName = config.get<string>("app.name");
    const options = new DocumentBuilder()
      .addBearerAuth()
      .setTitle(appName)
      .setDescription(appName)
      .setVersion(prefix)
      .build();
    const document = SwaggerModule.createDocument(app, options);
    SwaggerModule.setup(`${prefix}/docs`, app, document, {
      customSiteTitle: appName,
      swaggerOptions: {
        docExpansion: "list",
        filter: true,
        displayRequestDuration: true,
      },
    });

    app.use(helmet());

    await app.listen(appPort);

    const logger = app.get(Logger);
    logger.setContext("NestApplication");
    logger.log(`Application is running on: ${await app.getUrl()}`);
  }

  bootstrap();
}
