<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/XSearch.php';

use App\XSearch;

$twitterConfig = require __DIR__ . '/config/twitter.php';

$categories = [
    'pokeka' => ['label' => 'ポケカ', 'keyword' => 'ポケカ'],
    'onepiece' => ['label' => 'ワンピース', 'keyword' => 'ワンピカード OR ワンピースカード'],
    'yugioh' => ['label' => '遊戯王', 'keyword' => '遊戯王'],
];

// X API検索（キャッシュ1時間）
$xSearch = new XSearch($twitterConfig);
$results = [];
$errors = [];

foreach ($categories as $slug => $cat) {
    try {
        $results[$slug] = $xSearch->searchCampaignTweets($cat['keyword'], 20);
    } catch (\Throwable $e) {
        $errors[$slug] = $e->getMessage();
        $results[$slug] = ['tweets' => [], 'cached' => false];
    }
}

// ページ設定
$pageTitle = 'プレキャン・プレゼント企画まとめ｜オリパ速報';
$metaDescription = 'ポケカ・遊戯王・ワンピースのプレゼントキャンペーン・プレゼント企画をリアルタイムでまとめています。';
$canonical = 'https://oripanews.com/campaign/';
$ogType = 'website';
$ogTitle = $pageTitle;
$ogDescription = $metaDescription;
$ogImage = 'https://oripanews.com/img/ogp-default.png';
$currentCategory = '';
$jsonLd = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle,
        'description' => $metaDescription,
        'url' => $canonical,
    ],
];

require __DIR__ . '/templates/header.php';
?>

<div class="container">
    <main>
        <div class="campaign-page">
            <h1 class="campaign-title">◆ プレキャン・プレゼント企画まとめ ◆</h1>
            <p class="campaign-desc">X（旧Twitter）上のプレゼントキャンペーン投稿を自動収集しています（直近7日間・1時間ごと更新）</p>

            <?php foreach ($categories as $slug => $cat): ?>
                <section class="campaign-section" id="<?= $slug ?>">
                    <h2 class="campaign-section-title">■ <?= htmlspecialchars($cat['label']) ?></h2>

                    <?php if (!empty($errors[$slug])): ?>
                        <p class="campaign-error">※ 取得エラー: データを読み込めませんでした</p>
                    <?php elseif (empty($results[$slug]['tweets'])): ?>
                        <p class="campaign-empty">※ 該当するプレキャン投稿が見つかりませんでした</p>
                    <?php else: ?>
                        <div class="campaign-tweets">
                            <?php foreach ($results[$slug]['tweets'] as $tweet): ?>
                                <div class="campaign-tweet-item">
                                    <blockquote class="twitter-tweet">
                                        <p><?= htmlspecialchars(mb_substr($tweet['text'], 0, 140)) ?></p>
                                        &mdash; <?= htmlspecialchars($tweet['name']) ?> (@<?= htmlspecialchars($tweet['username']) ?>)
                                        <a href="https://x.com/<?= htmlspecialchars($tweet['username']) ?>/status/<?= htmlspecialchars($tweet['id']) ?>"><?= date('Y年n月j日', strtotime($tweet['created_at'])) ?></a>
                                    </blockquote>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <aside class="sidebar">
        <div class="sidebar-box">
            <div class="sidebar-box-header">◆ カテゴリ</div>
            <div class="sidebar-box-body">
                <ul class="campaign-nav">
                    <?php foreach ($categories as $slug => $cat): ?>
                        <li><a href="#<?= $slug ?>"><?= htmlspecialchars($cat['label']) ?>（<?= count($results[$slug]['tweets']) ?>件）</a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="sidebar-box">
            <div class="sidebar-box-header">◆ このページについて</div>
            <div class="sidebar-box-body campaign-about">
                X上のポケカ・遊戯王・ワンピースのプレゼント企画を自動で収集しています。<br>
                データは1時間ごとに更新されます。<br>
                <small>※ 直近7日間の投稿が対象です</small>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
