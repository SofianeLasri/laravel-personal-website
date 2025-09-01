#!/bin/bash

echo "Installing dependencies..."
composer install
php artisan key:generate

npm install

# Vérifier que les assets ont été compilés (optionnel en dev, mais recommandé)
if [ ! -f "/app/public/build/manifest.json" ]; then
    echo "⚠️  WARNING: Frontend assets not built!"
    echo "   Run 'npm run build' or 'npm run dev' on your host machine for better performance."
    # Note: pas d'exit ici car en dev on peut utiliser npm run dev
fi

echo "Migrating database..."
php artisan migrate

echo "Launching Supervisor..."
service supervisor start
supervisorctl start all
service cron start

echo "Running Octane..."
php artisan octane:frankenphp --workers=1 --max-requests=1 --watch