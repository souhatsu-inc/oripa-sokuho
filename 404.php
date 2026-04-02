<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Content.php';

http_response_code(404);

$pageTitle = '404 ページが見つかりません';
$metaDescription = 'お探しのページは見つかりませんでした。';
$canonical = 'https://oripanews.com/';
$noindex = true;
$currentCategory = '';
$jsonLd = [];

$categoryNames = Content::CATEGORY_NAMES;

// ランキング用（サイドバー・SP用）
$rankingArticles = [];

require __DIR__ . '/templates/header.php';
?>

<div class="container">
    <main>
        <div class="article-detail" style="text-align: center; padding: 40px 20px;">
            <h1 style="font-size: 48px; color: #8b1a2b; margin-bottom: 16px;">404</h1>
            <p style="font-size: 16px; margin-bottom: 24px;">
                お探しのページは見つかりませんでした。<br>
                URLが正しいかご確認ください。
            </p>
            <div style="margin-bottom: 24px;">
                <a href="/" class="article-readmore">トップページへ戻る</a>
            </div>
            <div style="font-size: 13px; color: #888;">
                ◆ カテゴリから探す ◆<br>
                <?php foreach ($categoryNames as $slug => $name): ?>
                <a href="/?category=<?= $slug ?>" style="margin: 0 8px;"><?= $name ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
