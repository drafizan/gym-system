#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

echo "Starting deployment in $APP_DIR"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Deployment failed: this folder is not a Git work tree."
    exit 1
fi

BRANCH="$(git rev-parse --abbrev-ref HEAD)"

if [[ "$BRANCH" == "HEAD" ]]; then
    echo "Deployment failed: repository is in detached HEAD state."
    exit 1
fi

echo "Fetching latest changes from origin..."
git fetch --prune origin

echo "Pulling origin/$BRANCH with fast-forward only..."
git pull --ff-only origin "$BRANCH"

echo "Installing PHP dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "Running database migrations..."
php artisan migrate --force

echo "Installing frontend dependencies..."
npm ci

echo "Building frontend assets..."
npm run build

echo "Refreshing Laravel caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Deployment completed successfully."
