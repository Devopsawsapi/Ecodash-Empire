#!/bin/bash
set -e

php setup.php

echo "🚀 Démarrage du serveur Laravel sur le port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}