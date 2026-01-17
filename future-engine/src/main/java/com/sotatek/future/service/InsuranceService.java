package com.sotatek.future.service;

import com.sotatek.future.util.MarginBigDecimal;
import lombok.AccessLevel;
import lombok.NoArgsConstructor;

@NoArgsConstructor(access = AccessLevel.PRIVATE)
public class InsuranceService {

  private static final InsuranceService instance = new InsuranceService();

  public static InsuranceService getInstance() {
    return instance;
  }

  public void insert(MarginBigDecimal amount) {
    // TODO implement
  }

  public void initialize() {}
}
