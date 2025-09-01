#!/bin/bash

echo "🚀 Initializing Dusk testing environment..."

# Copier le fichier d'environnement Dusk
echo "📋 Setting up Dusk environment..."
cp .env.dusk.docker .env.dusk.local

# Installer les dépendances
echo "📦 Installing dependencies..."
composer install
npm install

# Générer la clé d'application si nécessaire
php artisan key:generate --env=dusk

# Attendre que MariaDB soit prête
echo "⏳ Waiting for MariaDB to be ready..."
until php artisan db:show --env=dusk 2>/dev/null; do
    echo "   MariaDB is not ready yet..."
    sleep 2
done
echo "✅ MariaDB is ready!"

# Exécuter les migrations
echo "🗃️ Running migrations..."
php artisan migrate --env=dusk --force

# Vérifier que les assets ont été compilés
echo "📦 Checking frontend assets..."
if [ ! -f "/app/public/build/manifest.json" ]; then
    echo "❌ ERROR: Frontend assets not built!"
    echo "   Please run 'npm run build' on your host machine before starting Dusk containers."
    echo "   This is required because building on WSL is too slow."
    exit 1
fi
echo "✅ Frontend assets found!"

# Installer Chrome Driver pour Dusk
echo "🌐 Installing Chrome Driver..."
php artisan dusk:chrome-driver --detect

# Démarrer Supervisor et les services
echo "🚀 Starting services..."
service supervisor start
supervisorctl start all
service cron start

# Garder le container en vie pour les tests
echo "✅ Dusk environment ready! Container staying alive for test execution..."
echo "   Run tests with: docker exec laravel.dusk php artisan dusk"

# Démarrer le serveur Laravel pour les tests Dusk
php artisan serve --host=0.0.0.0 --port=8000 --env=dusk