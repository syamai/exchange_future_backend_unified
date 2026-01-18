docker exec -it amanpuri-api bash
if [ $? -eq 0 ]; then
  echo OK
else
  docker exec -it amanpuri-api ash
fi