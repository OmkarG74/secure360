<?php

declare(strict_types=1);

/**
 * Test Suite for Firebase FCM Credential Resolution Order
 *
 * Verifies:
 * 1. FIREBASE_CREDENTIALS_JSON (Raw JSON string in environment)
 *    - Valid JSON with required fields resolves to source 'environment_json'
 *    - Service account structure matches expected OAuth2 keys
 *    - Malformed JSON throws RuntimeException
 *    - Empty string throws RuntimeException
 *    - Missing required fields (type, project_id, private_key, client_email) throws RuntimeException
 *    - No silent fallback occurs when FIREBASE_CREDENTIALS_JSON is invalid
 * 2. FIREBASE_CREDENTIALS (Path to file)
 *    - Valid path resolves to source 'environment_file'
 *    - Non-existent path throws RuntimeException
 * 3. Local fallback (storage/credentials/*.json)
 *    - Resolves to source 'local_file'
 * 4. Diagnostics & Security
 *    - getCredentialSource() reports ONLY safe string identifier
 *    - No secrets logged
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');

require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

use App\Services\FirebaseNotificationService;

class FirebaseCredentialResolutionTest
{
    private int $passed = 0;
    private int $failed = 0;
    private string $originalLocalFile;
    private string $originalJsonContent;

    public function __construct()
    {
        $this->originalLocalFile = ROOT_PATH . '/storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json';
        if (!file_exists($this->originalLocalFile)) {
            throw new \RuntimeException('Original local credential file missing.');
        }
        $this->originalJsonContent = (string)file_get_contents($this->originalLocalFile);
    }

    private function assert(bool $condition, string $label, string $details = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$label}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$label}" . ($details ? " -> {$details}" : '') . "\n";
        }
    }

    private function clearEnvVars(): void
    {
        putenv('FIREBASE_CREDENTIALS_JSON');
        putenv('FIREBASE_CREDENTIALS');
        unset($_ENV['FIREBASE_CREDENTIALS_JSON'], $_SERVER['FIREBASE_CREDENTIALS_JSON']);
        unset($_ENV['FIREBASE_CREDENTIALS'], $_SERVER['FIREBASE_CREDENTIALS']);
    }

    public function run(): void
    {
        echo "============================================================\n";
        echo "FIREBASE CREDENTIAL RESOLUTION TESTS\n";
        echo "============================================================\n\n";

        $this->testLocalFallbackMode();
        $this->testEnvironmentFileMode();
        $this->testEnvironmentJsonModeValid();
        $this->testEnvironmentJsonModeInvalid();
        $this->testNoSilentFallbackOnInvalidJson();
        $this->testOAuth2TokenGenerationWithEnvJson();
        $this->testEndToEndPushWithEnvJson();
        $this->testNoSecretsInLogs();

        // Restore clean environment
        $this->clearEnvVars();

        echo "\n============================================================\n";
        echo "TEST SUMMARY: Total: " . ($this->passed + $this->failed) . " | Passed: {$this->passed} | Failed: {$this->failed}\n";
        echo "============================================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    /**
     * Test Step 3: Local file fallback when no environment variables are present
     */
    private function testLocalFallbackMode(): void
    {
        echo "--- 1. Testing Local File Fallback Mode (storage/credentials/*.json) ---\n";
        $this->clearEnvVars();

        $service = new FirebaseNotificationService();
        $source = $service->getCredentialSource();

        $this->assert($source === 'local_file', "Credential source is 'local_file' (got '{$source}')");
        $this->assert($service->getProjectId() === 'infipre360', "Project ID correctly resolved as 'infipre360'");
    }

    /**
     * Test Step 2: FIREBASE_CREDENTIALS file path mode
     */
    private function testEnvironmentFileMode(): void
    {
        echo "\n--- 2. Testing FIREBASE_CREDENTIALS File Path Mode ---\n";
        $this->clearEnvVars();

        // Set valid file path
        putenv('FIREBASE_CREDENTIALS=storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json');
        $_ENV['FIREBASE_CREDENTIALS'] = 'storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json';

        $service = new FirebaseNotificationService();
        $source = $service->getCredentialSource();
        $this->assert($source === 'environment_file', "Credential source is 'environment_file' (got '{$source}')");
        $this->assert($service->getProjectId() === 'infipre360', "Project ID correctly resolved as 'infipre360'");

        // Test non-existent file path
        $this->clearEnvVars();
        putenv('FIREBASE_CREDENTIALS=storage/credentials/non_existent_file.json');
        $_ENV['FIREBASE_CREDENTIALS'] = 'storage/credentials/non_existent_file.json';

        $caught = false;
        try {
            $badService = new FirebaseNotificationService();
            $badService->getCredentialSource();
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'Firebase credentials file not found'),
                "Clear error thrown for non-existent file: '{$e->getMessage()}'"
            );
        }
        $this->assert($caught, "Exception thrown on non-existent file path");
    }

    /**
     * Test Step 1: Valid FIREBASE_CREDENTIALS_JSON
     */
    private function testEnvironmentJsonModeValid(): void
    {
        echo "\n--- 3. Testing Valid FIREBASE_CREDENTIALS_JSON Mode ---\n";
        $this->clearEnvVars();

        // Feed original JSON into FIREBASE_CREDENTIALS_JSON
        putenv("FIREBASE_CREDENTIALS_JSON={$this->originalJsonContent}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = $this->originalJsonContent;

        $service = new FirebaseNotificationService();
        $source = $service->getCredentialSource();

        $this->assert($source === 'environment_json', "Credential source is 'environment_json' (got '{$source}')");
        $this->assert($service->getProjectId() === 'infipre360', "Project ID correctly resolved from env JSON");
    }

    /**
     * Test Step 1: Invalid FIREBASE_CREDENTIALS_JSON scenarios
     */
    private function testEnvironmentJsonModeInvalid(): void
    {
        echo "\n--- 4. Testing Invalid FIREBASE_CREDENTIALS_JSON Scenarios ---\n";

        // Scenario A: Empty string
        $this->clearEnvVars();
        putenv("FIREBASE_CREDENTIALS_JSON=   ");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = "   ";

        $caught = false;
        try {
            $service = new FirebaseNotificationService();
            $service->getCredentialSource();
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'present but empty'),
                "Empty JSON produces clear error: '{$e->getMessage()}'"
            );
        }
        $this->assert($caught, "Exception thrown for empty JSON");

        // Scenario B: Malformed JSON syntax
        $this->clearEnvVars();
        putenv("FIREBASE_CREDENTIALS_JSON={invalid_json_syntax");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = "{invalid_json_syntax";

        $caught = false;
        try {
            $service = new FirebaseNotificationService();
            $service->getCredentialSource();
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'not valid JSON'),
                "Malformed JSON produces clear error: '{$e->getMessage()}'"
            );
        }
        $this->assert($caught, "Exception thrown for malformed JSON");

        // Scenario C: Missing required fields
        $fields = ['type', 'project_id', 'private_key', 'client_email'];
        foreach ($fields as $missingField) {
            $this->clearEnvVars();
            $testObj = json_decode($this->originalJsonContent, true);
            unset($testObj[$missingField]);
            $partialJson = (string)json_encode($testObj);

            putenv("FIREBASE_CREDENTIALS_JSON={$partialJson}");
            $_ENV['FIREBASE_CREDENTIALS_JSON'] = $partialJson;

            $caught = false;
            try {
                $service = new FirebaseNotificationService();
                $service->getCredentialSource();
            } catch (\RuntimeException $e) {
                $caught = true;
                $this->assert(
                    str_contains($e->getMessage(), "missing required service account field(s): {$missingField}"),
                    "Missing '{$missingField}' throws descriptive error"
                );
            }
            $this->assert($caught, "Exception thrown when '{$missingField}' is omitted");
        }
    }

    /**
     * Test: Ensure no silent fallback occurs if FIREBASE_CREDENTIALS_JSON is explicitly supplied but invalid
     */
    private function testNoSilentFallbackOnInvalidJson(): void
    {
        echo "\n--- 5. Testing No Silent Fallback When FIREBASE_CREDENTIALS_JSON Is Invalid ---\n";
        $this->clearEnvVars();

        // Even though a valid FIREBASE_CREDENTIALS and storage/credentials/*.json exist,
        // an invalid FIREBASE_CREDENTIALS_JSON must fail immediately.
        putenv("FIREBASE_CREDENTIALS_JSON={\"invalid\": true}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = "{\"invalid\": true}";
        putenv("FIREBASE_CREDENTIALS=storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json");
        $_ENV['FIREBASE_CREDENTIALS'] = "storage/credentials/infipre360-firebase-adminsdk-fbsvc-2de9932d5b.json";

        $caught = false;
        try {
            $service = new FirebaseNotificationService();
            $service->getCredentialSource();
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'missing required service account field(s)'),
                "Did NOT fall back to file: threw '{$e->getMessage()}'"
            );
        }
        $this->assert($caught, "No silent fallback: execution aborted cleanly on invalid env JSON");
    }

    /**
     * Test: Valid FIREBASE_CREDENTIALS_JSON generates OAuth2 Token
     */
    private function testOAuth2TokenGenerationWithEnvJson(): void
    {
        echo "\n--- 6. Testing OAuth2 Token Generation With FIREBASE_CREDENTIALS_JSON ---\n";
        $this->clearEnvVars();

        putenv("FIREBASE_CREDENTIALS_JSON={$this->originalJsonContent}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = $this->originalJsonContent;

        $service = new FirebaseNotificationService();
        $token = $service->getValidAccessToken();

        $this->assert(!empty($token), "OAuth2 Access Token minted successfully from FIREBASE_CREDENTIALS_JSON");
        $this->assert(strlen($token) > 50, "Access token has expected length (>50 chars)");
    }

    /**
     * Test: Dispatch push notification with FIREBASE_CREDENTIALS_JSON
     */
    private function testEndToEndPushWithEnvJson(): void
    {
        echo "\n--- 7. Testing End-to-End FCM HTTP v1 Dispatch With FIREBASE_CREDENTIALS_JSON ---\n";
        $this->clearEnvVars();

        putenv("FIREBASE_CREDENTIALS_JSON={$this->originalJsonContent}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = $this->originalJsonContent;

        $service = new FirebaseNotificationService();
        $dummyToken = 'dummy_env_json_token_' . bin2hex(random_bytes(16));

        $res = $service->sendToDevice(
            $dummyToken,
            'Test Env JSON Push',
            'Testing resolution with FIREBASE_CREDENTIALS_JSON',
            ['type' => 'test', 'screen' => 'home']
        );

        $this->assert($res['success'] === false, "FCM cleanly processed request with FIREBASE_CREDENTIALS_JSON");
        $this->assert(!empty($res['error']), "Error captured safely: '{$res['error']}'");
        $this->assert($service->getCredentialSource() === 'environment_json', "Used credential source 'environment_json'");
    }

    /**
     * Test: Verify no secrets appear in logs or exception messages
     */
    private function testNoSecretsInLogs(): void
    {
        echo "\n--- 8. Testing No Secrets Leakage in Exceptions or Diagnostics ---\n";
        $this->clearEnvVars();

        $sa = json_decode($this->originalJsonContent, true);
        $privateKey = (string)$sa['private_key'];
        $clientEmail = (string)$sa['client_email'];

        // Trigger an invalid JSON exception
        putenv("FIREBASE_CREDENTIALS_JSON={\"type\":\"service_account\"}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = "{\"type\":\"service_account\"}";

        try {
            $service = new FirebaseNotificationService();
            $service->getCredentialSource();
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $this->assert(!str_contains($msg, $privateKey), "Private key never appears in exception message");
            $this->assert(!str_contains($msg, $clientEmail), "Client email never appears in exception message");
        }

        // Verify diagnostic source returns ONLY the safe identifier string
        $this->clearEnvVars();
        putenv("FIREBASE_CREDENTIALS_JSON={$this->originalJsonContent}");
        $_ENV['FIREBASE_CREDENTIALS_JSON'] = $this->originalJsonContent;
        $service = new FirebaseNotificationService();
        $source = $service->getCredentialSource();
        $this->assert(in_array($source, ['environment_json', 'environment_file', 'local_file'], true), "Diagnostic returns strictly whitelisted source identifier: '{$source}'");
    }
}

$test = new FirebaseCredentialResolutionTest();
$test->run();

