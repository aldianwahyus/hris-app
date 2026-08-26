FROM php:8.4-fpm-alpine

# libpq-dev menggantikan postgresql-dev (nama lama, sudah tidak tersedia
# di Alpine terbaru). $PHPIZE_DEPS berisi tool kompilasi yang dibutuhkan
# pecl — tanpa itu "pecl install redis" gagal.
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpq-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        linux-headers \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
    && apk add --no-cache \
        libpq \
        libzip \
        icu-libs \
        oniguruma \
        supervisor \
        libpng \
        freetype \
        libjpeg-turbo \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl \
        sockets \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Catatan ekstensi:
# - bcmath : perhitungan finansial presisi tinggi (gaji, iuran, PPh 21) — DB-001 D-8
# - pcntl  : dibutuhkan Laravel Horizon (tidak tersedia di Windows, tersedia di sini)
# - sockets: pendukung Horizon/Redis
# - gd     : generate gambar captcha login (mews/captcha) — hasil GD dipilih di
#            config/captcha.php agar tidak butuh Imagick, ekstensi ini yang minimal

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Berjalan sebagai root agar tidak bentrok izin dengan bind mount Windows.
# Pada produksi, gunakan user non-root sesuai ARCH-001 §6.
EXPOSE 9000
CMD ["php-fpm"]
