<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Content.php';
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

            <nav class="campaign-shortcuts">
                <?php foreach ($categories as $slug => $cat): ?>
                    <a href="#<?= $slug ?>" class="campaign-shortcut"><?= htmlspecialchars($cat['label']) ?>（<?= count($results[$slug]['tweets']) ?>件）</a>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($categories as $slug => $cat): ?>
                <section class="campaign-section" id="<?= $slug ?>">
                    <h2 class="campaign-section-title">■ <?= htmlspecialchars($cat['label']) ?></h2>

                    <?php if (!empty($errors[$slug])): ?>
                        <p class="campaign-error">※ 取得エラー: データを読み込めませんでした</p>
                    <?php elseif (empty($results[$slug]['tweets'])): ?>
                        <p class="campaign-empty">※ 該当するプレキャン投稿が見つかりませんでした</p>
                    <?php else: ?>
                        <div class="campaign-tweets">
                            <?php foreach ($results[$slug]['tweets'] as $i => $tweet): ?>
                                <div class="campaign-tweet-item<?= $i >= 4 ? ' campaign-hidden' : '' ?>"
                                     data-tweet-id="<?= htmlspecialchars($tweet['id']) ?>"
                                     data-tweet-username="<?= htmlspecialchars($tweet['username']) ?>"
                                     data-tweet-name="<?= htmlspecialchars($tweet['name']) ?>"
                                     data-tweet-text="<?= htmlspecialchars(mb_substr($tweet['text'], 0, 140)) ?>"
                                     data-tweet-date="<?= date('Y年n月j日', strtotime($tweet['created_at'])) ?>"
                                     data-tweet-url="https://x.com/<?= htmlspecialchars($tweet['username']) ?>/status/<?= htmlspecialchars($tweet['id']) ?>">
                                    <div class="campaign-tweet-placeholder">読み込み中...</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($results[$slug]['tweets']) > 4): ?>
                            <button class="campaign-more-btn" data-target="<?= $slug ?>">▼ もっと見る（残り<?= count($results[$slug]['tweets']) - 4 ?>件）</button>
                        <?php endif; ?>
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

<script>
(function() {
    // IntersectionObserverで遅延レンダリング
    function renderTweet(item) {
        if (item.dataset.rendered) return;
        item.dataset.rendered = '1';

        var bq = document.createElement('blockquote');
        bq.className = 'twitter-tweet';

        var p = document.createElement('p');
        p.textContent = item.dataset.tweetText;
        bq.appendChild(p);

        var meta = document.createTextNode('\u2014 ' + item.dataset.tweetName + ' (@' + item.dataset.tweetUsername + ') ');
        bq.appendChild(meta);

        var a = document.createElement('a');
        a.href = item.dataset.tweetUrl;
        a.textContent = item.dataset.tweetDate;
        bq.appendChild(a);

        item.innerHTML = '';
        item.appendChild(bq);

        if (typeof twttr !== 'undefined' && twttr.widgets) {
            twttr.widgets.load(item);
        }
    }

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                renderTweet(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '200px' });

    // 初期表示分（非hiddenのみ）をobserve
    document.querySelectorAll('.campaign-tweet-item').forEach(function(item) {
        if (item.classList.contains('campaign-hidden')) return;
        observer.observe(item);
    });

    // 「もっと見る」ボタン：表示してからobserve
    document.querySelectorAll('.campaign-more-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var section = document.getElementById(btn.dataset.target);
            section.querySelectorAll('.campaign-hidden').forEach(function(el) {
                el.classList.remove('campaign-hidden');
                if (!el.dataset.rendered) observer.observe(el);
            });
            btn.remove();
        });
    });
})();
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
