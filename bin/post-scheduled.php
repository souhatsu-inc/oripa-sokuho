#!/usr/bin/env php
<?php

/**
 * 予約投稿の実行スクリプト（cronで毎分実行）
 *
 * data/x-schedule.json から予約時刻を過ぎた投稿を1件ずつ実行する。
 *
 * Usage:
 *   php bin/post-scheduled.php           # 予約時刻に達した投稿を実行
 *   php bin/post-scheduled.php --list    # 予約一覧表示
 *   php bin/post-scheduled.php --dry-run # 実行対象の確認（投稿しない）
 *
 * Cron設定例（毎分実行）:
 *   * * * * * cd /path/to/project && php bin/post-scheduled.php >> data/schedule.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Content.php';

use App\TwitterClient;

$scheduleFile = __DIR__ . '/../data/x-schedule.json';
$postsFile = __DIR__ . '/../data/x-posts.json';

$options = getopt('', ['list', 'dry-run']);

function loadJson(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveJson(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
}

$schedule = loadJson($scheduleFile);

if (empty($schedule['queue'])) {
    if (isset($options['list'])) {
        echo "予約キューは空です。\n";
    }
    exit(0);
}

// --- 一覧表示 ---
if (isset($options['list'])) {
    $pending = array_filter($schedule['queue'], fn($item) => $item['status'] === 'pending');
    $done = array_filter($schedule['queue'], fn($item) => $item['status'] === 'posted');

    echo "=== 予約投稿キュー ===\n\n";
    echo "【待機中: " . count($pending) . "件】\n";
    foreach ($pending as $item) {
        $title = mb_strimwidth($item['slug'], 0, 40, '...');
        echo sprintf("  %s  %-40s\n", $item['scheduled_at'], $title);
    }
    echo "\n【投稿済み: " . count($done) . "件】\n";
    foreach ($done as $item) {
        $title = mb_strimwidth($item['slug'], 0, 40, '...');
        echo sprintf("  %s  %-40s  → %s\n", $item['scheduled_at'], $title, $item['tweet_id'] ?? '');
    }
    exit(0);
}

// --- 予約実行 ---
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
$dryRun = isset($options['dry-run']);

$executed = false;
foreach ($schedule['queue'] as &$item) {
    if ($item['status'] !== 'pending') {
        continue;
    }

    $scheduledAt = new DateTimeImmutable($item['scheduled_at'], new DateTimeZone('Asia/Tokyo'));
    if ($scheduledAt > $now) {
        continue;
    }

    // 1回のcron実行で1件だけ投稿（レート制限対策）
    if ($executed) {
        break;
    }

    $slug = $item['slug'];
    $mainText = $item['text'];
    $replyText = $item['reply_text'];
    $thumbnailUrl = $item['thumbnail_url'] ?? '';

    $jstNow = $now->format('Y-m-d H:i:s');
    echo "[{$jstNow}] 投稿実行: {$slug}\n";

    if ($dryRun) {
        echo "  (dry-run: スキップ)\n";
        $executed = true;
        continue;
    }

    $twitterConfigFile = __DIR__ . '/../config/twitter.php';
    if (!file_exists($twitterConfigFile)) {
        echo "  エラー: config/twitter.php が見つかりません\n";
        exit(1);
    }

    try {
        $twitterConfig = require $twitterConfigFile;
        $twitter = new TwitterClient($twitterConfig);
        $result = $twitter->postArticle($mainText, $replyText, $thumbnailUrl);

        $item['status'] = 'posted';
        $item['tweet_id'] = $result['tweet_id'];
        $item['reply_id'] = $result['reply_id'];
        $item['posted_at'] = $now->format('c');

        echo "  完了: https://x.com/i/status/{$result['tweet_id']}\n";

        // x-posts.json にも記録
        $posts = loadJson($postsFile);
        if (!isset($posts['posts'])) {
            $posts['posts'] = [];
        }
        $posts['posts'][] = [
            'slug' => $slug,
            'tweet_id' => $result['tweet_id'],
            'reply_id' => $result['reply_id'],
            'text' => $mainText,
            'reply_text' => $replyText,
            'posted_at' => $now->format('c'),
        ];
        saveJson($postsFile, $posts);

        $executed = true;
    } catch (\Throwable $e) {
        echo "  エラー: {$e->getMessage()}\n";
        $item['status'] = 'error';
        $item['error'] = $e->getMessage();
        $item['error_at'] = $now->format('c');
        $executed = true;
    }
}
unset($item);

// スケジュールファイルを更新
saveJson($scheduleFile, $schedule);
