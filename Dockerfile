# Ganti versi PHP bila perlu: docker compose build --build-arg PHP_VERSION=8.5
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# pdo_sqlite, sqlite3, mbstring, fileinfo, tokenizer, dll sudah bawaan image php resmi.
# Yang perlu ditambah: zip (untuk membaca CV .docx), bcmath, pcntl, dan alat bantu composer.
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates git unzip libzip-dev libsqlite3-dev \
 && update-ca-certificates \
 && docker-php-ext-install zip bcmath pcntl pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.enable_cli=1' \
    'opcache.validate_timestamps=1' \
    'opcache.revalidate_freq=2' \
    'curl.cainfo=/etc/ssl/certs/ca-certificates.crt' \
    'openssl.cafile=/etc/ssl/certs/ca-certificates.crt' \
    > /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000
