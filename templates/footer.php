<!-- フッター -->
<div class="footer">
    <div class="footer-about">
        運営：オリパ速報編集部 ｜ TCGオリパの最新情報を速報配信するニュースサイトです。<br>
        お問い合わせ：info@oripanews.com
    </div>
    <div class="footer-text">
        オリパ速報 &copy; 2025-<?= date('Y') ?> ━━ TCGオリパまとめ速報
    </div>
    <div class="footer-decoration">
        ━━━━━━━━━━━━━━━━━━━━━━━━━━
    </div>
</div>

<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
<script>
// blockquote.twitter-tweet が自動レンダリングされない場合のフォールバック
document.addEventListener('DOMContentLoaded', function() {
    function renderTweets() {
        if (typeof twttr === 'undefined' || !twttr.widgets) {
            setTimeout(renderTweets, 500);
            return;
        }
        document.querySelectorAll('blockquote.twitter-tweet').forEach(function(bq) {
            var link = bq.querySelector('a[href*="/status/"]');
            if (!link) return;
            var match = link.href.match(/\/status\/(\d+)/);
            if (!match) return;
            var container = document.createElement('div');
            bq.parentNode.replaceChild(container, bq);
            twttr.widgets.createTweet(match[1], container);
        });
    }
    setTimeout(renderTweets, 1000);
});
</script>
</body>
</html>
