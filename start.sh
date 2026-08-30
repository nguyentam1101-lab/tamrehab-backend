#!/bin/sh
set -eu

mkdir -p "$(dirname "${DB_PATH}")"
if [ ! -f "${DB_PATH}" ]; then
  cp /opt/tamrehab/brain.db "${DB_PATH}"
fi

# Keep the admin UI and API on the same SQLite file.
rm -f /var/www/html/public/brain.db
ln -s "${DB_PATH}" /var/www/html/public/brain.db

# The frontend is served by this backend, so it should call the current origin.
for page in /var/www/html/public/admin.html /var/www/html/public/thanh-toan.html; do
  if [ -f "${page}" ]; then
    sed -i "s#const API_URL = 'https://backend-tamrehab.onrender.com';#const API_URL = window.location.origin;#g" "${page}"
  fi
done

# Keep the legacy admin page compatible with strict_types and integer IDs.
php -r '$path="/var/www/html/public/admin.php"; $source=file_get_contents($path); $source=str_replace("function h(?string \$value): string", "function h(mixed \$value): string", $source); $source=str_replace("return htmlspecialchars(\$value ?? ''' ''', ENT_QUOTES", "return htmlspecialchars((string) (\$value ?? ''' '''), ENT_QUOTES", $source); file_put_contents($path, $source);'

exec php -S "0.0.0.0:${PORT:-10000}" -t /var/www/html/public /var/www/html/public/router.php
