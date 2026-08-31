FROM php:8.2-cli

ARG CACHE_BUST=1
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev sqlite3 && docker-php-ext-install pdo pdo_sqlite && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY my-website/backend/ /var/www/html/public/
COPY my-website/frontend/ /var/www/html/public/
COPY my-website/admin.php /var/www/html/public/admin.php
COPY my-website/thanh-toan.php /var/www/html/public/thanh-toan.php
COPY my-website/webhook-sepay.php /var/www/html/public/webhook-sepay.php
COPY my-website/backend/brain.db /opt/tamrehab/brain.db
COPY start.sh /usr/local/bin/tamrehab-start.sh
RUN chmod +x /usr/local/bin/tamrehab-start.sh

ENV DB_PATH=/var/data/brain.db
EXPOSE 10000
CMD ["sh", "/usr/local/bin/tamrehab-start.sh"]
