#!/usr/bin/env bash

set -Eeuo pipefail

readonly repo_path='/home/invumo/invumo'
readonly environment_path="$repo_path/.env"

if [[ $EUID -eq 0 || $(id -un) != 'invumo' ]]; then
    echo 'Run this as the unprivileged invumo user.' >&2
    exit 1
fi

if [[ ! -f $environment_path ]] || grep -Fq '__INVUMO_' "$environment_path"; then
    echo 'The initialized production environment is required before configuring email.' >&2
    exit 1
fi

readonly user_runtime_path="/run/user/$(id -u)"
export XDG_RUNTIME_DIR="${XDG_RUNTIME_DIR:-$user_runtime_path}"
export DBUS_SESSION_BUS_ADDRESS="${DBUS_SESSION_BUS_ADDRESS:-unix:path=$XDG_RUNTIME_DIR/bus}"

if [[ ! -d $XDG_RUNTIME_DIR || ! -S $XDG_RUNTIME_DIR/bus ]]; then
    echo 'The invumo systemd user manager is unavailable; verify that user lingering is active.' >&2
    exit 1
fi

read -r -p 'ZeptoMail SMTP server [smtp.zeptomail.com]: ' smtp_host
if [[ -z $smtp_host ]]; then
    smtp_host='smtp.zeptomail.com'
fi

read -r -p 'ZeptoMail SMTP username [emailapikey]: ' smtp_username
if [[ -z $smtp_username ]]; then
    smtp_username='emailapikey'
fi

read -r -s -p 'ZeptoMail SMTP password: ' smtp_password
printf '\n'

read -r -p 'Verified sender email address: ' sender_address
read -r -p 'Recipient for the configuration test: ' test_recipient

if [[ ! $smtp_host =~ ^[A-Za-z0-9.-]+$ ]]; then
    echo 'The SMTP server name is invalid.' >&2
    exit 1
fi

if [[ -z $smtp_username || -z $smtp_password ]]; then
    echo 'SMTP username and password are required.' >&2
    exit 1
fi

if [[ ! $sender_address =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] ||
    [[ ! $test_recipient =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]]; then
    echo 'The sender and test-recipient addresses must be valid email addresses.' >&2
    exit 1
fi

umask 077
environment_backup="$(mktemp /home/invumo/.invumo-env-zeptomail.XXXXXX)"
install -m 0600 "$environment_path" "$environment_backup"
configuration_complete=false

restore_environment() {
    if [[ $configuration_complete != true ]]; then
        install -m 0600 "$environment_backup" "$environment_path"
        (
            cd "$repo_path"
            umask 077
            php artisan config:cache --no-interaction >/dev/null
        )
        chmod 0600 "$repo_path/bootstrap/cache/config.php"
    fi

    rm -f "$environment_backup"
    unset smtp_password ZEPTO_SMTP_PASSWORD
}

trap restore_environment EXIT

printf '%s\0%s\0%s\0%s\0' "$smtp_host" "$smtp_username" "$smtp_password" "$sender_address" | php -r '
    $path = "/home/invumo/invumo/.env";
    $contents = file_get_contents($path);
    $quote = static fn (string $value): string => "\"".addcslashes($value, "\\\"\n\r\t$")."\"";
    $input = explode("\0", stream_get_contents(STDIN));

    if (count($input) !== 5 || array_pop($input) !== "") {
        fwrite(STDERR, "Unable to read the email settings securely.\n");
        exit(1);
    }

    [$host, $username, $password, $fromAddress] = $input;
    $updates = [
        "MAIL_MAILER" => "smtp",
        "MAIL_SCHEME" => "smtp",
        "MAIL_HOST" => $host,
        "MAIL_PORT" => "587",
        "MAIL_TIMEOUT" => "10",
        "MAIL_USERNAME" => $username,
        "MAIL_PASSWORD" => $password,
        "MAIL_FROM_ADDRESS" => $fromAddress,
        "MAIL_FROM_NAME" => "Invumo",
    ];

    foreach ($updates as $key => $value) {
        if ($value === false || $value === "") {
            fwrite(STDERR, "A required email setting is empty.\n");
            exit(1);
        }

        $pattern = "/^".preg_quote($key, "/")."=.*$/m";

        if (preg_match_all($pattern, $contents) !== 1) {
            fwrite(STDERR, "Expected exactly one environment entry for {$key}.\n");
            exit(1);
        }

        $contents = preg_replace($pattern, $key."=".$quote((string) $value), $contents);
    }

    $temporaryPath = $path.".zeptomail";

    if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write the email configuration.\n");
        exit(1);
    }

    chmod($temporaryPath, 0600);
    rename($temporaryPath, $path);
'

unset smtp_password

(
    cd "$repo_path"
    php artisan config:clear --no-interaction >/dev/null
    umask 077
    php artisan config:cache --no-interaction >/dev/null
)
chmod 0600 "$repo_path/bootstrap/cache/config.php"

TEST_RECIPIENT="$test_recipient" php -r '
    require "/home/invumo/invumo/vendor/autoload.php";
    $application = require "/home/invumo/invumo/bootstrap/app.php";
    $application->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    Illuminate\Support\Facades\Mail::raw(
        "Invumo successfully connected to ZeptoMail over authenticated TLS.",
        static function (Illuminate\Mail\Message $message): void {
            $message
                ->to((string) getenv("TEST_RECIPIENT"))
                ->subject("Invumo ZeptoMail configuration test");
        },
    );
'

systemctl --user restart invumo-queue.service
configuration_complete=true

echo 'ZeptoMail SMTP is configured over TLS and the test message was accepted for delivery.'
