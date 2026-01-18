#!/bin/sh
./bin/healthcheck/hc_auto_deleverage.sh
./bin/healthcheck/hc_liquidate.sh

#future index
#pecpertual index
./bin/healthcheck/hc_funding.sh
./bin/healthcheck/hc_dividend_all.sh
./bin/healthcheck/hc_ifd_order.sh
./bin/healthcheck/hc_stop_order.sh
#matching engine
./bin/healthcheck/hc_trading_volume_ranking.sh
./bin/healthcheck/hc_leaderbook.sh
./bin/healthcheck/hc_profit_margin.sh
