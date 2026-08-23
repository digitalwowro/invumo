#!/usr/bin/env bash

set -Eeuo pipefail

readonly repo_path='/home/invumo/invumo'
readonly env_path="${repo_path}/.env"
readonly runtime_placeholder='__INVUMO_RUNTIME_PASSWORD__'
readonly schema_placeholder='__INVUMO_SCHEMA_PASSWORD__'

if [[ ${EUID} -ne 0 ]]; then
    echo 'Run this one-time bootstrap with sudo.' >&2
    exit 1
fi

for executable in openssl php psql runuser; do
    if ! command -v "${executable}" >/dev/null 2>&1; then
        echo "Missing required executable: ${executable}" >&2
        exit 1
    fi
done

if [[ ! -f ${repo_path}/artisan || ! -f ${env_path} ]]; then
    echo "Expected Invumo installation at ${repo_path}." >&2
    exit 1
fi

if [[ $(stat -c '%U:%G' "${repo_path}") != 'invumo:invumo' ]]; then
    echo "Refusing to continue because ${repo_path} is not owned by invumo:invumo." >&2
    exit 1
fi

if ! grep -Fq "DB_PASSWORD=${runtime_placeholder}" "${env_path}" ||
    ! grep -Fq "DB_SCHEMA_PASSWORD=${schema_placeholder}" "${env_path}"; then
    echo 'The production environment has already been initialized or does not contain the expected placeholders.' >&2
    exit 1
fi

umask 077

runtime_password="$(openssl rand -hex 32)"
schema_password="$(openssl rand -hex 32)"

runuser -u postgres -- psql \
    --no-psqlrc \
    --set=ON_ERROR_STOP=1 \
    --dbname=postgres <<SQL
SELECT 'CREATE ROLE invumo_schema LOGIN'
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_schema') \gexec

SELECT 'CREATE ROLE invumo_runtime LOGIN'
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_runtime') \gexec

ALTER ROLE invumo_schema WITH
    LOGIN PASSWORD '${schema_password}'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

ALTER ROLE invumo_runtime WITH
    LOGIN PASSWORD '${runtime_password}'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

SELECT 'CREATE DATABASE invumo OWNER invumo_schema ENCODING ''UTF8'' TEMPLATE template0'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'invumo') \gexec

ALTER DATABASE invumo OWNER TO invumo_schema;
REVOKE ALL ON DATABASE invumo FROM PUBLIC;
GRANT CONNECT ON DATABASE invumo TO invumo_schema, invumo_runtime;

\connect invumo

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO invumo_schema;
GRANT USAGE ON SCHEMA public TO invumo_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE invumo_schema IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO invumo_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE invumo_schema IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO invumo_runtime;
SQL

RUNTIME_PASSWORD="${runtime_password}" \
SCHEMA_PASSWORD="${schema_password}" \
php -r '
    $path = "/home/invumo/invumo/.env";
    $contents = file_get_contents($path);
    $replacements = [
        "__INVUMO_RUNTIME_PASSWORD__" => getenv("RUNTIME_PASSWORD"),
        "__INVUMO_SCHEMA_PASSWORD__" => getenv("SCHEMA_PASSWORD"),
    ];

    foreach ($replacements as $placeholder => $secret) {
        if (substr_count($contents, $placeholder) !== 1 || $secret === false || $secret === "") {
            fwrite(STDERR, "Refusing an unsafe environment replacement.\n");
            exit(1);
        }

        $contents = str_replace($placeholder, $secret, $contents);
    }

    $temporaryPath = $path.".bootstrap";

    if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write the production environment.\n");
        exit(1);
    }

    chmod($temporaryPath, 0600);
    rename($temporaryPath, $path);
'

chown invumo:invumo "${env_path}"
chmod 0600 "${env_path}"

runuser -u invumo -- php "${repo_path}/artisan" key:generate --force --no-interaction
runuser -u invumo -- php "${repo_path}/artisan" config:clear --no-interaction
runuser -u invumo -- php "${repo_path}/artisan" migrate \
    --database=pgsql_schema \
    --force \
    --no-interaction

runuser -u postgres -- psql \
    --no-psqlrc \
    --set=ON_ERROR_STOP=1 \
    --dbname=invumo <<'SQL'
REVOKE ALL PRIVILEGES ON TABLE public.migrations FROM invumo_runtime;
SQL

runuser -u invumo -- php "${repo_path}/artisan" config:cache --no-interaction
chmod 0600 "${repo_path}/bootstrap/cache/config.php"
runuser -u invumo -- php "${repo_path}/artisan" view:cache --no-interaction

PGPASSWORD="${runtime_password}" psql \
    --no-psqlrc \
    --set=ON_ERROR_STOP=1 \
    --host=127.0.0.1 \
    --username=invumo_runtime \
    --dbname=invumo \
    --command='SELECT 1;' >/dev/null

unset runtime_password schema_password RUNTIME_PASSWORD SCHEMA_PASSWORD PGPASSWORD

echo 'Invumo production database, roles, environment secrets, migrations, and Laravel caches are ready.'
