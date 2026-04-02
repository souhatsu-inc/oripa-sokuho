<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Content.php';

$content = new Content(__DIR__ . '/content/articles');
$articles = $content->getAllArticles();

$baseUrl = 'https://oripanews.com';

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $baseUrl ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
<?php foreach ($articles as $article):
    $slug = $article['slug'];
    $lastmod = date('Y-m-d', strtotime($article['meta']['published_at'] ?? 'now'));
?>
    <url>
        <loc><?= $baseUrl ?>/article/<?= htmlspecialchars($slug) ?>/</loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>
<?php foreach (Content::CATEGORY_SLUGS as $catSlug): ?>
    <url>
        <loc><?= $baseUrl ?>/?category=<?= htmlspecialchars($catSlug) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
</urlset>
