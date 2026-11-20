#!/bin/sh
set -e

APP_DIR=/var/www/html
cd "$APP_DIR"

# ─── 1. Dossiers et permissions ──────────────────────────────
# storage est un volume : il peut être vide au premier lancement
# et les permissions doivent être refaites à chaque démarrage.
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/public \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ─── 2. Vérification de APP_KEY ──────────────────────────────
# Le .env n'est pas dans l'image : APP_KEY doit venir de env_file.
if [ -z "$APP_KEY" ]; then
    echo "ERREUR : APP_KEY n'est pas défini dans le .env du serveur." >&2
    echo "Génère-la en local avec : php artisan key:generate --show" >&2
    exit 1
fi

# ─── 3. Lien symbolique storage (idempotent) ─────────────────
php artisan storage:link --force

# ─── 4. Caches Laravel ───────────────────────────────────────
# On repart de zéro pour ne jamais garder un cache périmé
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ─── 5. Migrations (optionnel) ───────────────────────────────
# Décommente si tu veux migrer automatiquement au démarrage.
# Attention : à éviter si plusieurs conteneurs démarrent en même temps.
# php artisan migrate --force

# ─── 6. Lancement de supervisord ─────────────────────────────
exec "$@"
