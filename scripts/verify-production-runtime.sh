#!/usr/bin/env bash

set -Eeuo pipefail

readonly repo_path='/home/invumo/invumo'
readonly environment_path="${repo_path}/.env"

if [[ ! -f ${environment_path} ]]; then
    echo 'Production environment is missing.' >&2
    exit 1
fi

if grep -Fq '__INVUMO_' "${environment_path}"; then
    echo 'Production environment still contains bootstrap placeholders.' >&2
    exit 1
fi

if [[ $(stat -c '%a' "${environment_path}") != '600' ]]; then
    echo 'Production environment permissions must be 0600.' >&2
    exit 1
fi

cd "${repo_path}"

php artisan migrate:status --database=pgsql_schema --no-interaction >/dev/null
php artisan schedule:list --no-interaction >/dev/null

if ! systemctl is-active --quiet nginx.service; then
    echo 'Nginx is not active.' >&2
    exit 1
fi

if ! systemctl is-active --quiet php8.5-fpm.service; then
    echo 'PHP 8.5 FPM is not active.' >&2
    exit 1
fi

if ! systemctl is-active --quiet postgresql.service; then
    echo 'PostgreSQL is not active.' >&2
    exit 1
fi

if ! systemctl --user is-active --quiet invumo-queue.service; then
    echo 'Invumo queue worker is not active.' >&2
    exit 1
fi

scheduler_line="$(<"${repo_path}/ops/cron/invumo-scheduler")"

if ! crontab -l 2>/dev/null | grep -Fqx "${scheduler_line}"; then
    echo 'Invumo scheduler is not installed in the invumo user crontab.' >&2
    exit 1
fi

health_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' https://app.invumo.com/up)"
home_status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' https://app.invumo.com/)"

if [[ ${health_status} != '200' ]]; then
    echo "Public health check returned HTTP ${health_status}." >&2
    exit 1
fi

if [[ ${home_status} != '302' ]]; then
    echo "Application entry point returned HTTP ${home_status}, expected a login redirect." >&2
    exit 1
fi

echo 'Invumo production runtime verification passed.'
