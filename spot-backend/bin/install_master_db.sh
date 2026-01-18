# ./install_slave_db.sh root-password trading-password
password="$1"
trading_password="$2"

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
echo "server_id=1" >> /etc/my.cnf.d/trading.cnf
echo "bind-address="`hostname -I` >> /etc/my.cnf.d/trading.cnf

systemctl start mariadb
systemctl enable mariadb
systemctl status mariadb

mysqladmin -u root password $password

mysql_secure_installation

mysql -uroot -p$password -e "GRANT REPLICATION SLAVE ON *.* TO 'slave_user'@'%' IDENTIFIED BY 'ga23AFDa';"
mysql -uroot -p$password -e "FLUSH PRIVILEGES;"

mysql -uroot -p$password -e "create database trading;"
mysql -uroot -p$password -e "grant all on trading.* to 'trading'@'%' identified by '$trading_password';"
