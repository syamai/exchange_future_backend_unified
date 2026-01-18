date=`date '+%Y-%m-%d.%H-%M-%S'`
mysqldump -uroot -p --databases amanpuri | pv > amanpuri.$date.sql