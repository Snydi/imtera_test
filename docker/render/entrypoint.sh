#!/bin/sh
set -eu

cd /var/www/html/backend

if [ -n "${APP_KEY:-}" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:$APP_KEY"
fi

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
fi

if [ -z "${SANCTUM_STATEFUL_DOMAINS:-}" ] && [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export SANCTUM_STATEFUL_DOMAINS="${RENDER_EXTERNAL_HOSTNAME}"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

case "${1:-web}" in
    web)
        attempts=0
        until php artisan migrate --force; do
            attempts=$((attempts + 1))
            if [ "$attempts" -ge 12 ]; then
                echo "Database migrations failed after ${attempts} attempts." >&2
                exit 1
            fi
            echo "Database is not ready; retrying migrations in 5 seconds." >&2
            sleep 5
        done

        if [ -n "${SEED_USER_PASSWORD:-}" ]; then
            php artisan db:seed --force
        fi

        php artisan optimize
        envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
        exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
        ;;
    worker)
        php artisan optimize
        exec php artisan queue:work --sleep=3 --tries=3 --backoff=10 --timeout=600
        ;;
    *)
        exec "$@"
        ;;
esac
