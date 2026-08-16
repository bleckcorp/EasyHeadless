# Signed EasyHeadless updates

EasyHeadless 0.4 installs only EasyHeadless releases described by a trusted
HTTPS manifest and signed with Ed25519. Remote update routes never accept a
plugin slug, ZIP upload, package URL, or public key.

## Safety model

- The manifest signature covers the plugin slug, version, package URL, SHA-256,
  compatibility requirements, and release timestamp.
- The ZIP is downloaded to a temporary file and its SHA-256 must match before
  WordPress receives it.
- Installation uses WordPress `Plugin_Upgrader`; EasyHeadless does not copy or
  delete plugin directories itself.
- Remote installation requires WordPress 6.3+, PHP Sodium, direct filesystem
  access, writable plugin/content directories, and an exclusive update lock.
- Automatic updates remain disabled. An authenticated MCP or REST call must
  explicitly request installation.
- WordPress sites that require an FTP/SSH credential prompt should update from
  the Plugins screen instead of the remote route.

This design substantially reduces update risk but cannot promise that a server,
network, host, or third-party WordPress installation will never fail. Keep a
host-level backup and test releases on staging before production.

## Create signing keys

Create the private key outside the repository:

```bash
php scripts/generate-release-key.php \
  --secret-key-file=/secure/easyheadless-release.key
```

The command prints only the public key. Store the private key in a secure release
system; never upload it to WordPress or include it in a ZIP.

## Build and sign a release

Create a ZIP whose only root directory is `easyheadless-bridge/`, upload the ZIP
to its final HTTPS URL, then sign the exact ZIP:

```bash
php scripts/sign-release.php \
  --zip=dist/easyheadless-bridge-0.4.0.zip \
  --package-url=https://releases.example.com/easyheadless/easyheadless-bridge-0.4.0.zip \
  --secret-key-file=/secure/easyheadless-release.key \
  --released-at=2026-08-15T08:00:00Z \
  --details-url=https://releases.example.com/easyheadless/0.4.0 \
  --tested-wordpress=6.8 \
  --output=dist/stable.json
```

Upload the manifest without changing it. If the package URL or ZIP changes,
create a new signature.

## Configure a WordPress site

For strongest protection, lock trust configuration in `wp-config.php`:

```php
define('EASYHEADLESS_UPDATE_MANIFEST_URL', 'https://releases.example.com/easyheadless/stable.json');
define('EASYHEADLESS_UPDATE_PUBLIC_KEY', 'BASE64_PUBLIC_KEY');
```

Alternatively, an administrator can enter both values under **EasyHeadless →
Settings → Signed updates**. These settings are deliberately absent from the
content settings REST route.

## REST and MCP

- `GET /wp-json/easyheadless/v1/updater` — non-sensitive status.
- `POST /wp-json/easyheadless/v1/updater/check` — authenticated manifest refresh
  and signature check; no installation.
- `POST /wp-json/easyheadless/v1/updater/install` — authenticated, explicit
  production mutation through WordPress core.

MCP exposes the equivalent `easyheadless.get_updater_status`,
`easyheadless.check_update`, and `easyheadless.install_update` tools.
