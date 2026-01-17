import { forwardRef, Module } from "@nestjs/common";
import {
  AcceptLanguageResolver,
  CookieResolver,
  I18nJsonParser,
  I18nModule,
  QueryResolver,
} from "nestjs-i18n";
import { UsersModule } from "../user/users.module";

import { TranslateService } from "./translate.service";

@Module({
  imports: [
    I18nModule.forRoot({
      fallbackLanguage: "en",
      parser: I18nJsonParser,
      parserOptions: {
        path: "dist/i18n/",
      },
      resolvers: [
        {
          use: QueryResolver,
          options: ["lang", "locale", "l"],
        },
        AcceptLanguageResolver,
        new CookieResolver(["lang", "locale", "l"]),
      ],
    }),
    forwardRef(() => UsersModule),
  ],
  providers: [TranslateService],
  exports: [TranslateService],
})
export class TranslateModule {}
