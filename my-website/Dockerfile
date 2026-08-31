FROM php:8.2-cli

# Cài SQLite + pdo_sqlite (đồng bộ với Dockerfile ở root)
RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy backend + frontend vào cùng document root (giống root Dockerfile)
COPY backend/ /var/www/html/public/
COPY frontend/ /var/www/html/public/
COPY admin.php /var/www/html/public/admin.php
COPY thanh-toan.php /var/www/html/public/thanh-toan.php
COPY webhook-sepay.php /var/www/html/public/webhook-sepay.php
COPY backend/brain.db /opt/tamrehab/brain.db

# start.sh nằm ở thư mục cha khi build từ root, nên copy nếu tồn tại
# Khi build từ my-website/, bạn cần COPY ../start.sh hoặc dùng root Dockerfile

EXPOSE 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html/public", "/var/www/html/public/router.php"]
