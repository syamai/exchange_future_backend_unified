#install nodejs
curl --silent --location https://rpm.nodesource.com/setup_8.x | sudo bash -
yum -y install nodejs

#install pm2
npm install pm2 -g 

#install laravel echo server
npm install -g laravel-echo-server