<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

$options = getopt('', array('secret-key-file:'));
$target = isset($options['secret-key-file']) ? (string) $options['secret-key-file'] : '';
if (!$target || !function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "Usage: php scripts/generate-release-key.php --secret-key-file=/secure/path/easyheadless.key\nPHP Sodium is required.\n");
    exit(1);
}
if (file_exists($target)) {
    fwrite(STDERR, "Refusing to overwrite an existing private key.\n");
    exit(1);
}

$directory = dirname($target);
if (!is_dir($directory) || !is_writable($directory)) {
    fwrite(STDERR, "The private-key directory must already exist and be writable.\n");
    exit(1);
}

$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);
if (false === file_put_contents($target, base64_encode($secret) . PHP_EOL, LOCK_EX)) {
    fwrite(STDERR, "Unable to write the private key.\n");
    exit(1);
}
chmod($target, 0600);
sodium_memzero($secret);

fwrite(STDOUT, "Private key written with mode 0600. Keep it outside the repository and backups you do not control.\n");
fwrite(STDOUT, "EasyHeadless public key: " . base64_encode($public) . PHP_EOL);
