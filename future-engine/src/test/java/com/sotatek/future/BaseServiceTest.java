package com.sotatek.future;

import com.sotatek.future.entity.BaseEntity;
import com.sotatek.future.service.BaseService;
import com.sotatek.future.util.TimeUtil;
import java.util.Arrays;
import java.util.Objects;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;

public class BaseServiceTest {

  @Test
  void test1() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commit();

    Assertions.assertEquals(Arrays.asList(entity), service.getEntities());
  }

  @Test
  void test2() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.rollback();

    Assertions.assertEquals(Arrays.asList(), service.getEntities());
  }

  @Test
  void test3() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);

    Assertions.assertEquals(Arrays.asList(), service.getEntities());
    Assertions.assertEquals(Arrays.asList(new TestEntity(entity)), service.getProcessingEntities());
    Assertions.assertEquals(Arrays.asList(new TestEntity(entity)), service.getCurrentEntities());
  }

  @Test
  void test4() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commitTemporarily();

    Assertions.assertEquals(Arrays.asList(), service.getEntities());
    // commitTemporarily will create a copy in currentEntity,
    // so the old entity will remain in processing entities
    Assertions.assertEquals(Arrays.asList(entity), service.getProcessingEntities());
    Assertions.assertEquals(Arrays.asList(new TestEntity(entity)), service.getCurrentEntities());
  }

  @Test
  void test5() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commitTemporarily();
    service.rollback();

    Assertions.assertEquals(Arrays.asList(), service.getEntities());
    Assertions.assertEquals(Arrays.asList(), service.getProcessingEntities());
    Assertions.assertEquals(Arrays.asList(), service.getCurrentEntities());
  }

  @Test
  void test6() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commitTemporarily();
    service.commit();

    Assertions.assertEquals(Arrays.asList(new TestEntity("Entity1")), service.getEntities());
    Assertions.assertEquals(Arrays.asList(), service.getProcessingEntities());
    Assertions.assertEquals(Arrays.asList(new TestEntity("Entity1")), service.getCurrentEntities());
  }

  @Test
  void test7() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commit();

    entity = service.get(1L);
    entity.setData("Entity1b");
    service.update(entity);
    service.commitTemporarily();
    service.commit();

    entity = service.get(1L);
    entity.setData("Entity1c");
    service.update(entity);
    service.commit();

    TestEntity entity2 = new TestEntity("Entity2");
    service.insert(entity2);
    service.commit();

    TestEntity savedEntity = service.get(1L);
    Assertions.assertEquals(savedEntity.getData(), "Entity1c");
  }

  @Test
  void test8() {
    TestService service = new TestService();
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commit();

    service.removeOldEntity(entity);
    TimeUtil.sleep(100);
    service.cleanOldEntities();

    TestEntity savedEntity = service.get(1L);
    Assertions.assertEquals(savedEntity.getData(), "Entity1");
  }

  @Test
  void test9() {
    TestService service = new TestService(50);
    service.setCurrentId(1);
    TestEntity entity = new TestEntity("Entity1");

    service.insert(entity);
    service.commit();

    service.removeOldEntity(entity);
    TimeUtil.sleep(100);
    service.cleanOldEntities();

    TestEntity savedEntity = service.get(1L);
    Assertions.assertEquals(savedEntity, null);
  }
}

class TestEntity extends BaseEntity {

  private String data;

  public TestEntity(String data) {
    this.data = data;
  }

  public TestEntity(TestEntity e) {
    super(e);
    this.data = e.data;
  }

  @Override
  public Object getKey() {
    return id;
  }

  @Override
  public BaseEntity deepCopy() {
    return new TestEntity(this);
  }

  @Override
  public boolean equals(Object o) {
    if (this == o) {
      return true;
    }
    if (o == null || getClass() != o.getClass()) {
      return false;
    }
    TestEntity entity = (TestEntity) o;
    return Objects.equals(data, entity.data);
  }

  @Override
  public int hashCode() {
    return Objects.hash(data);
  }

  @Override
  public String toString() {
    return "TestEntity{" + "data='" + data + '\'' + '}';
  }

  public String getData() {
    return data;
  }

  public void setData(String data) {
    this.data = data;
  }
}

class TestService extends BaseService<TestEntity> {

  public TestService() {
    super(true);
  }

  public TestService(long expiryTime) {
    super(true);
    this.expiryTime = expiryTime;
  }
}
