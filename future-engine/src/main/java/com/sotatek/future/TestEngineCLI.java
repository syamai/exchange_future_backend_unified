package com.sotatek.future;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.sotatek.future.entity.FutureBidsAsksBinanceDataResponse;
import com.sotatek.future.enums.OrderSide;
import com.sotatek.future.output.OrderBookOutputStream;
import com.sotatek.future.util.MarginBigDecimal;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.SortedMap;
import java.util.function.Function;
import java.util.stream.Collectors;

public class TestEngineCLI {

  public static void main(String[] args) {
    try {
      OrderBookOutputStream.BidsAsksDataCombinedWithBinance result = TestEngineCLI.combineCurrentBidsAsksWithBinanceData(new ArrayList<>(), new ArrayList<>(), new ArrayList<>(), new ArrayList<>());
      int x = 0;
    } catch (Exception e) {
      e.printStackTrace();
    }
  }

  public static OrderBookOutputStream.BidsAsksDataCombinedWithBinance combineCurrentBidsAsksWithBinanceData(List<MarginBigDecimal[]> newBids, List<MarginBigDecimal[]> newAsks, List<MarginBigDecimal[]> changedBids, List<MarginBigDecimal[]> changedAsks) throws Exception {
    // Kéo data từ binance về
    FutureBidsAsksBinanceDataResponse binanceData = TestEngineCLI.getDataFromBinance("BTCUSDT");

    // Tạo ra 4 biến mới là: newBidsWithBinance, newAsksWithBinance, changedBidsWithBinance, changedAsksWithBinance
    // Những biến này là kết quả sau khi combine data binance và data của chúng ta
    List<MarginBigDecimal[]> newBidsWithBinance = new ArrayList<>(newBids);
    List<MarginBigDecimal[]> newAsksWithBinance = new ArrayList<>(newAsks);
    List<MarginBigDecimal[]> changedBidsWithBinance = new ArrayList<>(changedBids);
    List<MarginBigDecimal[]> changedAsksWithBinance = new ArrayList<>(changedAsks);

    newBidsWithBinance.addAll(binanceData.getBids());
    newAsksWithBinance.addAll(binanceData.getAsks());
    changedBidsWithBinance.addAll(binanceData.getBids());
    changedAsksWithBinance.addAll(binanceData.getAsks());

    // Tạo newOrderBook và changedRows bằng 4 biến trên
    return new OrderBookOutputStream.BidsAsksDataCombinedWithBinance(newBidsWithBinance, newAsksWithBinance, changedBidsWithBinance, changedAsksWithBinance);
  }

  public static FutureBidsAsksBinanceDataResponse getDataFromBinance(String symbol) throws Exception {
    URL obj = new URL("https://fapi.binance.com/fapi/v1/depth?symbol=" + symbol + "&limit=5");
    HttpURLConnection con = (HttpURLConnection) obj.openConnection();
    con.setRequestMethod("GET");
    int responseCode = con.getResponseCode();
//    System.out.println("GET Response Code :: " + responseCode);
    if (responseCode == HttpURLConnection.HTTP_OK) { // success
      BufferedReader in = new BufferedReader(new InputStreamReader(con.getInputStream()));
      String inputLine;
      StringBuffer response = new StringBuffer();
      while ((inputLine = in.readLine()) != null) {
        response.append(inputLine);
      }
      in.close();
      System.out.println(response.toString());

      ObjectMapper objectMapper = new ObjectMapper();
      FutureBidsAsksBinanceDataResponse result = objectMapper.readValue(response.toString(), FutureBidsAsksBinanceDataResponse.class);
      return result;
    } else {
      System.out.println("Cannot get data from Binance");
    }
    return null;
  }
}
