#!/usr/bin/env php
<?php

/**
 * プレキャンデータ日次更新スクリプト
 *
 * X APIから最新のプレキャンツイートを取得し、永続ストアにマージする。
 * 締切が過ぎたツイートは自動で除去される。
 *
 * Usage:
 *   php bin/refresh-campaign.php           # 全カテゴリ更新
 *   php bin/refresh-campaign.php pokeka    # 指定カテゴリのみ
 *   php bin/refresh-campaign.php --dry-run # 取得結果の確認（保存しない）
 *   php bin/refresh-campaign.php --status  # 現在のデータ状況を表示
 *
 * Cron設定例（毎日9時に実行）:
 *   0 9 * * * cd /home/souhatsu/oripanews.com/public_html && /opt/php-8.3/bin/php bin/refresh-campaign.php >> data/campaign-refresh.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/XSearch.php';

use App\XSearch;

$categories = [
    'pokeka' => 'ポケカ',
    'onepiece' => 'ワンピカード OR ワンピースカード',
    'yugioh' => '遊戯王',
];

$dataDir = __DIR__ . '/../data/campaign';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$showStatus = in_array('--status', $args, true);
$targetCat = null;

foreach ($args as $arg) {
    if (!str_starts_with($arg, '--') && isset($categories[$arg])) {
        $targetCat = $arg;
    }
}

$timestamp = date('Y-m-d H:i:s');

// --status: 現在のデータ状況を表示
if ($showStatus) {
    echo "[{$timestamp}] === プレキャンデータ状況 ===\n";
    foreach ($categories as $slug => $kw) {
        $file = "{$dataDir}/{$slug}.json";
        if (!file_exists($file)) {
            echo "  {$slug}: データなし\n";
            continue;
        }
        $data = json_decode(file_get_contents($file), true) ?? [];
        $withDeadline = 0;
        $noDeadline = 0;
        foreach ($data as $t) {
            if (!empty($t['deadline_raw'])) {
                $withDeadline++;
            } else {
                $noDeadline++;
            }
        }
        echo "  {$slug}: " . count($data) . "件（締切あり: {$withDeadline}, 締切なし: {$noDeadline}）\n";
    }
    exit(0);
}

// メイン処理
$config = require __DIR__ . '/../config/twitter.php';
$xSearch = new XSearch($config, '', 60); // キャッシュ短め（60秒）にして最新を取得

$targets = $targetCat ? [$targetCat => $categories[$targetCat]] : $categories;

echo "[{$timestamp}] プレキャン更新開始\n";

foreach ($targets as $slug => $keyword) {
    echo "  [{$slug}] 検索中...\n";

    try {
        // APIから最新を取得
        $result = $xSearch->searchCampaignTweets($keyword, 20);
        $newTweets = [];
        foreach ($result['tweets'] as $tweet) {
            $newTweets[] = XSearch::parseTweetData($tweet);
        }
        echo "  [{$slug}] API取得: " . count($newTweets) . "件\n";

        // 既存データを読み込み
        $file = "{$dataDir}/{$slug}.json";
        $existing = [];
        if (file_exists($file)) {
            $existing = json_decode(file_get_contents($file), true) ?? [];
        }
        $beforeCount = count($existing);

        // マージ（IDで重複排除、新しい方を優先）
        $merged = [];
        $existingById = [];
        foreach ($existing as $t) {
            $existingById[$t['id']] = $t;
        }
        foreach ($newTweets as $t) {
            $existingById[$t['id']] = $t; // 新しい方で上書き
        }
        $merged = array_values($existingById);

        // 締切切れを除去
        $today = date('Y-m-d');
        $filtered = [];
        $removed = 0;
        foreach ($merged as $t) {
            if (!empty($t['deadline_raw']) && $t['deadline_raw'] < $today) {
                $removed++;
                continue;
            }
            $filtered[] = $t;
        }

        // 締切なしのツイートは投稿日から14日経過で除去
        $cutoff = date('Y-m-d', strtotime('-14 days'));
        $final = [];
        $aged = 0;
        foreach ($filtered as $t) {
            // created_atがない場合はURLからID→日付推定はせず、残す
            if (empty($t['deadline_raw'])) {
                // tweet IDの先頭部分で大まかな投稿日を判定（なければそのまま残す）
                // 簡易的に: データに追加日を記録して14日で失効
                if (!isset($t['added_at'])) {
                    $t['added_at'] = $today;
                }
                if ($t['added_at'] < $cutoff) {
                    $aged++;
                    continue;
                }
            }
            $final[] = $t;
        }

        // engagement順でソート
        usort($final, fn($a, $b) => ($b['engagement'] ?? 0) - ($a['engagement'] ?? 0));

        echo "  [{$slug}] マージ結果: {$beforeCount}件 → " . count($final) . "件";
        echo " (新規: " . count($newTweets) . ", 締切切れ除去: {$removed}, 期限切れ除去: {$aged})\n";

        if ($dryRun) {
            echo "  [{$slug}] --dry-run: 保存スキップ\n";
            foreach (array_slice($final, 0, 5) as $t) {
                echo "    - {$t['title']} | @{$t['username']} | 締切: {$t['deadline']} | 盛り上がり: {$t['engagement']}\n";
            }
        } else {
            file_put_contents($file, json_encode($final, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo "  [{$slug}] 保存完了: {$file}\n";
        }
    } catch (\Throwable $e) {
        echo "  [{$slug}] エラー: {$e->getMessage()}\n";
    }
}

echo "[{$timestamp}] 完了\n";
