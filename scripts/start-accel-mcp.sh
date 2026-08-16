#!/bin/sh
set -eu

ACCEL_ENV_FILE="/Users/bleckcorp/IdeaProjects/AccelSkillsHub/.env"
EASYHEADLESS_SERVER="/Users/bleckcorp/IdeaProjects/EasyHeadless/mcp-server/dist/index.js"

if [ ! -f "$ACCEL_ENV_FILE" ]; then
    echo "AccelSkillsHub .env file was not found." >&2
    exit 1
fi

EASYHEADLESS_API_KEY=$(sed -n 's/^EASYHEADLESS_API_KEY=//p' "$ACCEL_ENV_FILE" | tail -n 1)

if [ -z "$EASYHEADLESS_API_KEY" ]; then
    echo "EASYHEADLESS_API_KEY is missing from AccelSkillsHub/.env." >&2
    exit 1
fi

export EASYHEADLESS_API_KEY
export EASYHEADLESS_API_URL="https://lms.accelskillshub.com/wp-json/easyheadless/v1"

exec node "$EASYHEADLESS_SERVER"
