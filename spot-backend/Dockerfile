FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update -y &&\
    apt-get install -y libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev libzip-dev zip &&\
    apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev &&\
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install gd pdo pdo_mysql bcmath pcntl sysvmsg sockets zip opcache

RUN touch /usr/local/etc/php/conf.d/uploads.ini \
    && echo "upload_max_filesize = 80M;" >> /usr/local/etc/php/conf.d/uploads.ini

RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y git

RUN git clone https://github.com/edenhill/librdkafka.git \
    && cd librdkafka \
    && ./configure \
    && make \
    && make install \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Install Swoole extension for coroutine-based async processing
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
RUN composer install --optimize-autoloader
RUN composer dump-autoload --optimize
RUN php artisan config:cache
# RUN php artisan route:cache

RUN php artisan storage:link && \
    php artisan passport:keys && \
    php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" && \
    php artisan l5-swagger:generate

RUN chown -R www-data:www-data /var/www/html/storage/api-docs
RUN chown -R www-data:www-data /var/www/html/storage/logs
RUN chown -R www-data:www-data /var/www/html/storage/framework

RUN export RABBITMQ_PREFIX="exchange_"
RUN echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-ram-limit.ini
# Optimized OPcache + JIT settings for matching engine performance
RUN echo -e '\
opcache.enable=1\n\
opcache.enable_cli=1\n\
opcache.memory_consumption=256\n\
opcache.interned_strings_buffer=32\n\
opcache.max_accelerated_files=20000\n\
opcache.max_wasted_percentage=10\n\
opcache.revalidate_freq=0\n\
opcache.validate_timestamps=0\n\
opcache.fast_shutdown=1\n\
opcache.jit_buffer_size=100M\n\
opcache.jit=1255\n\
' >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
