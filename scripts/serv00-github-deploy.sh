#!/usr/bin/env bash
set -euo pipefail

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

[[ "${SERV00_HOST:-}" =~ ^[A-Za-z0-9.-]+$ ]] || fail 'SERV00_HOST is missing or invalid.'
[[ "${SERV00_USERNAME:-}" =~ ^[A-Za-z0-9_]+$ ]] || fail 'SERV00_USERNAME is missing or invalid.'
[[ "${SERV00_DOMAIN:-}" =~ ^[A-Za-z0-9.-]+$ ]] || fail 'SERV00_DOMAIN is missing or invalid.'
[[ -n "${SERV00_SSH_PRIVATE_KEY:-}" ]] || fail 'SERV00_SSH_PRIVATE_KEY is missing.'

DEPLOY_SHA="${SERV00_DEPLOY_SHA:-${GITHUB_SHA:-}}"
PUBLIC_DOMAIN="${SERV00_PUBLIC_DOMAIN:-$SERV00_DOMAIN}"
[[ "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'Deployment commit SHA is missing or invalid.'
[[ "$PUBLIC_DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || fail 'SERV00_PUBLIC_DOMAIN is invalid.'

SSH_DIR="$(mktemp -d "${RUNNER_TEMP:-/tmp}/course-system-serv00.XXXXXX")"
SSH_KEY="$SSH_DIR/deploy_key"
KNOWN_HOSTS="$SSH_DIR/known_hosts"

cleanup() {
    rm -rf "$SSH_DIR"
}
trap cleanup EXIT

is_public_key() {
    [[ "$1" == ssh-* || "$1" == ecdsa-* || "$1" == sk-* || "$1" == *'BEGIN PUBLIC KEY'* ]]
}

write_private_key() {
    local key_material="$1"
    key_material="${key_material//$'\r'/}"

    if [[ "$key_material" != *$'\n'* && "$key_material" == *'\n'* ]]; then
        key_material="${key_material//\\n/$'\n'}"
    fi

    is_public_key "$key_material" && fail 'SERV00_SSH_PRIVATE_KEY contains a public key. Store the file without the .pub suffix.'

    printf '%s\n' "$key_material" > "$SSH_KEY"
    chmod 600 "$SSH_KEY"

    if ssh-keygen -y -P '' -f "$SSH_KEY" >/dev/null 2>&1; then
        return
    fi

    local decoded_key
    if decoded_key="$(printf '%s' "$key_material" | base64 --decode 2>/dev/null)"; then
        decoded_key="${decoded_key//$'\r'/}"
        is_public_key "$decoded_key" && fail 'SERV00_SSH_PRIVATE_KEY decodes to a public key. Encode the file without the .pub suffix.'
        printf '%s\n' "$decoded_key" > "$SSH_KEY"

        if ssh-keygen -y -P '' -f "$SSH_KEY" >/dev/null 2>&1; then
            return
        fi
    fi

    fail 'SERV00_SSH_PRIVATE_KEY is not a valid unencrypted private key. Store its complete raw or Base64-encoded contents.'
}

write_private_key "$SERV00_SSH_PRIVATE_KEY"
ssh-keyscan -T 10 -H "$SERV00_HOST" > "$KNOWN_HOSTS"
chmod 600 "$KNOWN_HOSTS"
[[ -s "$KNOWN_HOSTS" ]] || fail 'Unable to obtain the Serv00 SSH host key.'

APP_ROOT="/usr/home/$SERV00_USERNAME/domains/$SERV00_DOMAIN/application"
printf -v app_root_quoted '%q' "$APP_ROOT"
printf -v deploy_sha_quoted '%q' "$DEPLOY_SHA"

ssh \
    -i "$SSH_KEY" \
    -o BatchMode=yes \
    -o IdentitiesOnly=yes \
    -o StrictHostKeyChecking=yes \
    -o UserKnownHostsFile="$KNOWN_HOSTS" \
    "$SERV00_USERNAME@$SERV00_HOST" \
    "/usr/local/bin/bash -s -- $app_root_quoted $deploy_sha_quoted" <<'REMOTE'
set -euo pipefail

APP_ROOT="$1"
DEPLOY_SHA="$2"
MAINTENANCE_STARTED=0

report_failure() {
    status=$?
    if [[ "$status" -ne 0 && "$MAINTENANCE_STARTED" -eq 1 ]]; then
        echo 'Deployment failed after maintenance mode started; the site remains in maintenance mode.' >&2
    fi
}
trap report_failure EXIT

[[ "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid deployment commit SHA.' >&2; exit 1; }
[[ -d "$APP_ROOT/.git" ]] || { echo "Git checkout not found: $APP_ROOT" >&2; exit 1; }

cd "$APP_ROOT"
git diff --quiet && git diff --cached --quiet || {
    echo 'Tracked production files contain local changes; deployment stopped.' >&2
    exit 1
}

if [[ -f vendor/autoload.php ]]; then
    /usr/local/bin/php85 artisan down --retry=60 --refresh=15
    MAINTENANCE_STARTED=1
fi

git fetch --prune origin main
git checkout main
git merge --ff-only "$DEPLOY_SHA"
[[ "$(git rev-parse HEAD)" == "$DEPLOY_SHA" ]] || {
    echo 'Server checkout does not match the tested commit.' >&2
    exit 1
}

SKIP_GIT_PULL=1 /usr/local/bin/bash scripts/serv00-deploy.sh
MAINTENANCE_STARTED=0
/usr/local/bin/php85 artisan system:check --wait=75
REMOTE

curl \
    --fail \
    --show-error \
    --silent \
    --retry 3 \
    --retry-delay 5 \
    --max-time 30 \
    "https://$PUBLIC_DOMAIN/login" >/dev/null

echo "Deployment verified: https://$PUBLIC_DOMAIN/login"
