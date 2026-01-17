package com.sotatek.future.service;

import com.sotatek.future.BaseTest;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;

class OrderServiceTest extends BaseTest {

  @BeforeEach
  public void setUp() throws Exception {
    super.setUp();
    ServiceFactory.initialize();
  }

  @Override
  @AfterEach
  public void tearDown() throws Exception {
    super.tearDown();
  }
}
