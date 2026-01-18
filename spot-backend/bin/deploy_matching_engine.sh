#install php
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
apt -y install php7.1 php7.1-opcache php7.1-xml php7.1-mcrypt php7.1-gd php7.1-dev php7.1-mysqlnd php7.1-intl php7.1-mbstring php7.1-bcmath

#install composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin/ --filename=composer
php -r "unlink('composer-setup.php');"

#install nodejs
curl --silent https://deb.nodesource.com/gpgkey/nodesource.gpg.key | sudo apt-key add -
echo "deb https://deb.nodesource.com/node_8.x artful main" | sudo tee /etc/apt/sources.list.d/nodesource.list
echo "deb-src https://deb.nodesource.com/node_8.x artful main" | sudo tee -a /etc/apt/sources.list.d/nodesource.list
apt -y install nodejs
apt -y install npm

#install pm2
npm install pm2 -g 


#make storage and cache writable
mkdir -p /var/www/amanpuri-api/bootstrap/cache
mkdir -p /var/www/amanpuri-api/storage/framework/cache
mkdir -p /var/www/amanpuri-api/storage/framework/sessions
mkdir -p /var/www/amanpuri-api/storage/framework/views
mkdir -p /var/www/amanpuri-api/storage/logs
chown -R www-data:www-data /var/www/amanpuri-api/storage
chown -R www-data:www-data /var/www/amanpuri-api/bootstrap/cache
