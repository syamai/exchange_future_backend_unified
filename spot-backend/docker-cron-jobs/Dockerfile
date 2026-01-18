FROM php:8.1-cli

# Cài cron, git, zip, inotify-tools và các extension cần thiết
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    cron \
    telnet \
	redis-tools \
    inetutils-ping \
    default-mysql-client \
	netcat-openbsd \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    librdkafka-dev \
    libssl-dev \
    libaio-dev \
    libxslt-dev \
    libxml2-dev \
    libevent-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
		pdo \
		pdo_mysql \
		zip \
		gd \
		sockets \
		bcmath \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fix warning about git "dubious ownership"
RUN git config --global --add safe.directory /app

# Copy các file cần thiết
COPY crontab.txt /etc/cron.d/laravel-cron
COPY entrypoint.sh /entrypoint.sh
COPY watch-crontab.sh /app/laravel-cron/watch-crontab.sh

# Set quyền cho các file
RUN chmod 0644 /etc/cron.d/laravel-cron \
    && chmod +x /entrypoint.sh \
    && chmod +x /app/laravel-cron/watch-crontab.sh

WORKDIR /app

CMD ["/entrypoint.sh"]
