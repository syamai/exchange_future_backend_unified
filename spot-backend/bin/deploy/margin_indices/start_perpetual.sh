contract="$1"
sed "s/SYMBOL/$contract/g" ./bin/deploy/margin_indices/contract_perpetual.config.js.dummy > ./bin/deploy/margin_indices/"$contract"_contract_perpetual.config.js
pm2 startOrRestart ./bin/deploy/margin_indices/"$contract"_contract_perpetual.config.js