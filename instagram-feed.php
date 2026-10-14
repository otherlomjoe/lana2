<?php
// Prefer server-side token file; allow override via environment variable INSTAGRAM_TOKEN_FILE
$tokenFile = getenv('INSTAGRAM_TOKEN_FILE') ?: __DIR__ . '/gallery/secrets/token.txt';
$accessToken = null;
$debug = !empty($_GET['debug']);

if (is_readable($tokenFile)) {
    $fileToken = trim(@file_get_contents($tokenFile));
    if ($fileToken !== '') {
        $accessToken = $fileToken;
        if ($debug) echo "<!-- Using token from $tokenFile -->\n";
    }
}

// Fallback to embedded token for backward compatibility
if (empty($accessToken)) {
    $accessToken = "IGAAQO3E1ETNpBZAFp3R21ubHMzYnhkUGM2bF9hczZALZAmhMYjdBME9tSWF6Q2FtS1o5Ty00YXo5M3RXLXpKVkpHb1VEZAU5iSExReUF5TlpYbEZAacVljN0U4Njl5VEZA1VW9NRWY4T2h1Q1BNUDN1ajJMSWhweEhrbGpqVEROd3R1SQZDZD";
    if ($debug) echo "<!-- Using embedded token fallback -->\n";
}

$endpoint = "https://graph.instagram.com/me/media";
// allow callers to request a different limit via ?limit=n (cap for safety)
$requested_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$limit = max(1, min(12, $requested_limit));

$params = [
    // request additional fields: timestamp, like and comments counts, and children for carousel
    "fields" => "id,caption,media_url,thumbnail_url,permalink,media_type,timestamp,like_count,comments_count,children",
    "access_token" => $accessToken,
    "limit" => $limit
];

// support cursor paging via ?after=CURSOR
if (!empty($_GET['after'])) {
    $params['after'] = $_GET['after'];
}

$url = $endpoint . "?" . http_build_query($params);

// Enable debug output when called with ?debug=1
$debug = !empty($_GET['debug']);

// Simple filesystem cache to reduce API calls and improve resilience
$cache_ttl = 300; // seconds
$cache_dir = __DIR__ . '/gallery/cache';
// use per-request cache files so paginated requests don't collide
$cache_key = md5($url);
$cache_file = $cache_dir . '/instagram.' . $cache_key . '.json';

if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}

function read_cache_file($file) {
    if (!is_file($file)) return false;
    $fp = @fopen($file, 'r');
    if (!$fp) return false;
    if (!flock($fp, LOCK_SH)) { fclose($fp); return false; }
    $contents = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $contents;
}
function write_cache_file($file, $data) {
    $tmp = $file . '.' . uniqid('', true) . '.tmp';
    $fp = @fopen($tmp, 'c');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); @unlink($tmp); return false; }
    ftruncate($fp, 0);
    fwrite($fp, $data);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    @rename($tmp, $file);
    @chmod($file, 0644);
    return true;
}

$response = null;
$use_cached = false;
$stale_response = null;

if (is_file($cache_file)) {
    $age = time() - filemtime($cache_file);
    if ($age <= $cache_ttl) {
        $cached = read_cache_file($cache_file);
        if ($cached !== false) {
            $response = $cached;
            $use_cached = true;
            if ($debug) echo "<!-- using fresh cache (age={$age}s) -->\n";
        }
    } else {
        $stale_response = read_cache_file($cache_file);
        if ($debug && $stale_response !== false) echo "<!-- stale cache available (age={$age}s) -->\n";
    }
}

if (!$use_cached) {
    // fetch from network
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        $curl_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false && $debug) {
            echo "<!-- cURL error: " . htmlspecialchars($curl_err) . " -->\n";
        }
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

    if ($response !== false && $response !== null) {
        // validate json before caching
        $tmp = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            write_cache_file($cache_file, $response);
            if ($debug) echo "<!-- wrote fresh cache -->\n";
        } else {
            if ($debug) echo "<!-- fetch returned non-JSON, not caching -->\n";
        }
    } else {
        // network failed: use stale if available
        if (!empty($stale_response)) {
            $response = $stale_response;
            if ($debug) echo "<!-- using stale cache as fallback -->\n";
        }
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
    $out = '';
    $out .= '<div class="instagram-grid">';
    foreach ($data["data"] as $item) {
        $thumb = $item["thumbnail_url"] ?? $item["media_url"] ?? '';
        $media = $item["media_url"] ?? '';
        $link = $item["permalink"] ?? '#';
        $caption_full = $item["caption"] ?? '';
        $caption = htmlspecialchars(mb_strimwidth($caption_full, 0, 140, '...'));
        $likes = isset($item['like_count']) ? (int)$item['like_count'] : null;
        $comments = isset($item['comments_count']) ? (int)$item['comments_count'] : null;
        $ts = $item['timestamp'] ?? null;
        $type = $item['media_type'] ?? 'IMAGE';

        // data attributes for modal
        $data_attrs = 'data-media="' . htmlspecialchars($media, ENT_QUOTES) . '" '
                    . 'data-type="' . htmlspecialchars($type, ENT_QUOTES) . '" '
                    . 'data-caption="' . htmlspecialchars($caption_full, ENT_QUOTES) . '" '
                    . 'data-likes="' . htmlspecialchars((string)$likes, ENT_QUOTES) . '" '
                    . 'data-comments="' . htmlspecialchars((string)$comments, ENT_QUOTES) . '" '
                    . 'data-permalink="' . htmlspecialchars($link, ENT_QUOTES) . '" ';

        $out .= '<article class="instagram-card" ' . $data_attrs . '>';
        $out .= '<a class="insta-link" href="' . htmlspecialchars($link, ENT_QUOTES) . '" target="_blank" rel="noopener">';
        $out .= '<img class="insta-thumb" src="' . htmlspecialchars($thumb, ENT_QUOTES) . '" alt="' . htmlspecialchars($caption_full, ENT_QUOTES) . '" loading="lazy">';
        $out .= '</a>';
        $out .= '<div class="insta-body">';
        $out .= '<p class="insta-caption">' . $caption . '</p>';
        $out .= '<div class="insta-meta">';
        $out .= '<div class="meta-left">';
        if ($likes !== null) $out .= '<span class="meta-likes">&#10084; ' . $likes . '</span>';
        if ($comments !== null) $out .= '<span class="meta-comments">&#128172; ' . $comments . '</span>';
        $out .= '</div>';
        if ($ts) $out .= '<time datetime="' . htmlspecialchars($ts, ENT_QUOTES) . '">' . htmlspecialchars(date('j M Y', strtotime($ts))) . '</time>';
        $out .= '</div>'; // insta-meta
        $out .= '</div>'; // insta-body
        $out .= '</article>';
    }
    $out .= '</div>';

    // modal container and script (only include on first page to avoid duplicates)
    $modal_html = '<div id="insta-modal" class="insta-modal" aria-hidden="true">'
       . '<div class="modal-inner">'
       . '<button class="close-btn" aria-label="Close">Close</button>'
       . '<div class="media-pane"><div class="media-wrap"></div></div>'
       . '<div class="meta-pane">'
       . '<h4>Post</h4>'
       . '<p class="full-caption"></p>'
       . '<div class="meta-row"><span class="likes"></span> <span class="comments"></span></div>'
       . '<div class="meta-row"><a class="view-instagram" target="_blank" rel="noopener">View on Instagram</a></div>'
       . '</div>'
       . '</div>'
       . '</div>';

    $modal_script = '<script> (function(){ '
      . 'document.addEventListener("click", function(ev){ '
        . 'var card = ev.target.closest && ev.target.closest(".instagram-card"); '
        . 'if (!card) return; ev.preventDefault(); '
        . 'var modal = document.getElementById("insta-modal"); '
        . 'var media = card.getAttribute("data-media"); '
        . 'var type = card.getAttribute("data-type"); '
        . 'var caption = card.getAttribute("data-caption"); '
        . 'var likes = card.getAttribute("data-likes"); '
        . 'var comments = card.getAttribute("data-comments"); '
        . 'var permalink = card.getAttribute("data-permalink"); '
        . 'if(!modal) return; '
        . 'var wrap = modal.querySelector(".media-wrap"); wrap.innerHTML = ""; '
        . 'if (type === "VIDEO") { var v = document.createElement("video"); v.src = media; v.controls = true; v.style.maxWidth = "100%"; v.style.maxHeight = "80vh"; wrap.appendChild(v);} '
        . 'else { var i = document.createElement("img"); i.src = media; i.alt = caption || "Instagram image"; i.style.maxWidth = "100%"; i.style.maxHeight = "80vh"; wrap.appendChild(i);} '
        . 'modal.querySelector(".full-caption").textContent = caption || ""; '
        . 'modal.querySelector(".likes").textContent = (likes? ("❤ " + likes) : ""); '
        . 'modal.querySelector(".comments").textContent = (comments? ("💬 " + comments) : ""); '
        . 'modal.querySelector(".view-instagram").setAttribute("href", permalink || "#"); '
        . 'modal.classList.add("open"); modal.setAttribute("aria-hidden","false"); '
      . '}, false); '
      . 'document.addEventListener("click", function(ev){ var close = ev.target.closest && ev.target.closest(".close-btn"); var modal = document.getElementById("insta-modal"); if (close && modal) { modal.classList.remove("open"); modal.setAttribute("aria-hidden","true"); } }, false); '
      . 'document.addEventListener("keydown", function(ev){ var modal = document.getElementById("insta-modal"); if (ev.key === "Escape" && modal && modal.classList.contains("open")) { modal.classList.remove("open"); modal.setAttribute("aria-hidden","true"); } }, false); '
    . '})(); </script>';

    // prepare final output — include modal only for first page
    $out = $out;
    if (empty($_GET['after'])) {
        $out .= $modal_html . $modal_script;
    }

    // if json requested, return json with html and paging cursor
    if (!empty($_GET['json'])) {
        $next_cursor = null;
        if (!empty($data['paging']['cursors']['after'])) {
            $next_cursor = $data['paging']['cursors']['after'];
        }
        $result = ['html' => $out, 'next_cursor' => $next_cursor];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        return;
    }

    echo $out;

} else {
    if ($debug) {
        echo '<pre>No data returned: ' . htmlspecialchars($response) . '</pre>';
    }
    echo "<p>Unable to load Instagram feed.</p>";
}
?>
