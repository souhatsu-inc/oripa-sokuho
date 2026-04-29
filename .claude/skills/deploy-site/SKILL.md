---
name: deploy-site
description: "オリパ速報（oripanews.com）をXserverにデプロイする。'デプロイ', 'deploy', '本番反映', '公開', 'xserverにアップ', 'サーバーに反映', 'push and deploy'で発動。"
metadata:
  version: 1.0.0
---

# オリパ速報デプロイスキル（Xserver）

oripanews.com のデプロイ手順。初回セットアップ済みの場合は Step 4 から実行する。

---

## 前提情報

| 項目 | 値 |
|------|-----|
| サーバー | Xserver |
| サーバーID | souhatsu |
| ホスト名 | sv16601.xserver.jp |
| SSH鍵 | `~/.ssh/souhatsu.key` |
| SSHポート | 10022 |
| ドメイン | oripanews.com |
| ドキュメントルート | `~/oripanews.com/public_html` |
| リポジトリ | `souhatsu-inc/oripa-sokuho`（private） |
| PHP | 8.3（CLI: `/opt/php-8.3/bin/php`、Web: サーバーパネルで設定） |
| DB | SQLite（`data/comments.db`） |

### SSH接続コマンド（共通）

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022
```

以降、リモートコマンドはすべてこの形式で実行する：

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "コマンド"
```

---

## Step 1: 初回セットアップ（済みなら飛ばす）

### 1-1. Xserverサーバーパネルで設定

- **ドメイン設定** → `oripanews.com` 追加
- **SSL設定** → 無料独自SSL ON
- **PHP Ver.切替** → PHP 8.3以上を選択
- **SSH設定** → ON

### 1-2. Git clone

リポジトリがprivateの場合、一時的にpublicにするかPATを使う。

```bash
# publicの場合
cd ~/oripanews.com/public_html
git clone https://github.com/souhatsu-inc/oripa-sokuho.git .

# PATを使う場合
git clone https://kaikyou1223:{PAT}@github.com/souhatsu-inc/oripa-sokuho.git .
```

### 1-3. composer install

Xserverのデフォルトphpは8.0なので、8.3を明示指定する：

```bash
cd ~/oripanews.com/public_html
curl -sS https://getcomposer.org/installer | /opt/php-8.3/bin/php -- --install-dir=/tmp --filename=composer
/opt/php-8.3/bin/php /tmp/composer install --no-dev
```

### 1-4. コメントDB用ディレクトリ

```bash
cd ~/oripanews.com/public_html
mkdir -p data
touch data/comments.db
chmod 707 data
chmod 606 data/comments.db
```

### 1-5. .user.ini 復元

Xserverのデフォルト `.user.ini` が消えている場合、サーバーパネルの「PHP設定」で再保存すれば自動復元される。

---

## Step 2: 初回後のリモートURL設定（1回だけ）

PATが用意できたら、以降のgit pullを認証なしで実行できるようにする：

```bash
cd ~/oripanews.com/public_html
git remote set-url origin https://kaikyou1223:{PAT}@github.com/souhatsu-inc/oripa-sokuho.git
```

---

## Step 3: ローカルでの準備

デプロイ前にローカルで以下を確認する：

```bash
# 1. 変更があるか確認
git status

# 2. 変更をcommit（未commitの場合）
git add -A
git commit -m "変更内容の説明"

# 3. GitHubにpush
git push origin main
```

---

## Step 4: 本番反映（通常のデプロイ）

**これが毎回やる手順。**

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "
cd ~/oripanews.com/public_html && git pull origin main 2>&1
"
```

### composer.json に変更があった場合

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "
cd ~/oripanews.com/public_html
git pull origin main 2>&1
/opt/php-8.3/bin/php /tmp/composer install --no-dev 2>&1
"
```

---

## Step 5: 動作確認（HTTP + ブラウザスクショ）

デプロイ後、HTTPステータスとビジュアルの両方を確認する。**curl のステータスチェックだけだとレイアウト崩れを検知できない**ため、agent-browser でPC・SPのスクショ撮影まで行う。

### 5-1. HTTPステータス確認

```bash
for c in pokeka review guide free flame; do
  printf "/?category=%-7s: " "$c"
  curl -s -o /dev/null -w "%{http_code}\n" "https://oripanews.com/?category=$c"
done
printf "TOP            : "; curl -s -o /dev/null -w "%{http_code}\n" "https://oripanews.com/"
printf "記事サンプル    : "; curl -s -o /dev/null -w "%{http_code}\n" "https://oripanews.com/article/tcg-purchase-survey"
```

各カテゴリで `<div class="article-item">` が1件以上含まれているかも確認する：

```bash
for c in pokeka review guide free flame; do
  count=$(curl -s "https://oripanews.com/?category=$c" | grep -c 'class="article-item"')
  echo "$c: article-item数=$count"
done
```

### 5-2. ブラウザスクショ確認（必須）

PC・SP の両ビューポートで主要ページをスクショし、レイアウト崩れがないか目視確認する。

```bash
TS=$(date +%Y%m%d-%H%M%S)
DIR="/tmp/oripa-deploy-${TS}"
mkdir -p "$DIR"

URLS=(
  "https://oripanews.com/"                              # トップ
  "https://oripanews.com/?category=pokeka"              # ポケカ（記事多い）
  "https://oripanews.com/?category=review"              # 口コミ（記事少ない）
  "https://oripanews.com/?category=guide"               # 優良
  "https://oripanews.com/?category=free"                # 無料（記事少ない）
  "https://oripanews.com/?category=flame"               # 炎上
  "https://oripanews.com/article/tcg-purchase-survey/"  # 記事ページサンプル
)

for url in "${URLS[@]}"; do
  name=$(echo "$url" | sed 's|https://oripanews.com/||; s|[/?=&]|_|g')
  [ -z "$name" ] && name="top"
  # PC
  agent-browser resize 1280 800
  agent-browser open "$url" && agent-browser wait --load networkidle
  agent-browser screenshot "$DIR/${name}_pc.png"
  # SP
  agent-browser resize 375 800
  agent-browser open "$url" && agent-browser wait --load networkidle
  agent-browser screenshot "$DIR/${name}_sp.png"
done

echo "スクショ保存先: $DIR"
ls -la "$DIR"
```

### チェック観点（必ず目視確認）

**PC（1280px幅）**

- [ ] container が `1fr | 300px` の2カラムで正しく描画されている（main が左、sidebar が右）
- [ ] カテゴリ絞り込み時、breadcrumb が全幅で表示され、main が右の300px枠に押し込まれていない
- [ ] タブが6つ（総合/ポケカ/口コミ/優良/無料/炎上）すべて表示されている
- [ ] 記事カード（article-item）の左にサムネ、右にタイトル・本文の横並びレイアウト

**SP（375px幅）**

- [ ] 1カラムレイアウトに切り替わり、横スクロールが発生していない
- [ ] 上部の人気記事ランキング（sp-ranking）が表示されている
- [ ] タブがスクロール可能な状態で全6個アクセス可能

**記事ページ**

- [ ] タイトル・サムネ・本文・コメント欄・関連記事が正しく描画されている
- [ ] サムネが「NOW PRINTING」プレースホルダーになっている記事がないか

### スクショ確認で問題が見つかった場合

レイアウト崩れ・要素欠落・コンソールエラーが見つかった場合:

1. 原因を特定して修正
2. 再デプロイ（Step 3 → Step 4）
3. 再度 Step 5-2 のスクショ確認
4. **全ページOKを確認するまでデプロイ完了とみなさない**

### その他の従来チェックリスト

- [ ] コメント投稿（`/comment/post`）が動作する
- [ ] CSSが正しく読み込まれている
- [ ] .htaccessのリライトが機能している（パス形式URL）

---

## Step 6: PageSpeed Insights 計測（必須）

デプロイで変更された記事ページ・トップページに対して PageSpeed Insights を実行し、スコア劣化がないか確認する。

**前提:**
- `.env` に `PAGESPEED_API_KEY` を設定済み（`https://console.cloud.google.com/apis/credentials` で発行）
- `jq` がインストール済み（`brew install jq`）
- スクリプトは `scripts/pagespeed.sh`（実行権限付き）

### 6-1. 計測対象URLの決定

直近のデプロイで変更・追加された記事をベースに対象URLを決める。

```bash
cd /Users/kaikyotaro/repository/oripa-sokuho

# 直近コミットで追加・更新された記事ファイルを抽出 → URL化
git diff --name-only HEAD~1 HEAD -- 'content/articles/*.md' \
  | sed -E 's|content/articles/[0-9]{4}-[0-9]{2}-[0-9]{2}-(.+)\.md|https://oripanews.com/article/\1/|' \
  | sort -u
```

最低限以下を計測する：

- `https://oripanews.com/`（トップ）
- 直近で公開した記事ページ（複数あるならHOT/最新の1〜2本に絞る）

### 6-2. 計測実行

各URLについて mobile を実行（必要に応じて desktop も）：

```bash
cd /Users/kaikyotaro/repository/oripa-sokuho

# トップ + 最新記事を mobile で計測
./scripts/pagespeed.sh "https://oripanews.com/" mobile
echo ""
./scripts/pagespeed.sh "https://oripanews.com/article/<latest-slug>/" mobile
```

`<latest-slug>` は 6-1 で抽出した直近記事のslugに置き換える。

### 6-3. 判定基準

**Performance スコア:**

| スコア | 判定 | 対応 |
|---|---|---|
| 90+ | 🟢 OK | そのまま完了 |
| 50-89 | 🟡 要改善 | Top Opportunities をユーザー報告。改善するか確認 |
| 50未満 | 🔴 ブロッキング | 即報告。`./scripts/pagespeed.sh URL mobile --detail` で詳細調査 |

**チェック観点:**

- [ ] Performance スコアが前回デプロイから大きく劣化していないか（-10pt以上の劣化は要調査）
- [ ] LCP（Largest Contentful Paint）が 2.5s 以内
- [ ] CLS（Cumulative Layout Shift）が 0.1 以下
- [ ] TBT（Total Blocking Time）が 200ms 以下
- [ ] Top Opportunities に大きな新規項目（巨大画像・blocking script・外部CDNフォント）が出ていないか

### 6-4. 注意事項

- PageSpeed API は 1リクエスト 30〜60秒かかる。変更ページが多い場合は重要ページに絞る
- スコア劣化は新規追加要素（大きい画像・blocking script・外部CDNフォント等）が主因
- 詳細を見たい時: `./scripts/pagespeed.sh URL mobile --detail` で LCP要素・全opportunities・diagnostics・上位ネットワークリソースを確認
- 記事サムネが外部画像（PR TIMES・公式OGP・X画像等）で重い場合は、同じ画像を使い続けている既存記事と比較してスコア差分を見る

### 6-5. 劣化が見つかった場合

1. `--detail` で原因（LCP要素・大きいリソース・blocking 要素）を特定
2. 改善コミットを作成（画像のWebP化、preload追加、不要scriptの削除等）
3. 再デプロイ → Step 5（HTTP・スクショ）→ Step 6（再計測）
4. **改善が確認できるまでデプロイ完了とみなさない**

---

## トラブルシューティング

### 500 Internal Server Error

```bash
# エラーログ確認
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "
tail -20 ~/oripanews.com/log/error_log
"
```

### パス形式URLが404になる

`.htaccess` が正しく配置されているか確認：

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "
cat ~/oripanews.com/public_html/.htaccess
"
```

### コメントが保存できない

`data/` ディレクトリのパーミッションを確認：

```bash
ssh -i ~/.ssh/souhatsu.key souhatsu@sv16601.xserver.jp -p 10022 "
ls -la ~/oripanews.com/public_html/data/
chmod 707 ~/oripanews.com/public_html/data
chmod 606 ~/oripanews.com/public_html/data/comments.db
"
```

### composer installが「PHP version does not satisfy」

CLIのPHPバージョンが古い。8.3を明示指定：

```bash
/opt/php-8.3/bin/php /tmp/composer install --no-dev
```

### SSL証明書エラー（ERR_CERT_COMMON_NAME_INVALID）

ドメイン追加直後はSSL反映に最大1時間かかる。サーバーパネル → SSL設定 → 無料独自SSLがONか確認。

---

## 注意事項

- `data/comments.db` は `.gitignore` に入っている。本番のコメントデータはgit管理されない
- `.user.ini` はXserverが管理するファイル。git管理外だが消さないこと
- `vendor/` は `.gitignore` に入っている。サーバー上で `composer install` が必要
- リポジトリは **private** なので、git pullにはPATまたはpublic化が必要

---

## CSS修正時の注意（過去の失敗から）

### SP横スクロール対策で `overflow-x: hidden` を body/html に使わない

`html, body { overflow-x: hidden }` はスクロールを隠すだけで、コンテンツが右端でクリップされて**見切れる**副作用がある。根本原因を直すこと。

**正しいアプローチ：**

1. **CSS Grid のオーバーフロー** → グリッド子要素に `min-width: 0` を追加
   ```css
   .container > * { min-width: 0; }
   ```
2. **img の固定幅属性（`width="300"` 等）がグリッドを拡張する** → `max-width: 100%` を追加
3. **`<pre>` / コードブロック** → `overflow-x: auto; max-width: 100%` で要素内スクロール
4. **テーブル** → `display: block; overflow-x: auto`
5. **長URL・英数字** → `overflow-wrap: anywhere`
