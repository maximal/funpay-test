# syntax=docker/dockerfile:1.6

FROM debian:stable-slim

# Install dependencies
RUN apt-get update  &&  apt-get install --no-install-recommends -y \
	# Basic packages
	lsb-release build-essential ca-certificates curl gnupg \
	# ZIP Tools
	zlib1g-dev libzip-dev zip unzip \
	# 7ZIP Tools
	#p7zip \
	#p7zip-full \
	# Sending emails \
	#exim4 \
	# cron (disabled, because scheduler/run.sh script used in container)
	#cron \
	# supervisor to run Laravel app
	#supervisor \
	# For janus-pp-rec
	#libjansson-dev \
	# Office Documents (converting to PDF)
	#libreoffice-writer libreoffice-calc default-jre libreoffice-java-common \
	# PNG and WebP Tools
	#optipng webp \
	# HEIF/HEIC Tools (disable if VIPS (libvips42) enabled)
	#libheif-examples \
	# PDF Tools (disable if VIPS (libvips42) enabled)
	#poppler-utils \
	# Audio Tools
	#sox libsox-fmt-all \
	# Video Tools
	#ffmpeg \
	# VIPS tools
	#libvips42 \
	# `pg_dump` and `psql` for `artisan db:dump` and `artisan db:restore` commands
	#postgresql-client \
	# Git
	git

# Install PHP with extensions
RUN curl -sSLo /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > \
	/etc/apt/sources.list.d/php.list
RUN apt-get update  &&  apt-get install --no-install-recommends -y \
	# Basic packages
	php8.3-cli php8.3-fpm php8.3-intl php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath  \
	# For databases and caches
	#php8.3-pgsql \
	#php8.3-mysql \
	php8.3-mysqli \
	#php8.3-memcached \
	#php8.3-redis \
	# For graphics
	#php8.3-imagick php8.3-gd \
	# For SOAP integrations \
	#php8.3-soap \
	# For ZIP
	php8.3-zip

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install NodeJS + NPM
#RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
#	| gpg --dearmor -o /etc/apt/trusted.gpg.d/nodesource.gpg \
#	&&  echo "deb https://deb.nodesource.com/node_21.x nodistro main" \
#	> /etc/apt/sources.list.d/nodesource.list \
#	&&  apt-get update  &&  apt-get install --no-install-recommends -y nodejs
#
## Install Bun (alongside with NodeJS + NPM)
#RUN curl -fsSL https://bun.sh/install | bash  ||  cp /root/.bun/bin/bun /usr/local/bin/bun

# Clear caches
RUN apt-get full-upgrade -y  &&  apt-get clean  &&  apt-get -y autoremove \
	&&  rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Add user for the application
# Replace 1000 with actual User ID in your environment OS (command: id -u)
RUN groupadd -g 1000 www  &&  useradd -u 1000 -ms /bin/bash -g www www

# Copy application files
COPY --link . /var/www
#RUN chown www:www -R /var/www/storage

# Change current user to www
USER www

# Set working directory
WORKDIR /var/www
