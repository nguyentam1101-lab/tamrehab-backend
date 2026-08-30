#!/bin/sh
set -eu

mkdir -p "$(dirname "${DB_PATH}")"
if [ ! -f "${DB_PATH}" ]; then
  cp /opt/tamrehab/brain.db "${DB_PATH}"
fi

exec php -S "0.0.0.0:${PORT:-10000}" -t /var/www/html/public /var/www/html/public/router.php
