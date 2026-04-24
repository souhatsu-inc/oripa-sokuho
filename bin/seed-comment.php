#!/usr/bin/env php
<?php

/**
 * 記事に初期コメントを1件投入するCLIスクリプト
 *
 * 新規記事を公開した直後に、初期コメントを1件だけ投入して活性感を出すために使う。
 *
 * Usage:
 *   php bin/seed-comment.php --slug=<article-slug> --name="表示名" --body="コメント本文"
 *   php bin/seed-comment.php --slug=<article-slug> --name="表示名" --body="コメント本文" --created-at="2026-04-24 15:30:00"
 *
 * Example:
 *   php bin/seed-comment.php \
 *     --slug=toreca-chase-megarizadon-price-mistake \
 *     --name="名無しのオリパ民" \
 *     --body="スタッフが走って追いかけたの完全に漫画ｗｗｗ"
 */

declare(strict_types=1);

$opts = getopt('', ['slug:', 'name:', 'body:', 'created-at::']);

$slug = $opts['slug'] ?? null;
$name = $opts['name'] ?? null;
$body = $opts['body'] ?? null;
$createdAt = $opts['created-at'] ?? null;

if (!$slug || !$name || !$body) {
    fwrite(STDERR, "Usage: php bin/seed-comment.php --slug=<slug> --name=<name> --body=<body> [--created-at=<YYYY-MM-DD HH:MM:SS>]\n");
    exit(1);
}

if ($createdAt === null) {
    $createdAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');
}

$articleFile = glob(__DIR__ . "/../content/articles/*-{$slug}.md")[0] ?? null;
if (!$articleFile) {
    fwrite(STDERR, "記事ファイルが見つかりません: slug={$slug}\n");
    exit(1);
}

$dbPath = __DIR__ . '/../data/comments.db';
if (!file_exists($dbPath)) {
    fwrite(STDERR, "comments.db が見つかりません: {$dbPath}\n");
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ipHash = hash('sha256', 'seed-' . $slug . '-' . microtime(true));

$stmt = $db->prepare(
    'INSERT INTO comments (article_slug, name, body, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$slug, $name, $body, $ipHash, $createdAt]);

$id = $db->lastInsertId();

echo "✅ コメント投入完了 (id={$id})\n";
echo "  slug     : {$slug}\n";
echo "  name     : {$name}\n";
echo "  body     : {$body}\n";
echo "  created  : {$createdAt}\n";
