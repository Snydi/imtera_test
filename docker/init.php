<?php

$path = dirname(__DIR__).'/.env';
$contents = file_get_contents(is_file($path) ? $path : dirname(__DIR__).'/.env.example');

foreach (['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_PASSWORD' => bin2hex(random_bytes(24)), 'SEED_USER_PASSWORD' => bin2hex(random_bytes(12))] as $key => $value) {
    $contents = preg_replace('/^'.preg_quote($key, '/').'=\r?$/m', $key.'='.$value, $contents);
}

file_put_contents($path, $contents);
echo "Environment ready: .env\n";
