#!/bin/sh
set -e

# Permissions runtime (storage est un volume, donc à refaire à chaque démarrage)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Génère la clé d'application si elle n'existe pas encore dans .env
if ! grep -q "^APP_KEY=base64" /var/www/html/.env 2>/dev/null; then
    php artisan key:generate --force
fi

# Lien symbolique storage (idempotent, sans erreur s'il existe déjà)
php artisan storage:link --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
