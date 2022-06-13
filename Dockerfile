#
# Build Composer Base Image
#
FROM composer as composer

# Local proxy config (remove for server deployment)
# ENV http_proxy=http://198.161.14.25:8080

ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app
COPY . /app

RUN composer update --ignore-platform-reqs
RUN composer require kalnoy/nestedset doctrine/dbal awobaz/compoships --ignore-platform-reqs
RUN chgrp -R 0 /app && \
    chmod -R g=u /app

#
# Build Server Deployment Image
#
FROM php:8.0-apache

WORKDIR /

# Local proxy config (remove for server deployment)
# ENV http_proxy=http://198.161.14.25:8080

RUN apt-get update -y && apt -y upgrade && apt-get install -y \
    openssl \
    ssh-client \
    zip \
    unzip

RUN ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log && \
	apt-get update -y && \
	apt-get upgrade -y --fix-missing && \
	apt-get dist-upgrade -y && \
	dpkg --configure -a && \
	apt-get -f install && \
	apt-get install -y zlib1g-dev libicu-dev g++ && \
	apt-get install rsync grsync && \
	apt-get install tar && \
	set -eux; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite; \
	fi; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	docker-php-ext-install -j "$(nproc)" \
	; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false;

RUN echo '\
  opcache.interned_strings_buffer=16\n\
  opcache.load_comments=Off\n\
  opcache.max_accelerated_files=16000\n\
  opcache.save_comments=Off\n\
  ' >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

RUN echo "deb https://packages.sury.org/php/ buster main" | tee /etc/apt/sources.list.d/php.list
RUN docker-php-ext-install pdo pdo_mysql opcache

COPY --chown=www-data:www-data --from=composer /app /var/www/html

# Copy Server Config files (Apache / PHP)
COPY --chown=www-data:www-data server_files/apache2.conf /etc/apache2/apache2.conf
COPY --chown=www-data:www-data server_files/ports.conf /etc/apache2/ports.conf
COPY --chown=www-data:www-data server_files/.htaccess /var/www/html/public/.htaccess
COPY --chown=www-data:www-data server_files/php.ini /usr/local/etc/php/php.ini
COPY --chown=www-data:www-data server_files/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY --chown=www-data:www-data server_files/mods-enabled/expires.load /etc/apache2/mods-enabled/expires.load
COPY --chown=www-data:www-data server_files/mods-enabled/headers.load /etc/apache2/mods-enabled/headers.load
COPY --chown=www-data:www-data server_files/mods-enabled/rewrite.load /etc/apache2/mods-enabled/rewrite.load

RUN cd /var/www/html && \
    php artisan config:clear && \
    php artisan config:cache

EXPOSE 8000
