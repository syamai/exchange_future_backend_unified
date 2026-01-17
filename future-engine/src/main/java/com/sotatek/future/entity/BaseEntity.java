package com.sotatek.future.entity;

import java.io.Serializable;
import java.util.Date;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import lombok.ToString;

@Getter
@Setter
@NoArgsConstructor
@ToString
public abstract class BaseEntity implements Serializable {

  protected Long id;
  protected String operationId;
  protected Date createdAt;
  protected Date updatedAt;

  protected BaseEntity(BaseEntity e) {
    this.id = e.id;
    this.operationId = e.operationId;
    this.createdAt = e.createdAt;
    this.updatedAt = e.updatedAt;
  }

  public abstract <T> T getKey();

  public BaseEntity deepCopy() {
    throw new UnsupportedOperationException("Deep copy of " + getClass() + " is not supported");
  }

  public <T> T getValue() {
    throw new UnsupportedOperationException(
        "Getting value of entity type" + getClass() + " is not supported");
  }
}
