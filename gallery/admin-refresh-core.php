<?php
// gallery/admin-refresh-core.php
// Core token refresh helper for gallery admin tools.

function gallery_refresh_instagram_token(string $tokenFile, array $opts = []): array
{
    $logFile = $opts['logFile'] ?? __DIR__ . '/refresh.log';
    $timeoutConnect = $opts['connect_timeout'] ?? 5;
    $timeout = $opts['timeout'] ?? 10;

    $log = function ($m) use ($logFile) {
        $line = '[' . date('c') . '] ' . $m . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    };

    if (!is_file($tokenFile)) {
        return ['ok' => false, 'message' => 'token file missing', 'payload' => null];
    }
    $current = trim(@file_get_contents($tokenFile));
    if ($current === '') {
        return ['ok' => false, 'message' => 'token file empty', 'payload' => null];
    }

    $refreshUrl = 'https://graph.instagram.com/refresh_access_token'
        . '?grant_type=ig_refresh_token&access_token=' . urlencode($current);

    $response = false;
    $httpCode = 0;
    $curlErr = null;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $refreshUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutConnect);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GalleryTokenRefresher/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            $log('cURL error: ' . $curlErr);
        }
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: GalleryTokenRefresher/1.0\r\n"]]);
        $response = @file_get_contents($refreshUrl, false, $ctx);
        if ($response === false) {
            $err = error_get_last();
            $log('file_get_contents error: ' . ($err['message'] ?? 'unknown'));
        }
    }

    if ($response === false) {
        return ['ok' => false, 'message' => 'No response from refresh endpoint', 'payload' => null];
    }

    $payload = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $log('JSON parse error: ' . json_last_error_msg());
        return ['ok' => false, 'message' => 'Invalid JSON from refresh endpoint', 'payload' => ['raw' => substr($response, 0, 512)]];
    }

    if (!empty($payload['access_token'])) {
        $newToken = trim($payload['access_token']);
    } elseif (!empty($payload['expires_in'])) {
        // treat as success; keep current token
        $newToken = $current;
    } else {
        $log('Unexpected refresh payload: ' . json_encode($payload));
        return ['ok' => false, 'message' => 'Unexpected refresh response', 'payload' => $payload];
    }

    $tmp = $tokenFile . '.' . uniqid('tmp', true);
    if (@file_put_contents($tmp, $newToken, LOCK_EX) === false) {
        $log("Failed to write temp token file $tmp");
        @unlink($tmp);
        return ['ok' => false, 'message' => 'Failed to write temp token file', 'payload' => null];
    }
    if (!@rename($tmp, $tokenFile)) {
        $log('Failed to rename temp to token file');
        @unlink($tmp);
        return ['ok' => false, 'message' => 'Failed to replace token file', 'payload' => null];
    }
    @chmod($tokenFile, 0640);
    @file_put_contents($tokenFile . '.lastrefresh', (string) time(), LOCK_EX);
    $log('SUCCESS: refreshed token; http_code=' . $httpCode . '; payload=' . json_encode($payload));
    return ['ok' => true, 'message' => 'Token refreshed', 'payload' => $payload];
}
