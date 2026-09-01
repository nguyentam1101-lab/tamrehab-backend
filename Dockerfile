FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
