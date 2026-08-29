#!/usr/bin/env bash

set -Eeuo pipefail

readonly repo_path='/home/invumo/invumo'
readonly environment_path="${repo_path}/.env"

if [[ ${EUID} -ne 0 ]]; then
    echo 'Create the isolated PostgreSQL test database with sudo.' >&2
    exit 1
fi

if [[ ! -f ${environment_path} ]] || grep -Fq '__INVUMO_' "${environment_path}"; then
    echo 'Bootstrap the production database roles before creating the isolated test database.' >&2
    exit 1
fi

runuser -u postgres -- psql \
    --no-psqlrc \
    --set=ON_ERROR_STOP=1 \
    --dbname=postgres <<'SQL'
SELECT 'CREATE ROLE invumo_dispatcher NOLOGIN'
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'invumo_dispatcher') \gexec

ALTER ROLE invumo_dispatcher WITH
    NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

GRANT invumo_dispatcher TO invumo_runtime
    WITH ADMIN FALSE, INHERIT FALSE, SET TRUE;

SELECT 'CREATE DATABASE invumo_test OWNER invumo_schema ENCODING ''UTF8'' TEMPLATE template0'
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'invumo_test') \gexec

ALTER DATABASE invumo_test OWNER TO invumo_schema;
REVOKE ALL ON DATABASE invumo_test FROM PUBLIC;
GRANT CONNECT ON DATABASE invumo_test TO invumo_schema, invumo_runtime;

\connect invumo_test

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO invumo_schema;
GRANT USAGE ON SCHEMA public TO invumo_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE invumo_schema IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO invumo_runtime;

ALTER DEFAULT PRIVILEGES FOR ROLE invumo_schema IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO invumo_runtime;
SQL

echo 'The isolated invumo_test database is ready. No production data or schema was changed.'
