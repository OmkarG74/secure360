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
    private ?string $credentialSource = null;
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
        error_log("[FirebaseNotificationService] Device count for user {$userId}: " . count($tokens));
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
            $curlOptions = [
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
                CURLOPT_SSL_VERIFYHOST => 2,
            ];

            $caBundle = $this->resolveCaBundlePath();
            if ($caBundle !== null) {
                $curlOptions[CURLOPT_CAINFO] = $caBundle;
            }

            curl_setopt_array($ch, $curlOptions);

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
                $msgId = $decoded['name'] ?? 'none';
                error_log("[FirebaseNotificationService] FCM HTTP {$httpCode} success: message_id={$msgId}");
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

            error_log("[FirebaseNotificationService] FCM HTTP {$httpCode} error: {$errorMessage} (code: {$errorCode})");

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
        $curlOptions = [
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
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caBundle = $this->resolveCaBundlePath();
        if ($caBundle !== null) {
            $curlOptions[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($ch, $curlOptions);

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
            error_log("[FirebaseNotificationService] OAuth2 failure: {$errorDesc} (HTTP {$httpCode})");
            throw new \RuntimeException("Google OAuth2 token error ({$httpCode}): {$errorDesc}");
        }

        $accessToken = (string)$tokenData['access_token'];
        $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
        error_log("[FirebaseNotificationService] OAuth2 success: token obtained for project {$sa['project_id']}");

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
     * Safe diagnostic method returning ONLY the credential source identifier:
     * 'environment_json', 'environment_file', or 'local_file'.
     * Never exposes any credential contents or secrets.
     */
    public function getCredentialSource(): string
    {
        if ($this->credentialSource === null) {
            $this->getServiceAccount();
        }

        return (string)$this->credentialSource;
    }

    /**
     * Load and validate Firebase service-account credentials according to resolution order:
     * 1. FIREBASE_CREDENTIALS_JSON (raw JSON string in environment variable)
     * 2. FIREBASE_CREDENTIALS (path to a readable JSON file)
     * 3. Local fallback (storage/credentials/*.json)
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function getServiceAccount(): array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        // -------------------------------------------------------------------------
        // 1. FIREBASE_CREDENTIALS_JSON
        // -------------------------------------------------------------------------
        $hasEnvJson = (getenv('FIREBASE_CREDENTIALS_JSON') !== false)
            || array_key_exists('FIREBASE_CREDENTIALS_JSON', $_ENV)
            || array_key_exists('FIREBASE_CREDENTIALS_JSON', $_SERVER);

        if ($hasEnvJson) {
            $rawJson = getenv('FIREBASE_CREDENTIALS_JSON');
            if ($rawJson === false) {
                $rawJson = $_ENV['FIREBASE_CREDENTIALS_JSON'] ?? ($_SERVER['FIREBASE_CREDENTIALS_JSON'] ?? '');
            }

            $trimmed = trim((string)$rawJson);
            if ($trimmed === '') {
                throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON environment variable is present but empty.');
            }

            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('FIREBASE_CREDENTIALS_JSON environment variable is not valid JSON.');
            }

            $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($decoded[$field]) || !is_string($decoded[$field]) || trim($decoded[$field]) === '') {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                throw new \RuntimeException(
                    'FIREBASE_CREDENTIALS_JSON is missing required service account field(s): ' . implode(', ', $missingFields)
                );
            }

            if (str_contains($decoded['private_key'], '\n') && !str_contains($decoded['private_key'], "\n")) {
                $decoded['private_key'] = str_replace('\n', "\n", $decoded['private_key']);
            }

            $this->credentialSource = 'environment_json';
            $this->serviceAccount = $decoded;
            error_log("[FirebaseNotificationService] Credential source: {$this->credentialSource}");
            return $this->serviceAccount;
        }

        // -------------------------------------------------------------------------
        // 2. FIREBASE_CREDENTIALS (file path)
        // -------------------------------------------------------------------------
        $hasEnvFile = (getenv('FIREBASE_CREDENTIALS') !== false && trim((string)getenv('FIREBASE_CREDENTIALS')) !== '')
            || (!empty($_ENV['FIREBASE_CREDENTIALS']) && trim((string)$_ENV['FIREBASE_CREDENTIALS']) !== '')
            || (!empty($_SERVER['FIREBASE_CREDENTIALS']) && trim((string)$_SERVER['FIREBASE_CREDENTIALS']) !== '');

        if ($hasEnvFile) {
            $relPath = (string)(getenv('FIREBASE_CREDENTIALS') ?: ($_ENV['FIREBASE_CREDENTIALS'] ?? $_SERVER['FIREBASE_CREDENTIALS']));
            $relPath = trim($relPath);
            $fullPath = $this->resolvePath($relPath);

            if (!file_exists($fullPath)) {
                throw new \RuntimeException("Firebase credentials file not found: {$relPath}");
            }

            if (!is_readable($fullPath)) {
                throw new \RuntimeException("Cannot read Firebase credentials file: {$relPath}");
            }

            $content = file_get_contents($fullPath);
            if ($content === false) {
                throw new \RuntimeException("Cannot read Firebase credentials file: {$relPath}");
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded) || empty($decoded['project_id']) || empty($decoded['private_key'])) {
                throw new \RuntimeException("Invalid or incomplete Firebase service account JSON: {$relPath}");
            }

            if (isset($decoded['private_key']) && is_string($decoded['private_key']) && str_contains($decoded['private_key'], '\n') && !str_contains($decoded['private_key'], "\n")) {
                $decoded['private_key'] = str_replace('\n', "\n", $decoded['private_key']);
            }

            $this->credentialSource = 'environment_file';
            $this->serviceAccount = $decoded;
            error_log("[FirebaseNotificationService] Credential source: {$this->credentialSource}");
            return $this->serviceAccount;
        }

        // -------------------------------------------------------------------------
        // 3. Existing local fallback: storage/credentials/*.json
        // -------------------------------------------------------------------------
        $defaultRelative = 'storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json';
        $defaultPath = $this->resolvePath($defaultRelative);

        $localFile = null;
        if (file_exists($defaultPath) && is_readable($defaultPath)) {
            $localFile = $defaultPath;
        } else {
            $pattern = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'credentials' . DIRECTORY_SEPARATOR . '*.json';
            $matches = glob($pattern) ?: [];
            foreach ($matches as $match) {
                if (is_file($match) && is_readable($match)) {
                    $localFile = $match;
                    break;
                }
            }
        }

        if ($localFile === null) {
            throw new \RuntimeException('No Firebase credentials found. Set FIREBASE_CREDENTIALS_JSON or FIREBASE_CREDENTIALS, or place credentials in storage/credentials/.');
        }

        $content = file_get_contents($localFile);
        if ($content === false) {
            throw new \RuntimeException('Cannot read Firebase credentials file: ' . basename($localFile));
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || empty($decoded['project_id']) || empty($decoded['private_key'])) {
            throw new \RuntimeException('Invalid or incomplete Firebase service account JSON: ' . basename($localFile));
        }

        if (isset($decoded['private_key']) && is_string($decoded['private_key']) && str_contains($decoded['private_key'], '\n') && !str_contains($decoded['private_key'], "\n")) {
            $decoded['private_key'] = str_replace('\n', "\n", $decoded['private_key']);
        }

        $this->credentialSource = 'local_file';
        $this->serviceAccount = $decoded;
        error_log("[FirebaseNotificationService] Credential source: {$this->credentialSource}");
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

    /**
     * Resolves a valid CA certificate bundle path for secure cURL SSL verification.
     * 1. If PHP's curl.cainfo or openssl.cafile is explicitly set and readable, returns null (PHP handles it natively).
     * 2. Otherwise, detects known valid local CA bundles (e.g. storage/credentials/cacert.pem or WAMP phpMyAdmin bundle).
     * 3. Returns null on standard Linux/Debian environments where system CAs (/etc/ssl/certs/) are used automatically.
     */
    private function resolveCaBundlePath(): ?string
    {
        // 1. If PHP already has a configured and readable CA bundle, rely on it natively
        $iniCurlCa = (string)ini_get('curl.cainfo');
        if ($iniCurlCa !== '' && file_exists($iniCurlCa) && is_readable($iniCurlCa)) {
            return null;
        }

        $iniOpensslCa = (string)ini_get('openssl.cafile');
        if ($iniOpensslCa !== '' && file_exists($iniOpensslCa) && is_readable($iniOpensslCa)) {
            return null;
        }

        // 2. Candidate paths on local Windows / WAMP environments
        $candidates = [
            $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'credentials' . DIRECTORY_SEPARATOR . 'cacert.pem',
            'C:\\wamp64\\apps\\phpmyadmin5.2.3\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
            'C:\\wamp64\\bin\\php\\cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_readable($candidate) && filesize($candidate) > 10000) {
                return $candidate;
            }
        }

        return null;
    }
}
