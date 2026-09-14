#!/bin/sh
set -eu

# Keep the MCP connection tied to the Film Efiko frontend project. The project
# owns the WordPress URL and EasyHeadless key; this launcher only forwards them
# to the MCP process and never stores a credential in this repository.
FILM_EFIKO_DIR="${FILM_EFIKO_PROJECT_DIR:-/Users/bleckcorp/IdeaProjects/film-efiko-web}"
EASYHEADLESS_SERVER="${EASYHEADLESS_SERVER:-/Users/bleckcorp/IdeaProjects/EasyHeadless/mcp-server/dist/index.js}"

ENV_FILE=""
for candidate in \
    "$FILM_EFIKO_DIR/.env.local" \
    "$FILM_EFIKO_DIR/.env.production.local" \
    "$FILM_EFIKO_DIR/.env.production" \
    "$FILM_EFIKO_DIR/.env"
do
    if [ -f "$candidate" ]; then
        ENV_FILE="$candidate"
        break
    fi
done

if [ -z "$ENV_FILE" ]; then
    echo "No Film Efiko environment file found in $FILM_EFIKO_DIR." >&2
    echo "Create .env.local (or .env) with EASYHEADLESS_API_URL and EASYHEADLESS_API_KEY." >&2
    exit 1
fi

# Read only the two MCP variables. This intentionally does not source the file,
# so arbitrary environment code cannot execute when the MCP starts.
read_env_value() {
    key="$1"
    sed -n "s/^${key}=//p" "$ENV_FILE" | tail -n 1 | sed 's/^\"//; s/\"$//; s/^\x27//; s/\x27$//'
}

EASYHEADLESS_API_URL="$(read_env_value EASYHEADLESS_API_URL)"
EASYHEADLESS_API_KEY="$(read_env_value EASYHEADLESS_API_KEY)"

if [ -z "$EASYHEADLESS_API_URL" ]; then
    echo "EASYHEADLESS_API_URL is missing from $ENV_FILE." >&2
    exit 1
fi

if [ -z "$EASYHEADLESS_API_KEY" ]; then
    echo "EASYHEADLESS_API_KEY is missing from $ENV_FILE." >&2
    exit 1
fi

case "$EASYHEADLESS_API_URL" in
    https://filmefiko.com/wp-json/easyheadless/v1|https://filmefiko.com/wp-json/easyheadless/v1/)
        ;;
    *)
        echo "Refusing to start: Film Efiko MCP URL is not filmefiko.com ($EASYHEADLESS_API_URL)." >&2
        exit 1
        ;;
esac

export EASYHEADLESS_API_URL EASYHEADLESS_API_KEY
exec node "$EASYHEADLESS_SERVER"
