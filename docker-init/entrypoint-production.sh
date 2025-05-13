#!/bin/bash

php artisan key:generate

echo "Migrating database..."
php artisan migrate --force

echo "Launching Supervisor..."
service supervisor start
supervisorctl start all

echo "Running SSR..."
php artisan inertia:start-ssr &

echo "Running Octane..."
php artisan octane:frankenphp &