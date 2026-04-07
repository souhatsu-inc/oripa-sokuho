<?php

declare(strict_types=1);

namespace App;

/**
 * X API v2 検索クライアント（Bearer Token認証）
 *
 * App-only認証で Recent Search を行い、結果をファイルキャッシュする。
 */
class XSearch
{
    private const API_BASE = 'https://api.x.com/2';
    private const TOKEN_URL = 'https://api.x.com/oauth2/token';

    private string $consumerKey;
    private string $consumerSecret;
    private string $cacheDir;
    private int $cacheTtl;

    public function __construct(array $config, string $cacheDir = '', int $cacheTtl = 3600)
    {
        $this->consumerKey = $config['consumer_key'];
        $this->consumerSecret = $config['consumer_secret'];
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../data/cache';
        $this->cacheTtl = $cacheTtl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * プレキャン（プレゼントキャンペーン）ツイートを検索
     *
     * @param string $keyword カテゴリキーワード（例: "ポケカ"）
     * @param int    $maxResults 取得件数（10〜100）
     * @return array{tweets: array, users: array, cached: bool}
     */
    public function searchCampaignTweets(string $keyword, int $maxResults = 20): array
    {
        $cacheKey = 'campaign_' . md5($keyword);
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        $bearer = $this->getBearerToken();

        $query = "{$keyword} (プレゼント OR プレゼントキャンペーン OR プレキャン OR プレゼント企画) (フォロー OR リポスト OR RT) -is:retweet";
        $startTime = date('Y-m-d\TH:i:s\Z', strtotime('-7 days'));

        $params = http_build_query([
            'query' => $query,
            'start_time' => $startTime,
            'max_results' => min($maxResults, 100),
            'tweet.fields' => 'created_at,author_id,public_metrics',
            'expansions' => 'author_id',
            'user.fields' => 'username,name',
        ]);

        $url = self::API_BASE . "/tweets/search/recent?{$params}";

        $response = $this->httpGet($url, $bearer);

        $tweets = [];
        $users = [];

        if (isset($response['includes']['users'])) {
            foreach ($response['includes']['users'] as $u) {
                $users[$u['id']] = $u;
            }
        }

        if (isset($response['data'])) {
            foreach ($response['data'] as $tweet) {
                $user = $users[$tweet['author_id']] ?? null;
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'created_at' => $tweet['created_at'],
                    'username' => $user['username'] ?? '',
                    'name' => $user['name'] ?? '',
                    'metrics' => $tweet['public_metrics'] ?? [],
                ];
            }
        }

        $result = ['tweets' => $tweets, 'users' => $users];
        $this->setCache($cacheKey, $result);

        return array_merge($result, ['cached' => false]);
    }

    private function getBearerToken(): string
    {
        $cacheKey = 'bearer_token';
        $cached = $this->getCache($cacheKey);
        if ($cached !== null && isset($cached['token'])) {
            return $cached['token'];
        }

        $credentials = base64_encode(
            urlencode($this->consumerKey) . ':' . urlencode($this->consumerSecret)
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException('Bearer Token取得失敗 (HTTP ' . $httpCode . ')');
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \RuntimeException('Bearer Token取得失敗: access_token なし');
        }

        $this->setCache($cacheKey, ['token' => $data['access_token']], 3600);

        return $data['access_token'];
    }

    private function httpGet(string $url, string $bearerToken): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearerToken],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException('X API検索エラー (HTTP ' . $httpCode . '): ' . $response);
        }

        return json_decode($response, true) ?? [];
    }

    private function getCache(string $key): ?array
    {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['expires_at'])) {
            return null;
        }

        if (time() > $data['expires_at']) {
            unlink($file);
            return null;
        }

        return $data['payload'];
    }

    private function setCache(string $key, array $payload, ?int $ttl = null): void
    {
        $file = $this->cacheDir . '/' . $key . '.json';
        $data = [
            'expires_at' => time() + ($ttl ?? $this->cacheTtl),
            'payload' => $payload,
        ];
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
