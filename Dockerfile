# ServiceCloud AI — imagen única para desarrollo y nube
# Base: serversideup/php (PHP-FPM + Nginx, usuario www-data, puerto 8080, pdo_pgsql incluido)

############################################
# Base común
############################################
FROM serversideup/php:8.4-fpm-nginx AS base

USER root
# Extensiones extra que usa Laravel (formato de números/fechas en español, cálculos SLA)
RUN install-php-extensions intl bcmath
USER www-data

############################################
# Desarrollo (el código se monta como volumen desde tu PC)
############################################
FROM base AS development

# Solo para compañeros en Linux: igualar el UID/GID de www-data con el usuario del host.
# En Windows y macOS (Docker Desktop) se deja vacío y no hace nada.
ARG USER_ID=""
ARG GROUP_ID=""
USER root
RUN if [ -n "$USER_ID" ] && [ -n "$GROUP_ID" ]; then \
      docker-php-serversideup-set-id www-data "${USER_ID}:${GROUP_ID}"; \
    fi
USER www-data

############################################
# Producción (para desplegar en cada nube)
############################################
FROM base AS production

# Valores por defecto para la nube (12-factor): todo se puede sobrescribir con variables de entorno.
# AUTORUN ejecuta al arrancar: migrate --force, storage:link y config/route/view/event:cache.
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database

COPY --chown=www-data:www-data . /var/www/html

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
