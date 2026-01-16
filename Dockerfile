# --- ÉTAPE 1 : INSTALLATION DES DÉPENDANCES (BUILDER) ---
FROM php:8.2-apache AS builder

# Installer les outils nécessaires pour Composer
RUN apt-get update && apt-get install -y unzip zip git libzip-dev libpq-dev libicu-dev

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Copier uniquement les fichiers de dépendances
COPY composer.json composer.lock ./

# Installation optimisée pour GitHub Actions (sans dev, sans scripts, sans autoloader lourd)
RUN COMPOSER_MEMORY_LIMIT=-1 composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

# Copier le reste du code
COPY . .

# Générer l'autoloader final de manière autoritaire (très rapide à l'exécution)
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# --- ÉTAPE 2 : IMAGE FINALE (LÉGÈRE) ---
FROM php:8.2-apache

# Activer les modules Apache
RUN a2enmod rewrite

# Configuration Apache (Regroupée pour gagner des couches/layers)
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && echo "<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>" >> /etc/apache2/apache2.conf

# Installer uniquement les extensions PHP nécessaires (sans les outils de build)
RUN apt-get update && apt-get install -y libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo pdo_pgsql zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# On récupère uniquement le projet propre depuis l'étape "builder"
COPY --from=builder /var/www/html .

# Droits pour Symfony (Regroupés)
RUN mkdir -p var/cache var/log && chown -R www-data:www-data var

EXPOSE 80
