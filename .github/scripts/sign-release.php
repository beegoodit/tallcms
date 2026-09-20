<?php

declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php sign-release.php <file>\n");

    exit(1);
}

if (! function_exists('sodium_crypto_sign_detached')) {
    fwrite(STDERR, "The sodium PHP extension is required.\n");

    exit(1);
}

$privateKeyHex = getenv('ED25519_PRIVATE_KEY');

if ($privateKeyHex === false || $privateKeyHex === '') {
    fwrite(STDERR, "ED25519_PRIVATE_KEY is not set.\n");

    exit(1);
}

if (strlen($privateKeyHex) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES * 2 || ! ctype_xdigit($privateKeyHex)) {
    fwrite(STDERR, "ED25519_PRIVATE_KEY must be a 64-byte hexadecimal Ed25519 secret key.\n");

    exit(1);
}

$file = $argv[1];
$content = is_file($file) ? file_get_contents($file) : false;

if ($content === false) {
    fwrite(STDERR, "Unable to read file: {$file}\n");

    exit(1);
}

$privateKey = sodium_hex2bin($privateKeyHex);
$signature = sodium_crypto_sign_detached($content, $privateKey);
$publicKey = sodium_crypto_sign_publickey_from_secretkey($privateKey);

if (! sodium_crypto_sign_verify_detached($signature, $content, $publicKey)) {
    fwrite(STDERR, "Signature self-verification failed.\n");

    exit(1);
}

$signatureFile = $file.'.sig';

if (file_put_contents($signatureFile, base64_encode($signature)) === false) {
    fwrite(STDERR, "Unable to write signature: {$signatureFile}\n");

    exit(1);
}

fwrite(STDOUT, "Signed and verified: {$signatureFile}\n");
