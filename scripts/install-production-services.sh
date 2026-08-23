#!/usr/bin/env bash

set -Eeuo pipefail

readonly repo_path='/home/invumo/invumo'
readonly environment_path="${repo_path}/.env"
readonly queue_source="${repo_path}/ops/systemd/invumo-queue.service"
readonly scheduler_source="${repo_path}/ops/cron/invumo-scheduler"
readonly user_unit_directory='/home/invumo/.config/systemd/user'
readonly queue_destination="${user_unit_directory}/invumo-queue.service"

if [[ ${EUID} -eq 0 || $(id -un) != 'invumo' ]]; then
    echo 'Install the production services as the unprivileged invumo user, without sudo.' >&2
    exit 1
fi

if [[ ! -f ${environment_path} ]] || grep -Fq '__INVUMO_' "${environment_path}"; then
    echo 'Bootstrap the production database and environment before installing services.' >&2
    exit 1
fi

for source_file in "${queue_source}" "${scheduler_source}"; do
    if [[ ! -f ${source_file} ]]; then
        echo "Missing production service definition: ${source_file}" >&2
        exit 1
    fi
done

if [[ $(loginctl show-user invumo --property=Linger --value) != 'yes' ]]; then
    echo 'systemd lingering must be enabled for the invumo user before installing the queue service.' >&2
    exit 1
fi

php "${repo_path}/artisan" migrate:status \
    --database=pgsql_schema \
    --no-interaction >/dev/null

install -d -m 0700 "${user_unit_directory}"
install -m 0600 "${queue_source}" "${queue_destination}"

systemctl --user daemon-reload
systemctl --user enable --now invumo-queue.service

scheduler_line="$(<"${scheduler_source}")"

if ! crontab -l 2>/dev/null | grep -Fqx "${scheduler_line}"; then
    temporary_crontab="$(mktemp)"
    trap 'rm -f "${temporary_crontab}"' EXIT

    crontab -l >"${temporary_crontab}" 2>/dev/null || true
    printf '\n%s\n' "${scheduler_line}" >>"${temporary_crontab}"
    crontab "${temporary_crontab}"
fi

systemctl --user is-enabled --quiet invumo-queue.service
systemctl --user is-active --quiet invumo-queue.service
crontab -l | grep -Fqx "${scheduler_line}"

echo 'Invumo user-level queue supervision and scheduler cron are installed and active.'
