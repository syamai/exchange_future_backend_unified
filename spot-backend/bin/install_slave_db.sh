# ./install_slave_db.sh password trading-password master-host server-id
password="$1"
trading_password="$2"
master="$3"
server_id="$4"

if [ -z $password ]; then
    echo "Please enter root password"
    read -s password
fi
if [ -z $password ]; then
    echo "Invalid root password"
exit
fi

if [ -z $trading_password ]; then
    echo "Please enter password (trading user)"
    read -s trading_password
fi
if [ -z $trading_password ]; then
    echo "Invalid password"
exit
fi

if [ -z $master ]; then
    echo "Please enter ip of master database (e.g. 192.168.1.4)"
    read master
fi
if [ -z $master ]; then
    echo "Invalid master host"
    exit
fi

if [ -z $server_id ]; then
    echo "Please enter server id (first slave: 2, second slave: 3, ....)"
    read server_id
fi
if [ -z $server_id ]; then
    echo "Invalid server id"
    exit
fi

echo "[ivarch]" > /etc/yum.repos.d/ivarch.repo
echo "name=RPMs from ivarch.com" >> /etc/yum.repos.d/ivarch.repo
echo "baseurl=http://www.ivarch.com/programs/rpms/\$basearch/" >> /etc/yum.repos.d/ivarch.repo
echo "enabled=1" >> /etc/yum.repos.d/ivarch.repo
echo "gpgcheck=1" >> /etc/yum.repos.d/ivarch.repo
rpm --import http://www.ivarch.com/personal/public-key.txt
yum install -y pv

echo "[mariadb]" > /etc/yum.repos.d/MariaDB.repo
echo "name = MariaDB" >> /etc/yum.repos.d/MariaDB.repo
echo "baseurl = http://yum.mariadb.org/10.1/rhel7-amd64" >> /etc/yum.repos.d/MariaDB.repo
echo "gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB" >> /etc/yum.repos.d/MariaDB.repo
echo "gpgcheck=1" >> /etc/yum.repos.d/MariaDB.repo

yum install -y mariadb-server

if [ ! -f ./trading.cnf ]; then
    echo "File not found: trading.cnf"
    exit
fi
cp trading.cnf /etc/my.cnf.d/
echo "server_id=$server_id" >> /etc/my.cnf.d/trading.cnf
echo "bind-address="`hostname -I` >> /etc/my.cnf.d/trading.cnf

systemctl start mariadb
systemctl enable mariadb
systemctl status mariadb

mysqladmin -u root password $password

mysql_secure_installation

mysql -uroot -p$password -e "create database trading;"
mysql -uroot -p$password -e "grant select on trading.* to 'trading'@'%' identified by '$trading_password';";

mysql -uroot -p$password -e "GRANT REPLICATION CLIENT ON *.* TO 'slave_monitor'@'%' IDENTIFIED BY 'ga23AFDa';"

mysql -uroot -p$password -e "CHANGE MASTER 'trading' TO MASTER_HOST='$master', MASTER_USER='slave_user', MASTER_PASSWORD='ga23AFDa', MASTER_PORT=3306, MASTER_CONNECT_RETRY=10, MASTER_USE_GTID=slave_pos;";
mysql -uroot -p$password -e "START SLAVE 'trading';";
mysql -uroot -p$password -e "SHOW SLAVE 'trading' STATUS;";


echo "You should change innodb_buffer_pool_size and innodb_log_file_size value (in /etc/my.cnf.d/trading.cnf) for better performance"

# to reset slave
#mysql -uroot -pFs2E43 -e "RESET SLAVE 'trading';";
#mysql -uroot -pFs2E43 -e "START SLAVE 'trading';";

# resync slave
# on master, find current gtid
# show global variables like '%gtid%';

# on slave, choose one of following:
#SET GLOBAL gtid_slave_pos = “”;
#SELECT BINLOG_GTID_POS(“master-bin.000001", 600);
#SET GLOBAL gtid_slave_pos = “0-1-2”;
