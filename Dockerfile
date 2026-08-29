FROM php:8.2-cli

# Cập nhật và cài đặt SQLite và extension
RUN apt-get update \
    && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy toàn bộ thư mục backend vào container
COPY backend/ /var/www/html/backend/

# Đặt thư mục làm việc
WORKDIR /var/www/html/backend/

# Expose cổng 10000
EXPOSE 10000

# Chạy PHP server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
