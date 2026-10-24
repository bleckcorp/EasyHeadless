#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
package_temp="$(mktemp -d -t film-efiko-package)"
trap 'rm -rf "$package_temp"' EXIT
cp -R "$project_root/easyheadless-bridge" "$package_temp/easyheadless-bridge"

# Keep the installed directory/bootstrap identity to preserve settings and activation.
php -r '
$file = $argv[1];
$source = file_get_contents($file);
$source = str_replace("Plugin Name: EasyHeadless Bridge", "Plugin Name: Film Efiko Custom", $source);
$source = preg_replace("/Description: .*/", "Description: Private Film Efiko publishing bridge, homepage editor, issues and reader access.", $source, 1);
$source = str_replace("Version: 0.6.10", "Version: 0.6.18", $source);
$source = str_replace("define(\x27EASYHEADLESS_VERSION\x27, \x270.6.10\x27);", "define(\x27EASYHEADLESS_VERSION\x27, \x270.6.18\x27);\ndefine(\x27EASYHEADLESS_EDITION\x27, \x27film-efiko\x27);", $source);
if (strpos($source, "EASYHEADLESS_EDITION") === false) { fwrite(STDERR, "Custom edition marker missing\n"); exit(1); }
file_put_contents($file, $source);
' "$package_temp/easyheadless-bridge/easyheadless-bridge.php"

mkdir -p "$project_root/dist"
artifact="$project_root/dist/film-efiko-custom-0.6.18.zip"
(cd "$package_temp" && zip -FSrq "$artifact" easyheadless-bridge -x '*/.DS_Store' '*/tests/*')
unzip -t "$artifact"
shasum -a 256 "$artifact"
cp "$artifact" "$project_root/dist/film-efiko-custom.zip"
echo "$artifact"
