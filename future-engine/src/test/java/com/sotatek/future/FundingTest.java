package com.sotatek.future;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.FundingParams;
import com.sotatek.future.entity.InstrumentExtraInformation;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.service.ServiceFactory;
import com.sotatek.future.util.MarginBigDecimal;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Date;
import java.util.List;
import java.util.stream.Collectors;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Disabled;
import org.junit.jupiter.api.Test;

@Disabled("Disable until FundingService can actually update account balance")
public class FundingTest extends BaseTest {

  @Override
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

  @Test
  void test2() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("2000"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("2000"));

    List<Command> commands = new ArrayList<>();
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(65000), MarginBigDecimal.ZERO)));

    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000.13", "1.3457");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000.13", "1.3457");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    commands.add(
        new Command(CommandCode.PAY_FUNDING, this.createFundingParams("0.023456", "65000.12")));

    this.testFunding(commands);

    Account account1 = this.accountService.get(1L);
    Account account2 = this.accountService.get(2L);
    assertEquals(MarginBigDecimal.valueOf("1957.615214"), account1.getBalance());
    assertEquals(MarginBigDecimal.valueOf("1954.914112"), account2.getBalance());
  }

  @Test
  void test3() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1100"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1100"));

    List<Command> commands = new ArrayList<>();
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(65000), MarginBigDecimal.ZERO)));

    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000.11", "1.3457");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000.11", "1.3457");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    commands.add(
        new Command(CommandCode.PAY_FUNDING, this.createFundingParams("0.223456", "65000.12")));

    this.testFunding(commands);

    // funding amount = 65000.12 * 1.3457 * 0.223456 / 100 = 195.458441

    Account account1 = this.accountService.get(1L);
    Account account2 = this.accountService.get(2L);

    // available 137.005211
    assertEquals(MarginBigDecimal.valueOf("882.673897"), account1.getBalance()); // 1078.132335

    // available 93.431364
    assertEquals(MarginBigDecimal.valueOf("1229.855455"), account2.getBalance()); // 1034.397004
  }

  @Test
  void test4() {
    this.setUpAccount(1, MarginBigDecimal.valueOf("1100"));
    this.setUpAccount(2, MarginBigDecimal.valueOf("1100"));

    List<Command> commands = new ArrayList<>();
    commands.add(
        new Command(
            CommandCode.LIQUIDATE,
            new InstrumentExtraInformation(
                this.defaultSymbol, MarginBigDecimal.valueOf(65000), MarginBigDecimal.ZERO)));

    Order order10 = this.createOrder(10, 1, OrderSide.BUY, OrderType.LIMIT, "65000.13", "1.3457");
    Order order11 = this.createOrder(11, 2, OrderSide.SELL, OrderType.LIMIT, "65000.13", "1.3457");
    List<Order> orders = Arrays.asList(order10.deepCopy(), order11.deepCopy());
    List<Command> orderCommands =
        orders.stream()
            .map(order -> new Command(CommandCode.PLACE_ORDER, order))
            .collect(Collectors.toList());
    commands.addAll(orderCommands);

    commands.add(
        new Command(
            CommandCode.PAY_FUNDING,
            this.createFundingParams("0.223456", "65000.12", System.currentTimeMillis() + 1000)));
    commands.add(
        new Command(
            CommandCode.PAY_FUNDING,
            this.createFundingParams("-0.243456", "65000.12", System.currentTimeMillis() + 2000)));

    this.testFunding(commands);

    // funding amount = 65000.12 * 1.3457 * 0.223456 / 100 = 195.458441
    // funding amount 2 = 65000.12 * 1.3457 * -0.243456 / 100 = -212.952573

    Account account1 = this.accountService.get(1L);
    System.out.println(account1);
    // available 137.005211
    assertEquals(MarginBigDecimal.valueOf("1095.626464"), account1.getBalance()); // 1078.132335
    Account account2 = this.accountService.get(2L);
    // available 93.431364
    System.out.println(account2);
    assertEquals(MarginBigDecimal.valueOf("1016.902862"), account2.getBalance()); // 1034.397004
  }

  private FundingParams createFundingParams(String fundingRate, String oraclePrice, long time) {
    MarginBigDecimal rate = MarginBigDecimal.valueOf(fundingRate);
    MarginBigDecimal price = MarginBigDecimal.valueOf(oraclePrice);
    return new FundingParams(this.defaultSymbol, rate, price, new Date(time));
  }

  private FundingParams createFundingParams(String fundingRate, String oraclePrice) {
    return this.createFundingParams(fundingRate, oraclePrice, System.currentTimeMillis() + 10000);
  }
}
