#
# Build Composer Base Image
#
FROM composer as composer


ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_PROCESS_TIMEOUT=2000

WORKDIR /app
COPY . /app

RUN composer update --ignore-platform-reqs
RUN composer require kalnoy/nestedset doctrine/dbal awobaz/compoships dompdf/dompdf --ignore-platform-reqs

RUN chgrp -R 0 /app && \
    chmod -R g=u /app


#
# Build Server Deployment Image
#
FROM php:8.1-apache

WORKDIR /

# Local proxy config (remove for server deployment)
# ENV http_proxy=http://198.161.14.25:8080

RUN apt-get update -y && apt -y upgrade && apt-get install -y \
    openssl \
    ssh-client \
    zip \
    unzip \
    vim \
	sudo

RUN apt-get update && apt-get install -y cron && cron

# Copy cron file to the cron.d directory
COPY /laravelcron /etc/cron.d/laravelcron

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/laravelcron

# Apply cron job
RUN crontab /etc/cron.d/laravelcron

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

RUN echo "deb https://packages.sury.org/php/ buster main" | tee /etc/apt/sources.list.d/php.list
RUN docker-php-ext-install pdo pdo_mysql opcache

COPY --chown=www-data:www-data --from=composer /app /var/www/html


# Create the "public" folder in /storage/app
WORKDIR /
RUN mkdir -p /var/www/html/storage/app/public

# Set the working directory
WORKDIR /var/www/html

# Set appropriate permissions for the /storage/app/public directory
RUN chown -R www-data:www-data /var/www/html/storage/app/public
# Copy the contents from your local ./storage/app/public directory to the target directory
COPY ./storage/app/public /var/www/html/storage/app/public
# Set permissions for the copied files and directories
RUN chmod -R 755 /var/www/html/storage/app/public

# Run php artisan storage:link to create the symbolic link
RUN php artisan storage:link

#Switch back to the root folder
WORKDIR /

# Copy Server Config files (Apache / PHP)
COPY --chown=www-data:www-data server_files/apache2.conf /etc/apache2/apache2.conf
COPY --chown=www-data:www-data server_files/ports.conf /etc/apache2/ports.conf
COPY --chown=www-data:www-data server_files/.htaccess /var/www/html/public/.htaccess
COPY --chown=www-data:www-data server_files/php.ini /usr/local/etc/php/php.ini
COPY --chown=www-data:www-data server_files/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY --chown=www-data:www-data server_files/mods-enabled/expires.load /etc/apache2/mods-enabled/expires.load
COPY --chown=www-data:www-data server_files/mods-enabled/headers.load /etc/apache2/mods-enabled/headers.load
COPY --chown=www-data:www-data server_files/mods-enabled/rewrite.load /etc/apache2/mods-enabled/rewrite.load
COPY --chown=www-data:www-data server_files/start.sh /usr/local/bin/start

RUN chmod +x /usr/local/bin/start

# Create cache and session storage structure
RUN bash -c 'mkdir -p /var/www/html/storage{app,framework,logs}'
RUN chmod -R 755 /var/www/html/storage
RUN chown -R www-data:www-data /var/www/html/storage/app /var/www/html/storage/framework /var/www/html/storage/logs

RUN chmod 4111 /usr/bin/sudo 
RUN chmod -R 755 /var/log/apache2 
RUN chown -R www-data:www-data /var/log/apache2 

#RUN useradd -l -u 1001510000 -c "1001510000" 1001510000 && \
    #addgroup crond-users && \
    #chgrp crond-users /var/run/crond.pid && \
    #usermod -a -G crond-users 1001510000


EXPOSE 8000



# Add a command to base-image entrypont script
RUN sed -i 's/^exec /service cron start\n\nexec /' /usr/local/bin/apache2-foreground

#CMD /usr/local/bin/apache2-foreground

#RUN /usr/local/bin/apache2-foreground