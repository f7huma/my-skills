# syntax=docker/dockerfile:1.7
# صورة واحدة للتطبيق تُستخدم لثلاث خدمات: web و worker و scheduler (يحددها APP_ROLE)

# ---------- 1) بناء الواجهة
FROM node:22.23-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
COPY public ./public
RUN npx tsc --noEmit -p tsconfig.json && npx vite build

# ---------- 2) اعتماديات PHP
FROM composer:2.8 AS vendor
WORKDIR /src
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- 3) وقت التشغيل
FROM dunglas/frankenphp:1-php8.3-bookworm AS runtime
RUN install-php-extensions pdo_pgsql pgsql intl zip gd opcache pcntl sodium bcmath \
 && apt-get update && apt-get install -y --no-install-recommends curl gnupg ca-certificates tar tini \
 && install -d /usr/share/postgresql-common/pgdg \
 && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
 && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
 && apt-get update && apt-get install -y --no-install-recommends postgresql-client-17 \
 && apt-get purge -y curl gnupg && apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/* \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/app/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/app/Caddyfile /etc/frankenphp/Caddyfile
WORKDIR /app
COPY --from=vendor /src /app
COPY --from=assets /src/public/build /app/public/build
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
# مستخدم غير جذري؛ لا يملك صلاحية الكتابة على الكود (لا يعدّل التطبيق كوده)
RUN useradd -r -u 10001 -g www-data app \
 && mkdir -p /var/lib/humaidan/files /var/lib/humaidan/backups /var/lib/humaidan/restored /app/storage/framework/{cache,sessions,views} /app/storage/logs /app/bootstrap/cache \
 && chown -R app:www-data /var/lib/humaidan /app/storage /app/bootstrap/cache /data /config \
 && chmod 0700 /var/lib/humaidan/files /var/lib/humaidan/backups \
 && chmod +x /usr/local/bin/entrypoint \
 && rm -rf /app/tests /app/node_modules /app/.env /app/storage/logs/*.log
USER app
ENV APP_ENV=production APP_DEBUG=false SERVER_NAME=:8080 \
    ATTACHMENTS_ROOT=/var/lib/humaidan/files BACKUP_PATH=/var/lib/humaidan/backups LOG_CHANNEL=stderr
EXPOSE 8080
ENTRYPOINT ["tini", "--", "entrypoint"]
CMD ["web"]
