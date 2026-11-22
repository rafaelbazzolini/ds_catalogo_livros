FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev unzip git && docker-php-ext-install pdo_mysql intl mbstring zip && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
COPY docker/php-development.ini /usr/local/etc/php/conf.d/development.ini
COPY docker/development.conf /etc/apache2/conf-enabled/development.conf
RUN composer dump-autoload --optimize && mkdir -p var && chown -R www-data:www-data var
