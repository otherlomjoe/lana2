<?php
$accessToken = "IGAAQO3E1ETNpBZAFp3R21ubHMzYnhkUGM2bF9hczZALZAmhMYjdBME9tSWF6Q2FtS1o5Ty00YXo5M3RXLXpKVkpHb1VEZAU5iSExReUF5TlpYbEZAacVljN0U4Njl5VEZA1VW9NRWY4T2h1Q1BNUDN1ajJMSWhweEhrbGpqVEROd3R1SQZDZD";

$endpoint = "https://graph.instagram.com/me/media";
$params = [
    "fields" => "id,caption,media_url,permalink,thumbnail_url,media_type",
    "access_token" => $accessToken,
    "limit" => 12
];

$url = $endpoint . "?" . http_build_query($params);

// Enable debug output when called with ?debug=1
$debug = !empty($_GET['debug']);

$response = null;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false && $debug) {
        echo "<!-- cURL error: " . curl_error($ch) . " -->\n";
    }
    curl_close($ch);
} else {
    if ($debug) {
        echo "<!-- curl not available, using file_get_contents fallback -->\n";
    }
    $opts = [
        "http" => [
            "method" => "GET",
            "timeout" => 10,
            "header" => "User-Agent: PHP" . "\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if ($response === false && $debug) {
        $err = error_get_last();
        echo "<!-- file_get_contents error: " . htmlspecialchars($err['message'] ?? 'unknown') . " -->\n";
    }
}

if ($response === false || $response === null) {
    if ($debug) {
        echo "<p>Failed to fetch Instagram API. Response empty.</p>\n";
    } else {
        echo "<p>Unable to load Instagram feed.</p>";
    }
    return;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    if ($debug) {
        echo '<pre>JSON parse error: ' . json_last_error_msg() . "\n" . htmlspecialchars($response) . '</pre>';
    }
    echo "<p>Unable to load Instagram feed.</p>";
    return;
}

if (!empty($data['error'])) {
    if ($debug) {
        echo '<pre>API error: ' . print_r($data['error'], true) . '</pre>';
    }
    echo "<p>Unable to load Instagram feed.</p>";
    return;
}

if (!empty($data["data"])) {
    echo '<div class="instagram-feed-grid">';
    foreach ($data["data"] as $item) {
        $img = $item["media_url"] ?? ($item['thumbnail_url'] ?? '');
        $link = $item["permalink"] ?? '#';
        $caption = htmlspecialchars($item["caption"] ?? "");

        echo '<div class="instagram-item">';
        echo '<a href="' . $link . '" target="_blank">';
        echo '<img src="' . $img . '" alt="' . $caption . '">';
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
} else {
    if ($debug) {
        echo '<pre>No data returned: ' . htmlspecialchars($response) . '</pre>';
    }
    echo "<p>Unable to load Instagram feed.</p>";
}
?>
