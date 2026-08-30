FROM php:8.2-cli

# Render provides the listening port through $PORT. pdo_sqlite is compiled into
# the image so the PHP API can use SQLite without a separate database service.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY artifacts/api-server/public/ ./public/
COPY artifacts/api-server/data/brain.db ./data/brain.db

ENV DB_PATH=/var/lib/tamrehab/brain.db
RUN mkdir -p /var/lib/tamrehab \
    && cp ./data/brain.db "$DB_PATH" \
    && rm -rf ./data

EXPOSE 10000
CMD ["sh", "-c", "mkdir -p \"$(dirname \"$DB_PATH\")\" && php -S 0.0.0.0:${PORT:-10000} -t /var/www/html/public /var/www/html/public/router.php"]