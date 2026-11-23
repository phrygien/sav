#!/bin/sh
set -e

APP_DIR=/var/www/html

# Arborescence storage (recréée à chaque démarrage, sans volume)
mkdir -p \
    "$APP_DIR/storage/app/public" \
    "$APP_DIR/storage/framework/cache/data" \
    "$APP_DIR/storage/framework/sessions" \
    "$APP_DIR/storage/framework/views" \
    "$APP_DIR/storage/logs" \
    "$APP_DIR/bootstrap/cache"

# Permissions
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# APP_KEY: fournie via env_file (pas de .env dans le conteneur)
if [ -z "$APP_KEY" ]; then
    echo "ERREUR: APP_KEY n'est pas défini dans le .env du docker-compose."
    echo "Génère-en une avec: echo \"base64:\$(openssl rand -base64 32)\""
    exit 1
fi

# Lien symbolique storage (idempotent)
php artisan storage:link --force

php artisan config:cache
php artisan route:cache

# view:cache non bloquant: si une vue ne compile pas (ex: composant Flux Pro
# manquant), on log l'erreur et on démarre quand même. Les vues non
# compilées le seront à la volée à la première requête.
if ! php artisan view:cache; then
    echo "WARN: view:cache a échoué, démarrage sans cache complet des vues."
    php artisan view:clear || true
fi

exec "$@"
