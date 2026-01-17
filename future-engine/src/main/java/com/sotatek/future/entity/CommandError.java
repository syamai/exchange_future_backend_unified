package com.sotatek.future.entity;

import lombok.Builder;
import lombok.With;

@Builder
@With
public class CommandError {
  private final long userId;
  private final long accountId;
  private final String code;
  private final String messages;
}
