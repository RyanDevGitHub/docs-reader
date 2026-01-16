# Base PHP avec Apache
FROM php:8.2-apache

# Activer mod_rewrite pour .htaccess
RUN a2enmod rewrite

# Modifier la racine vers /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Ajouter bloc Directory pour autoriser .htaccess
RUN echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
    </Directory>" >> /etc/apache2/apache2.conf

# Installer extensions PHP nécessaires
RUN apt-get update && apt-get install -y unzip zip git libicu-dev libonig-dev libxml2-dev libzip-dev libpq-dev curl gnupg \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql opcache zip

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Définir le dossier de travail
WORKDIR /var/www/html

# Copier tout le projet
COPY . .

# Installer dépendances PHP avec Composer
# On utilise -1 pour dire à PHP de ne pas limiter la RAM pour Composer
RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction --prefer-dist --no-scripts

# Droits pour Symfony
RUN mkdir -p var && chown -R www-data:www-data var

# Exposer le port HTTP
EXPOSE 80
