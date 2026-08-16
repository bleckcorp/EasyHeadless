<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$options = getopt('', array(
    'zip:',
    'package-url:',
    'secret-key-file:',
    'released-at:',
    'output:',
    'details-url::',
    'tested-wordpress::',
));

foreach (array('zip', 'package-url', 'secret-key-file', 'released-at', 'output') as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Missing --{$required}. See docs/updates.md.\n");
        exit(1);
    }
}
if (!class_exists('ZipArchive') || !function_exists('sodium_crypto_sign_detached')) {
    fwrite(STDERR, "PHP Zip and Sodium extensions are required.\n");
    exit(1);
}

$zip_path = realpath((string) $options['zip']);
$secret_path = realpath((string) $options['secret-key-file']);
$package_url = (string) $options['package-url'];
$released_at = (string) $options['released-at'];
$output = (string) $options['output'];

if (!$zip_path || !$secret_path || 'https' !== strtolower((string) parse_url($package_url, PHP_URL_SCHEME)) || !parse_url($package_url, PHP_URL_HOST)) {
    fwrite(STDERR, "ZIP, private key, and HTTPS package URL must be valid.\n");
    exit(1);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $released_at)) {
    fwrite(STDERR, "--released-at must be an explicit UTC timestamp such as 2026-08-15T08:00:00Z.\n");
    exit(1);
}

$zip = new ZipArchive();
if (true !== $zip->open($zip_path)) {
    fwrite(STDERR, "Unable to open the release ZIP.\n");
    exit(1);
}
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);
    if (!is_string($name) || 0 !== strpos($name, 'easyheadless-bridge/') || false !== strpos($name, '../') || false !== strpos($name, '\\')) {
        $zip->close();
        fwrite(STDERR, "The ZIP contains an entry outside easyheadless-bridge/.\n");
        exit(1);
    }
}
$plugin = $zip->getFromName('easyheadless-bridge/easyheadless-bridge.php');
$zip->close();
if (!is_string($plugin) || !preg_match('/^[ \t\/*#@]*Version:\s*([^\r\n]+)/mi', $plugin, $match)) {
    fwrite(STDERR, "The EasyHeadless plugin header or version is missing from the ZIP.\n");
    exit(1);
}
$version = trim($match[1]);
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "The ZIP contains an invalid plugin version.\n");
    exit(1);
}

$secret = base64_decode(trim((string) file_get_contents($secret_path)), true);
if (false === $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen($secret)) {
    fwrite(STDERR, "The private key file is not a base64-encoded Ed25519 secret key.\n");
    exit(1);
}

$manifest = array(
    'schemaVersion' => 1,
    'slug' => 'easyheadless-bridge',
    'version' => $version,
    'packageUrl' => $package_url,
    'sha256' => hash_file('sha256', $zip_path),
    'signature' => '',
    'requiresWordPress' => '6.2',
    'requiresPhp' => '7.4',
    'releasedAt' => $released_at,
    'detailsUrl' => isset($options['details-url']) ? (string) $options['details-url'] : '',
    'testedWordPress' => isset($options['tested-wordpress']) ? (string) $options['tested-wordpress'] : '',
);

$payload = implode("\n", array(
    'schemaVersion=' . $manifest['schemaVersion'],
    'slug=' . $manifest['slug'],
    'version=' . $manifest['version'],
    'packageUrl=' . $manifest['packageUrl'],
    'sha256=' . $manifest['sha256'],
    'requiresWordPress=' . $manifest['requiresWordPress'],
    'requiresPhp=' . $manifest['requiresPhp'],
    'releasedAt=' . $manifest['releasedAt'],
));
$manifest['signature'] = base64_encode(sodium_crypto_sign_detached($payload, $secret));
$public = sodium_crypto_sign_publickey_from_secretkey($secret);
sodium_memzero($secret);

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (false === file_put_contents($output, $json, LOCK_EX)) {
    fwrite(STDERR, "Unable to write the release manifest.\n");
    exit(1);
}

fwrite(STDOUT, "Signed EasyHeadless {$version}; manifest written to {$output}.\n");
fwrite(STDOUT, "EasyHeadless public key: " . base64_encode($public) . PHP_EOL);
