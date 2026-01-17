package com.sotatek.future.service;

import com.sotatek.future.entity.BaseEntity;
import java.util.Date;
import java.util.HashMap;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.Queue;
import java.util.stream.Collectors;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.tuple.Pair;

@Slf4j
public class BaseService<T extends BaseEntity> {

  protected long currentId;
  protected boolean needToCloneEntity;
  protected long expiryTime = 900000; // 15m
  // This Map is whole all mapping entities state from DB to Matching Engine
  // It always keeps the same with state of entities on DB
  protected Map<Object, T> entities = new HashMap<>();
  protected Map<Object, T> temporaryEntities = new HashMap<>();
  // This Map is whole all processing entities which handling on Matching Engine at that time
  // The idea is we can change state of entity from original then we can roll back ( delete from
  // processing entity list ) if there is an error
  // or commit ( save to entities list above ) if all are done
  protected Map<Object, T> processingEntities = new HashMap<>();
  protected Queue<Pair<T, Long>> removingEntities = new LinkedList<>();

  public BaseService(boolean needToCloneEntity) {
    this.needToCloneEntity = needToCloneEntity;
  }

  public void setCurrentId(long id) {
    currentId = id;
  }

  protected long getNextId() {
    if (currentId <= 0) {
      throw new RuntimeException("Uninitialized service, current id: " + currentId);
    }
    return currentId++;
  }

  protected void assignNewId(T entity) {
    entity.setId(getNextId());
  }

  /**
   * Get object by key from list processing/temporary/entities
   *
   * @param key
   * @return
   */
  public T get(Object key) {
    T entity = this.processingEntities.get(key);
    if (entity == null) {
      entity = this.temporaryEntities.get(key);
    }
    if (entity == null) {
      entity = this.entities.get(key);
    }
    if (entity == null) {
      return null;
    } else {
      return this.cloneEntityIfNeeded(entity);
    }
  }

  /**
   * Put entity which is modifying to processing list
   *
   * @param entity
   * @return
   */
  public T update(T entity) {
    entity.setUpdatedAt(new Date());
    this.processingEntities.put(entity.getKey(), entity);
    return entity;
  }

  /**
   * Assign new ID and then put entity to processing list
   *
   * @param entity new entity to insert
   * @return
   */
  public T insert(T entity) {
    assignNewId(entity);
    Date date = new Date();
    entity.setCreatedAt(date);
    update(entity);
    return entity;
  }

  /** clear all processing and temporary entities when roll-back */
  public void rollback() {
    temporaryEntities.clear();
    processingEntities.clear();
  }

  /**
   * This method for roll back when at processing has error Then we clear processing entities and
   * get back from temporary entities
   */
  public void rollbackTemporary() {
    processingEntities.clear();
    processingEntities.putAll(temporaryEntities);
    temporaryEntities.clear();
  }

  /**
   * Put all entity form temporary/processing to entities list then clear temporary/processing list
   */
  public void commit() {
    entities.putAll(processingEntities);
    temporaryEntities.clear();
    processingEntities.clear();
  }

  public void commitTemporarily() {
    // clone current processing entity to temporary entity
    getProcessingEntities().forEach(e -> temporaryEntities.put(e.getKey(), e));
  }

  public List<T> getProcessingEntities() {
    return processingEntities.values().stream()
        .map(this::cloneEntityIfNeeded)
        .collect(Collectors.toList());
  }

  public List<T> getEntities() {
    return entities.values().stream().map(this::cloneEntityIfNeeded).collect(Collectors.toList());
  }

  public List<T> getCurrentEntities() {
    Map<Object, T> currentEntities = new HashMap<>(entities);
    currentEntities.putAll(processingEntities);
    return currentEntities.values().stream()
        .map(this::cloneEntityIfNeeded)
        .collect(Collectors.toList());
  }

  public void clear() {
    entities.clear();
    temporaryEntities.clear();
    processingEntities.clear();
  }

  public void cleanOldEntities() {
    if (removingEntities.size() == 0) {
      return;
    }
    Pair<T, Long> oldEntity = removingEntities.peek();
    while (oldEntity != null && oldEntity.getRight() < System.currentTimeMillis()) {
      entities.remove(oldEntity.getLeft().getKey());
      removingEntities.remove();
      oldEntity = removingEntities.peek();
    }
  }

  public void removeOldEntity(T t) {
    removingEntities.add(Pair.of(t, System.currentTimeMillis() + expiryTime));
  }

  protected T cloneEntityIfNeeded(T t) {
    return needToCloneEntity ? (T) t.deepCopy() : t;
  }
}
