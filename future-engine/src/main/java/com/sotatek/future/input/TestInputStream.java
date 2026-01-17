package com.sotatek.future.input;

import com.sotatek.future.entity.Account;
import com.sotatek.future.entity.Command;
import com.sotatek.future.entity.Order;
import com.sotatek.future.enums.Asset;
import com.sotatek.future.enums.CommandCode;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.enums.OrderStatus;
import com.sotatek.future.enums.OrderType;
import com.sotatek.future.enums.TimeInForce;
import com.sotatek.future.util.MarginBigDecimal;
import java.io.IOException;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.TimeoutException;

import static com.sotatek.future.service.AccountService.INSURANCE_USER_ID;

public class TestInputStream extends BaseInputStream<Command> {

    public static final int PLACE_ORDER = 0;
    public static final int CANCEL_ORDER = 1;
    public static final int MATCHING = 2;

    private long userCount;
    private long orderCount;
    private int type;

    public TestInputStream(long userCount, long orderCount, int type) {
        this.userCount = userCount;
        this.orderCount = orderCount;
        this.type = type;
    }

    @Override
    public boolean connect() throws IOException, TimeoutException {
        if (this.callback == null) {
            return true;
        }
        this.test();
        return true;
    }

    private void test() {
        this.createAccounts();
        switch (this.type) {
            case PLACE_ORDER:
                this.testPlaceOrder();
                break;
            case CANCEL_ORDER:
                this.testCancel();
                break;
            case MATCHING:
                this.testMatching();
                break;
        }
    }

    private void testPlaceOrder() {
        for (int i = 0; i < this.orderCount; i++) {
            if (i % 10000 == 0) {
                System.out.println("Order input " + i);
            }
            OrderSide side = Math.random() > 0.5 ? OrderSide.BUY : OrderSide.SELL;
            Double price = Math.floor(Math.random() * 10000) + 50000 + (side == OrderSide.BUY ? 1000 : -1000);
            Double quantity = Math.floor(Math.random() * 10000) / 10000;
            int userId = (int) Math.floor(Math.random() * this.userCount);
            Order order = new Order(i, userId, side, OrderType.LIMIT, price.toString(), String.format("%f", quantity));
            order.setTimeInForce(TimeInForce.GTC);
            order.setSymbol("BTCUSDT");
            order.setLeverage(MarginBigDecimal.valueOf(20));
            order.setStatus(OrderStatus.PENDING);
            Command command = new Command(CommandCode.PLACE_ORDER, order);
            this.callback.onNewData(command);
        }
    }

    private void testCancel() {
        List<Order> orders = new ArrayList<>();
        for (int i = 0; i < this.orderCount; i++) {
            if (i % 10000 == 0) {
                System.out.println("Create Order " + i);
            }
            OrderSide side = Math.random() > 0.5 ? OrderSide.BUY : OrderSide.SELL;
            Double price = Math.floor(Math.random() * 10000) + 50000 + (side == OrderSide.BUY ? -10000 : 10000);
            Double quantity = Math.floor(Math.random() * 10000) / 10000;
            int userId = (int) Math.floor(Math.random() * this.userCount);
            Order order = new Order(i, userId, side, OrderType.LIMIT, price.toString(), String.format("%f", quantity));
            order.setTimeInForce(TimeInForce.GTC);
            order.setSymbol("BTCUSDT");
            order.setStatus(OrderStatus.PENDING);
            order.setLeverage(MarginBigDecimal.valueOf(20));
            Command command = new Command(CommandCode.PLACE_ORDER, order);
            this.callback.onNewData(command);
            orders.add(order);
        }

        for (Order order : orders) {
            Command command = new Command(CommandCode.CANCEL_ORDER, order);
            this.callback.onNewData(command);
        }
    }

    private void testMatching() {
        for (int i = 1; i < this.orderCount; i++) {
            if (i % 10000 == 0) {
                System.out.println("Order input " + i);
            }
            Order order;
            if (i % 10000 == 0) {
                OrderSide side = OrderSide.SELL;
                int userId = 0;
                order = new Order(i, userId, side, OrderType.LIMIT, "65000", "1");
            } else {
                OrderSide side = OrderSide.BUY;
                int userId = 1 + (int) Math.floor(Math.random() * (this.userCount - 1));
                order = new Order(i, userId, side, OrderType.LIMIT, "65000", "0.0001");
            }
            order.setTimeInForce(TimeInForce.GTC);
            order.setSymbol("BTCUSDT");
            order.setStatus(OrderStatus.PENDING);
            order.setLeverage(MarginBigDecimal.valueOf(20));
            Command command = new Command(CommandCode.PLACE_ORDER, order);
            this.callback.onNewData(command);

        }
    }

    private void createAccounts() {
        for (long i = 0; i < this.userCount; i++) {
            Account account = new Account(i, MarginBigDecimal.valueOf(100000000L));
            Command command = new Command(CommandCode.CREATE_ACCOUNT, account);
            this.callback.onNewData(command);
        }
        // create account for insurance fund
        Account account = new Account();
        account.setId(20000l);
        account.setBalance(MarginBigDecimal.valueOf("10000000000"));
        account.setAsset(Asset.USDT);
        account.setUserId(INSURANCE_USER_ID);
        Command command = new Command(CommandCode.CREATE_ACCOUNT, account);
        this.callback.onNewData(command);
    }

    @Override
    public void close() {

    }
}