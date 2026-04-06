<?php

// .env ファイルから環境変数を読み込む
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            putenv($line);
        }
    }
}

return [
    'consumer_key' => getenv('X_CONSUMER_KEY'),
    'consumer_secret' => getenv('X_CONSUMER_SECRET'),
    'access_token' => getenv('X_ACCESS_TOKEN'),
    'access_token_secret' => getenv('X_ACCESS_TOKEN_SECRET'),
];
