package com.sotatek.future.util.json;

import com.google.gson.ExclusionStrategy;
import com.google.gson.FieldAttributes;
import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.JsonDeserializationContext;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParseException;
import com.google.gson.JsonPrimitive;
import com.google.gson.JsonSerializer;
import com.sotatek.future.entity.Command;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.util.MarginBigDecimal;
import java.lang.reflect.Type;
import java.time.Instant;
import java.util.Date;

public class JsonUtil {

  private static final GsonBuilder gsonBuilder = new GsonBuilder();
  private static final GsonBuilder internalGsonBuilder = new GsonBuilder();
  private static final Gson internalGson;
  private static final JsonSerializer<MarginBigDecimal> marginBigDecimalSerializer =
      (marginBigDecimal, type, jsonSerializationContext) ->
          new JsonPrimitive(marginBigDecimal.normalize().toString());

  private static final JsonDeserializer<MarginBigDecimal> marginBigDecimalDeserializer =
      (json, typeOfT, context) -> new MarginBigDecimal(json.getAsString());
  private static final JsonSerializer<Date> dateSerializer =
      (date, type, jsonSerializationContext) -> new JsonPrimitive(date.getTime());
  private static final JsonDeserializer<Date> dateDeserializer =
      (json, typeOfT, context) -> customOptionDateDeserializer(json);
  private static final JsonDeserializer<Command> commandDeserializer =
      new JsonDeserializer<>() {
        @Override
        public Command deserialize(
            JsonElement json, Type typeOfT, JsonDeserializationContext context)
            throws JsonParseException {
          JsonObject jsonObject = json.getAsJsonObject();
          String code = jsonObject.get("code").getAsString();

          CommandCode commandCode = CommandCode.valueOf(code);
          if (jsonObject.get("data") != null) {
            Object data = internalGson.fromJson(jsonObject.get("data"), commandCode.getDataClass());
            return new Command(commandCode, data);
          } else {
            return new Command(commandCode, null);
          }
        }
      };
  private static final ExclusionStrategy excludeStrategy =
      new ExclusionStrategy() {
        @Override
        public boolean shouldSkipClass(Class<?> clazz) {
          return false;
        }

        @Override
        public boolean shouldSkipField(FieldAttributes field) {
          return field.getAnnotation(Exclude.class) != null;
        }
      };

  static {
    internalGsonBuilder.registerTypeAdapter(MarginBigDecimal.class, marginBigDecimalSerializer);
    internalGsonBuilder.registerTypeAdapter(MarginBigDecimal.class, marginBigDecimalDeserializer);
    internalGsonBuilder.registerTypeAdapter(Date.class, dateSerializer);
    internalGsonBuilder.registerTypeAdapter(Date.class, dateDeserializer);
    internalGson = internalGsonBuilder.create();
  }

  static {
    gsonBuilder.registerTypeAdapter(MarginBigDecimal.class, marginBigDecimalSerializer);
    gsonBuilder.registerTypeAdapter(MarginBigDecimal.class, marginBigDecimalDeserializer);
    gsonBuilder.registerTypeAdapter(Date.class, dateSerializer);
    gsonBuilder.registerTypeAdapter(Date.class, dateDeserializer);
    gsonBuilder.registerTypeAdapter(Command.class, commandDeserializer);
    gsonBuilder.addSerializationExclusionStrategy(excludeStrategy);
  }

  private JsonUtil() {}

  public static Gson createGson() {
    return gsonBuilder.create();
  }

  private static Date customOptionDateDeserializer(JsonElement json) {
    try {
      return new Date(json.getAsLong());
    } catch (NumberFormatException nfe) {
      // do nothing and try to use ISO string format
      return Date.from(Instant.parse(json.getAsString()));
    }
  }
}
