FROM php:8.2-apache

# Cài đặt extension SQLite3 và PDO SQLite
RUN apt-get update && apt-get install -y --no-install-recommends \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Copy toàn bộ backend vào document root Apache
COPY backend/ /var/www/html/backend/

# Copy database vào thư mục làm việc
COPY backend/brain.db /var/www/html/backend/brain.db

# Expose port 10000
EXPOSE 10000

# Chạy PHP server trên port 10000
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html/backend/"]
