<?php

declare(strict_types=1);

namespace App;

/**
 * X (Twitter) API v2 クライアント
 *
 * OAuth 1.0a 署名によるツイート投稿・画像アップロード・リプライを行う。
 * 外部ライブラリ不要。
 */
class TwitterClient
{
    private const API_BASE = 'https://api.x.com/2';
    private const UPLOAD_BASE = 'https://upload.twitter.com/1.1';

    private string $consumerKey;
    private string $consumerSecret;
    private string $accessToken;
    private string $accessTokenSecret;

    public function __construct(array $config)
    {
        $this->consumerKey = $config['consumer_key'];
        $this->consumerSecret = $config['consumer_secret'];
        $this->accessToken = $config['access_token'];
        $this->accessTokenSecret = $config['access_token_secret'];
    }

    /**
     * ツイートを投稿する
     *
     * @param string      $text    投稿本文
     * @param string|null $mediaId アップロード済み画像のmedia_id（任意）
     * @return array{id: string, text: string} 投稿結果
     */
    public function tweet(string $text, ?string $mediaId = null): array
    {
        $body = ['text' => $text];

        if ($mediaId !== null) {
            $body['media'] = ['media_ids' => [$mediaId]];
        }

        $response = $this->request('POST', self::API_BASE . '/tweets', $body);

        if (!isset($response['data']['id'])) {
            throw new \RuntimeException('ツイート投稿に失敗: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['data'];
    }

    /**
     * リプライを投稿する
     *
     * @param string $text           リプライ本文
     * @param string $replyToTweetId リプライ先のツイートID
     * @return array{id: string, text: string}
     */
    public function reply(string $text, string $replyToTweetId): array
    {
        $body = [
            'text' => $text,
            'reply' => ['in_reply_to_tweet_id' => $replyToTweetId],
        ];

        $response = $this->request('POST', self::API_BASE . '/tweets', $body);

        if (!isset($response['data']['id'])) {
            throw new \RuntimeException('リプライ投稿に失敗: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['data'];
    }

    /**
     * 画像をアップロードしてmedia_idを取得する
     *
     * @param string $imageUrl 画像URL
     * @return string media_id_string
     */
    public function uploadImageFromUrl(string $imageUrl): string
    {
        // 画像をダウンロード
        $imageData = file_get_contents($imageUrl);
        if ($imageData === false) {
            throw new \RuntimeException('画像ダウンロードに失敗: ' . $imageUrl);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        // media/upload (v1.1) は multipart/form-data
        $url = self::UPLOAD_BASE . '/media/upload.json';

        $oauthParams = $this->buildOAuthParams();
        // multipart時はbodyパラメータをOAuth署名に含めない
        $oauthParams['oauth_signature'] = $this->generateSignature('POST', $url, $oauthParams);

        $boundary = bin2hex(random_bytes(16));
        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media_data\"\r\n\r\n";
        $body .= base64_encode($imageData) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"media_category\"\r\n\r\n";
        $body .= "tweet_image\r\n";
        $body .= "--{$boundary}--\r\n";

        $headers = [
            'Authorization: ' . $this->buildAuthorizationHeader($oauthParams),
            'Content-Type: multipart/form-data; boundary=' . $boundary,
        ];

        $response = $this->httpRequest('POST', $url, $headers, $body);

        if (!isset($response['media_id_string'])) {
            throw new \RuntimeException('画像アップロードに失敗: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        return $response['media_id_string'];
    }

    /**
     * 記事からX投稿を行う（1投稿目 + リプ）
     *
     * @param string $mainText     1投稿目の本文
     * @param string $replyText    リプの本文（リンク含む）
     * @param string $thumbnailUrl サムネイル画像URL（空なら画像なし）
     * @return array{tweet_id: string, reply_id: string}
     */
    public function postArticle(string $mainText, string $replyText, string $thumbnailUrl = ''): array
    {
        $mediaId = null;
        if ($thumbnailUrl !== '') {
            try {
                $mediaId = $this->uploadImageFromUrl($thumbnailUrl);
            } catch (\Throwable $e) {
                // 画像アップロード失敗時はテキストのみで投稿
                error_log('[TwitterClient] 画像アップロード失敗、テキストのみで投稿: ' . $e->getMessage());
            }
        }

        // 1投稿目
        $tweet = $this->tweet($mainText, $mediaId);

        // リプ（1投稿目への返信）
        $reply = $this->reply($replyText, $tweet['id']);

        return [
            'tweet_id' => $tweet['id'],
            'reply_id' => $reply['id'],
        ];
    }

    // ─── OAuth 1.0a 署名 ───

    private function request(string $method, string $url, array $jsonBody): array
    {
        $oauthParams = $this->buildOAuthParams();
        $oauthParams['oauth_signature'] = $this->generateSignature($method, $url, $oauthParams);

        $headers = [
            'Authorization: ' . $this->buildAuthorizationHeader($oauthParams),
            'Content-Type: application/json',
        ];

        return $this->httpRequest($method, $url, $headers, json_encode($jsonBody));
    }

    private function buildOAuthParams(): array
    {
        return [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];
    }

    private function generateSignature(string $method, string $url, array $params): string
    {
        ksort($params);

        $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->accessTokenSecret);

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }

    private function buildAuthorizationHeader(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth ' . implode(', ', $parts);
    }

    private function httpRequest(string $method, string $url, array $headers, string $body): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            throw new \RuntimeException('cURLエラー: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            throw new \RuntimeException("X API エラー (HTTP {$httpCode}): " . ($response ?: '空レスポンス'));
        }

        return $decoded ?? [];
    }
}
