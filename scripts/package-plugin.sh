#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
plugin_file="$project_root/easyheadless-bridge/easyheadless-bridge.php"
version="$(sed -n "s/define('EASYHEADLESS_VERSION', '\([^']*\)').*/\1/p" "$plugin_file")"

if [[ -z "$version" ]]; then
  echo "Unable to determine EASYHEADLESS_VERSION." >&2
  exit 1
fi

artifact="$project_root/dist/easyheadless-bridge-$version.zip"
mkdir -p "$project_root/dist"
cd "$project_root"
zip -FSrq "$artifact" easyheadless-bridge -x '*/.DS_Store' '*/tests/*'
unzip -t "$artifact"
shasum -a 256 "$artifact"
cp "$artifact" "$project_root/dist/easyheadless-bridge.zip"
echo "$artifact"
