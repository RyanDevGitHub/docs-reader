# Utiliser une image PHP FPM légère
FROM php:8.2-fpm-alpine

# Étape 1 : Installer les dépendances système et extensions PHP
RUN apk update && apk add --no-cache \
    git \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    zip \
    unzip \
    && docker-php-ext-install \
       pdo \
       pdo_pgsql \
       intl \
       zip \
       bcmath \
       opcache

# Étape 2 : Définir le répertoire de travail
WORKDIR /var/www/html

# Étape 3 : Copier le code de l'application
COPY . .

# Étape 4 : Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Étape 5 : Installer les dépendances PHP via Composer
RUN composer install --optimize-autoloader

# Étape 6 : Changer les permissions pour FPM (www-data)
RUN chown -R www-data:www-data var public

# Étape 7 : Exposer le port FPM
EXPOSE 9000
