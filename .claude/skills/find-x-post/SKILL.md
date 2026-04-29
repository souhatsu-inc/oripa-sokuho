---
name: find-x-post
description: "X(旧Twitter)の実在ツイートを検索し、記事に埋め込めるblockquoteコードを生成する。'ツイート検索', 'X検索', 'ツイート埋め込み', 'find tweet', 'find x post', 'X投稿探して', 'ツイート探して', '元ツイート'で発動。"
metadata:
  version: 1.0.0
---

# X(旧Twitter)投稿検索・埋め込みスキル

実在するX投稿を検索し、オリパ速報の記事に埋め込めるHTMLコードを返すスキル。

---

## Step 1: 入力を確認する

ユーザーから以下を受け取る（未提供の場合は質問する）：

| 入力 | 必須 | 説明 |
|-----|------|------|
| 検索キーワード | 必須 | 人名・サービス名・事件概要など |
| 投稿者のXアカウント | 任意 | `@handle` がわかれば精度が上がる |
| 投稿時期 | 任意 | いつ頃の投稿か |
| 投稿内容の手がかり | 任意 | 投稿本文の一部、インプレッション数など |

---

## Step 2: ツイートURLを探す

以下の検索戦略を**上から順に**試す。見つかった時点で次のステップへ進む。

### 戦略1: X直接検索
WebSearchで以下のパターンを試す：
- `"投稿者名" "キーワード" site:x.com`
- `"投稿者名" "キーワード" site:twitter.com`
- `@handle キーワード site:x.com`

### 戦略2: まとめサイト経由
炎上・話題系の投稿はまとめサイトに転載されていることが多い：
- `"キーワード" site:yaraon-blog.com`
- `"キーワード" site:blog.esuteru.com`（はちま起稿）
- `"キーワード" site:matomame.jp`（まとめまとめ）
- `"キーワード" site:togetter.com`

見つかったまとめ記事をWebFetchし、ツイートURLを抽出する。
ツイートURLのパターン: `https://x.com/{handle}/status/{id}` or `https://twitter.com/{handle}/status/{id}`

### 戦略3: ニュース記事経由
- `"キーワード" ツイート OR 投稿 OR X`
- 記事内にツイート埋め込みやスクリーンショットがあれば、そこからURLを辿る

### 戦略4: 投稿者のプロフィールから
- `site:x.com/{handle}` で投稿者のタイムラインを検索
- 投稿日付が分かっていれば日付を添えて検索

---

## Step 3: ツイートURLを検証する

見つかったURLについて以下を確認：
- URLの形式が正しいか（`https://x.com/{handle}/status/{数字}` の形式）
- 投稿者のハンドルが期待通りか
- ステータスIDの時系列が投稿日と矛盾しないか

**注意:** X.comは直接WebFetchできない場合が多い（402/403エラー）。URLの形式と検索コンテキストから妥当性を判断する。

---

## Step 4: 埋め込みコードを生成する

### 基本形式（推奨）

```html
<blockquote class="twitter-tweet"><a href="https://x.com/{handle}/status/{id}"></a></blockquote>
```

この形式で十分。`widgets.js`（footer.phpで読み込み済み）が自動でツイートカードを展開する。

### 複数ツイートの場合

関連ツイートが複数ある場合（リプライチェーンなど）、最も重要な1件をメイン埋め込みにし、他はテキストリンクで補足する：

```html
<blockquote class="twitter-tweet"><a href="https://x.com/{handle}/status/{メインのid}"></a></blockquote>

関連投稿：[投稿の要約](https://x.com/{handle}/status/{別のid})
```

---

## Step 5: 結果を返す

以下の形式で報告する：

```
## 見つかったX投稿

- **投稿者:** @handle（表示名）
- **投稿日:** YYYY-MM-DD
- **内容:** 投稿本文の要約
- **URL:** https://x.com/handle/status/xxxxx
- **検証:** 検索経路の説明（例: まとめサイト経由で確認）

### 埋め込みコード

<blockquote class="twitter-tweet"><a href="https://x.com/handle/status/xxxxx"></a></blockquote>
```

---

## 注意事項

- **見つからない場合は正直に報告する**。架空のURLを絶対に生成しない
- X.comのURLは `twitter.com` でも `x.com` でも動作するが、`x.com` に統一する
- 削除済み・非公開の投稿が判明した場合はその旨を報告する
- 埋め込みが表示されないリスクがある場合（非公開アカウント等）は代替案を提示する
