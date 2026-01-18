#install apache
yum install -y httpd
cp ./bin/amanpuri-api.conf /etc/httpd/conf.d/
cp ./bin/amanpuri-api_https.conf /etc/httpd/conf.d/

#install mariadb (in order to run mysql command)
echo "[mariadb]" > /etc/yum.repos.d/MariaDB.repo
echo "name = MariaDB" >> /etc/yum.repos.d/MariaDB.repo
echo "baseurl = http://yum.mariadb.org/10.1/rhel7-amd64" >> /etc/yum.repos.d/MariaDB.repo
echo "gpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB" >> /etc/yum.repos.d/MariaDB.repo
echo "gpgcheck=1" >> /etc/yum.repos.d/MariaDB.repo

yum install -y mariadb-server

yum install -y mod_ssl
mv /etc/httpd/conf.d/ssl.conf /etc/httpd/conf.d/ssl.conf.bak

#install php
rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm
yum -y install php71w php71w-opcache php71w-xml php71w-mcrypt php71w-gd php71w-devel php71w-mysqlnd php71w-intl php71w-mbstring php71w-bcmath


#install composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin/ --filename=composer
php -r "unlink('composer-setup.php');"

#install nodejs
curl --silent --location https://rpm.nodesource.com/setup_8.x | sudo bash -
yum -y install nodejs

#install pm2
npm install pm2 -g 

#make storage and cache writable
mkdir -p /var/www/amanpuri-api/bootstrap/cache
mkdir -p /var/www/amanpuri-api/storage/framework/cache
mkdir -p /var/www/amanpuri-api/storage/framework/sessions
mkdir -p /var/www/amanpuri-api/storage/framework/views
mkdir -p /var/www/amanpuri-api/storage/logs
mkdir -p /var/www/amanpuri-api/public/
mkdir -p /var/www/amanpuri-api/storage/app/public/qr_codes
mkdir -p /var/www/amanpuri-api/storage/app/public/notice
mkdir -p /var/www/amanpuri-api/storage/app/public/kyc
mkdir -p /var/www/amanpuri-api/storage/app/public/excel
chown -R apache:apache /var/www/amanpuri-api/storage
chown -R apache:apache /var/www/amanpuri-api/bootstrap/cache
chown -R apache:apache /var/www/amanpuri-api/database/migrations
setfacl -d -m g:apache:rwx /var/www/amanpuri-api/storage/logs
ln -s /var/www/amanpuri-api/storage/app/public /var/www/amanpuri-api/public/storage

restorecon -R /var/www
chcon -t httpd_sys_rw_content_t -R /var/www/amanpuri-api/storage
chcon -t httpd_sys_rw_content_t -R /var/www/amanpuri-api/bootstrap/cache
chcon -t httpd_sys_rw_content_t -R /var/www/amanpuri-api/database/migrations/erc20

systemctl start httpd.service
systemctl enable httpd.service

#cd /root/BitGoJS && npm install && pm2 start ./bin/bitgo-express -x -- --debug --port 3080 --env prod --bind 0.0.0.0 --keypath /root/ssl/bitgo.key  --crtpath /root/ssl/bitgo.crt

#yum install supervisor
#cp amanpuri-api.ini /etc/supervisor.d/
#touch /var/www/amanpuri-api/storage/logs/worker.log
#chmod 777 /var/www/amanpuri-api/storage/logs/worker.log
#systemctl start supervisord
#systemctl enable supervisord


#make apache be able to connect network
setsebool -P httpd_can_network_connect 1

#configuration
#env
#migration
#laravel echo server start
#