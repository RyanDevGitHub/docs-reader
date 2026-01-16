#!/bin/sh
set -e

# On attend quelques secondes pour être sûr que PostgreSQL est prêt
echo "En attente de la base de données..."
sleep 3

# 1. Exécuter les migrations sans poser de question
echo "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# 2. Charger les partenaires (fixtures) sans supprimer ce qui existe déjà
echo "Chargement des partenaires..."
php bin/console doctrine:fixtures:load --no-interaction --append

# 3. Lancer la commande principale du conteneur (souvent php-fpm ou frankenphp)
exec "$@"