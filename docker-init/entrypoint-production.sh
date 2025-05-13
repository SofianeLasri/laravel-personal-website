#!/bin/bash

echo "Installing dependencies..."
composer install
php artisan key:generate

npm install
npm run build:ssr

echo "Migrating database..."
php artisan migrate

echo "Launching Supervisor..."
service supervisor start
supervisorctl start all

echo "Running SSR..."
php artisan inertia:start-ssr &

echo "Running Octane..."
php artisan octane:frankenphp &