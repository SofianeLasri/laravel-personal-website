#!/bin/bash

echo "Installing dependencies..."
composer install
php artisan key:generate

npm install
npm run build

echo "Migrating database..."
php artisan migrate

echo "Launching Supervisor..."
service supervisor start
supervisorctl start all

echo "Running Octane..."
php artisan octane:frankenphp --workers=1 --max-requests=1 --watch