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
chown -R apache:apache /var/www/amanpuri-api/storage
chown -R apache:apache /var/www/amanpuri-api/bootstrap/cache
setfacl -d -m g:apache:rwx /var/www/amanpuri-api/storage/logs

chcon -t httpd_sys_rw_content_t -R /var/www/amanpuri-api/storage
chcon -t httpd_sys_rw_content_t -R /var/www/amanpuri-api/bootstrap/cache

#install git
yum install -y git

#install redis
yum -y install redis
sed 's/^bind 127\.0\.0\.1/bind 0\.0\.0\.0/g' /etc/redis.conf > t.conf
cat t.conf > /etc/redis.conf
rm -rf t.conf
systemctl start redis
systemctl enable redis

# add crontab: check disk free
echo "0 * * * * cd /var/www/amanpuri-api && ./bin/healthcheck/df.sh vcc.alert@sotatek.com" | crontab -

#make apache be able to connect network
setsebool -P httpd_can_network_connect 1

#configuration
#env
#migration
#laravel echo server start
#