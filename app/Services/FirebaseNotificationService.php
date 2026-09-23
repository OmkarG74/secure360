<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserDeviceToken;

/**
 * Pure Core PHP Firebase Cloud Messaging (FCM) Service
 * Uses FCM HTTP v1 API with RS256 Google OAuth2 Service-Account Authentication
 *
 * Requirements:
 * - cURL extension
 * - OpenSSL extension
 * - Valid Google service-account JSON in storage/credentials/
 */
class FirebaseNotificationService
{
    private const OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const FCM_AUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const FCM_V1_SEND_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const DEFAULT_ANDROID_CHANNEL_ID = 'secure360_notifications';

    private const WAKEUP_ANDROID_CHANNEL_ID = 'secure360_wakeup';

    private ?array $serviceAccount = null;
    private string $projectRoot;
    private string $cacheFile;
    private UserDeviceToken $deviceTokenModel;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->cacheFile = $this->projectRoot . '/storage/cache/fcm_oauth_token.json';
        $this->deviceTokenModel = new UserDeviceToken();
    }

    /**
     * Send push notification to a specific FCM device registration token
     *
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array{success: bool, message_id: ?string, error: ?string, unregistered: bool}
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): array {
        $deviceToken = trim($deviceToken);
        if ($deviceToken === '') {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Device token is empty',
                'unregistered' => false,
            ];
        }

        $isWakeUp = in_array(($data['type'] ?? ''), ['wake_up', 'wake_up_call'], true) || (($data['screen'] ?? '') === 'wake_up');
        $channelId = $isWakeUp ? self::WAKEUP_ANDROID_CHANNEL_ID : ($data['channel_id'] ?? self::DEFAULT_ANDROID_CHANNEL_ID);
        $sound = $isWakeUp ? 'wake_up_alarm' : 'default';

        if ($isWakeUp) {
            // Urgent Wake-Up Call:
            // Send as high-priority data message for Android so Google Play Services
            // does NOT hijack it with a passive system-tray notice.
            // This invokes the Flutter background/foreground handler immediately,
            // which posts the Android full-screen intent and starts the looping alarm audio!
            $wakeUpData = array_merge($data, [
                'title' => $title,
                'message' => $body,
                'body' => $body,
                'type' => 'wake_up',
                'screen' => 'wake_up',
                'channel_id' => self::WAKEUP_ANDROID_CHANNEL_ID,
            ]);

            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'data' => $this->sanitizeDataPayload($wakeUpData),
                    'android' => [
                        'priority' => 'high',
                        'ttl' => '0s',
                    ],
                ],
            ];
        } else {
            // Standard notification: passive system tray notice
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->sanitizeDataPayload($data),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => $channelId,
                            'sound' => $sound,
                            'default_vibrate_timings' => true,
                        ],
                    ],
                ],
            ];
        }

        $result = $this->sendHttpRequest($payload);

        // If FCM reported the token is unregistered or invalid, deactivate it in the database
        if ($result['unregistered']) {
            $this->deviceTokenModel->deactivateToken($deviceToken);
        } else if ($result['success']) {
            $this->deviceTokenModel->updateLastUsed($deviceToken);
        }

        return $result;
    }

    /**
     * Send push notification to all active devices registered to a specific user
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array{total_devices: int, sent_count: int, failed_count: int, results: array}
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): array {
        $tokens = $this->deviceTokenModel->getActiveTokensByUser($userId);
        if (empty($tokens)) {
            return [
                'total_devices' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ];
        }

        $results = [];
        $sentCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            $res = $this->sendToDevice($token, $title, $body, $data);
            $results[$token] = $res;
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'total_devices' => count($tokens),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * Send push notification to multiple users
     *
     * @param int[] $userIds
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array{total_devices: int, sent_count: int, failed_count: int}
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = []
    ): array {
        $tokens = $this->deviceTokenModel->getActiveTokensByUsers($userIds);
        if (empty($tokens)) {
            return [
                'total_devices' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
            ];
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            $res = $this->sendToDevice($token, $title, $body, $data);
            if ($res['success']) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return [
            'total_devices' => count($tokens),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * Explicitly deactivate an invalid or unregistered device token
     */
    public function deactivateInvalidToken(string $deviceToken): bool
    {
        return $this->deviceTokenModel->deactivateToken($deviceToken);
    }

    /**
     * Send push notification to an FCM topic (e.g. 'all_guards', 'org_12')
     *
     * @param string $topic
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array{success: bool, message_id: ?string, error: ?string, unregistered: bool}
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = []
    ): array {
        $topic = ltrim(trim($topic), '/topics/');
        if ($topic === '') {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Topic name is empty',
                'unregistered' => false,
            ];
        }

        $payload = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->sanitizeDataPayload($data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => self::DEFAULT_ANDROID_CHANNEL_ID,
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                    ],
                ],
            ],
        ];

        return $this->sendHttpRequest($payload);
    }

    /**
     * Dispatch HTTP POST request to FCM HTTP v1 endpoint
     */
    private function sendHttpRequest(array $messagePayload): array
    {
        try {
            $accessToken = $this->getValidAccessToken();
            $projectId = $this->getProjectId();

            $url = sprintf(self::FCM_V1_SEND_URL, $projectId);
            $jsonPayload = json_encode($messagePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json; UTF-8',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($curlError)) {
                error_log("[FCM] Network request failed: {$curlError}");
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => 'Network error contacting FCM service',
                    'unregistered' => false,
                ];
            }

            $decoded = json_decode($response, true) ?: [];

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message_id' => $decoded['name'] ?? null,
                    'error' => null,
                    'unregistered' => false,
                ];
            }

            // Inspect error response
            $errorDetails = $decoded['error'] ?? [];
            $errorCode = (string)($errorDetails['status'] ?? '');
            $errorMessage = (string)($errorDetails['message'] ?? 'FCM error occurred');

            $isUnregistered = ($errorCode === 'UNREGISTERED' || $errorCode === 'NOT_FOUND');
            if (!$isUnregistered && isset($errorDetails['details']) && is_array($errorDetails['details'])) {
                foreach ($errorDetails['details'] as $detail) {
                    $errorCodeDetail = (string)($detail['errorCode'] ?? '');
                    if ($errorCodeDetail === 'UNREGISTERED') {
                        $isUnregistered = true;
                        break;
                    }
                }
            }

            if (!$isUnregistered && (
                str_contains(strtolower($errorMessage), 'not registered') ||
                str_contains(strtolower($errorMessage), 'not a valid fcm registration token') ||
                str_contains(strtolower($errorMessage), 'invalid registration token')
            )) {
                $isUnregistered = true;
            }

            error_log("[FCM] HTTP {$httpCode} Error: {$errorMessage} (Status: {$errorCode})");

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'unregistered' => $isUnregistered,
            ];
        } catch (\Throwable $e) {
            error_log("[FCM] Exception sending push notification: " . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'unregistered' => false,
            ];
        }
    }

    /**
     * Retrieve a valid Google OAuth2 Access Token.
     * Uses filesystem caching in storage/cache/ to minimize OAuth requests.
     */
    public function getValidAccessToken(): string
    {
        // 1. Check cached token
        if (file_exists($this->cacheFile)) {
            $cacheContent = @file_get_contents($this->cacheFile);
            if ($cacheContent) {
                $cached = json_decode($cacheContent, true);
                if (!empty($cached['access_token']) && !empty($cached['expires_at'])) {
                    // Buffer of 120 seconds before expiration
                    if ($cached['expires_at'] > (time() + 120)) {
                        return (string)$cached['access_token'];
                    }
                }
            }
        }

        // 2. Mint a new token
        $sa = $this->getServiceAccount();
        $clientEmail = (string)($sa['client_email'] ?? '');
        $privateKey = (string)($sa['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Invalid Firebase service account credentials.');
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode((string)json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaimSet = $this->base64UrlEncode((string)json_encode([
            'iss' => $clientEmail,
            'scope' => self::FCM_AUTH_SCOPE,
            'aud' => self::OAUTH_TOKEN_URI,
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signInput = "{$jwtHeader}.{$jwtClaimSet}";
        $signature = '';

        $pkeyRes = openssl_pkey_get_private($privateKey);
        if ($pkeyRes === false) {
            throw new \RuntimeException('Failed to parse Firebase private key with OpenSSL.');
        }

        $signed = openssl_sign($signInput, $signature, $pkeyRes, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new \RuntimeException('OpenSSL failed to sign Google OAuth2 assertion.');
        }

        $jwt = "{$signInput}." . $this->base64UrlEncode($signature);

        // Request OAuth2 access token via cURL
        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::OAUTH_TOKEN_URI,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new \RuntimeException('Failed to request OAuth2 token from Google: ' . $curlError);
        }

        $tokenData = json_decode($response, true);
        if ($httpCode !== 200 || empty($tokenData['access_token'])) {
            $errorDesc = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown OAuth error');
            throw new \RuntimeException("Google OAuth2 token error ({$httpCode}): {$errorDesc}");
        }

        $accessToken = (string)$tokenData['access_token'];
        $expiresIn = (int)($tokenData['expires_in'] ?? 3600);

        // Cache the token
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents(
            $this->cacheFile,
            json_encode([
                'access_token' => $accessToken,
                'expires_at' => $now + $expiresIn,
            ])
        );

        return $accessToken;
    }

    /**
     * Get Firebase Project ID from the service account JSON
     */
    public function getProjectId(): string
    {
        $sa = $this->getServiceAccount();
        $projectId = (string)($sa['project_id'] ?? '');
        if ($projectId === '') {
            throw new \RuntimeException('Firebase project_id could not be resolved from service account.');
        }
        return $projectId;
    }

    /**
     * Load and validate the Firebase service-account JSON
     *
     * @return array<string, mixed>
     */
    private function getServiceAccount(): array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        $relPath = (string)(getenv('FIREBASE_CREDENTIALS') ?: 'storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json');
        $fullPath = $this->resolvePath($relPath);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Firebase credentials file not found: {$relPath}");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read Firebase credentials file: {$relPath}");
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || empty($decoded['project_id']) || empty($decoded['private_key'])) {
            throw new \RuntimeException("Invalid or incomplete Firebase service account JSON: {$relPath}");
        }

        $this->serviceAccount = $decoded;
        return $this->serviceAccount;
    }

    /**
     * Resolve a relative or absolute path against project root
     */
    private function resolvePath(string $path): string
    {
        // Check if path is already absolute (Windows drive letter or Unix root)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    /**
     * Sanitize and format data payload for FCM HTTP v1.
     * Every key and value in the FCM data payload MUST be a string.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function sanitizeDataPayload(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $val) {
            $keyStr = (string)$key;
            if (is_bool($val)) {
                $clean[$keyStr] = $val ? '1' : '0';
            } elseif (is_scalar($val)) {
                $clean[$keyStr] = (string)$val;
            } elseif (is_null($val)) {
                $clean[$keyStr] = '';
            } else {
                $clean[$keyStr] = (string)json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }
        return $clean;
    }

    /**
     * Base64Url encode string for RFC 7519 JWT
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
