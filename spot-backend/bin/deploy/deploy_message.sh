echo "---------------------"
echo "Please backup your database before migrating && seeding"
echo "or run manually: make backup-db"
echo -e "\033[31;7mWhich database you want to backup?\e[0m";
echo "---------------------"
echo -e "-Server 1 or 2 (\033[31;7m1\e[0m|\033[31;7m2\e[0m)";
echo -e "-Server Staging (\033[31;7ms\e[0m)";
echo -e "-Exit (press \033[31;7mn\e[0m)";
# read -rsn1 answer

while [ "$input" != "n" ]; do
read -rn1 input
if [ "$input" = "1" ]; then
    echo "Backup database 'amanpuri1'"
    mysqldump --add-drop-table -u root -p1 amanpuri1 > ~/amanpuri1_`date +"%d%m%Y%H%M%S"`.sql
    echo "Done"
fi
if [ "$input" = "2" ]; then
    echo "Backup database 'amanpuri2'"
    mysqldump --add-drop-table -u root -p1 amanpuri2 > ~/amanpuri2_`date +"%d%m%Y%H%M%S"`.sql
    echo "Done"
fi
if [ "$input" = "s" ]; then
    echo "Backup database 'amanpuri'"
    mysqldump --add-drop-table -u root -p1 amanpuri > ~/amanpuri_`date +"%d%m%Y%H%M%S"`.sql
    echo "Done"
fi
done