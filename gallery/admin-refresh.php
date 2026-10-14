<?php
// gallery/admin-refresh.php
// Admin page and endpoint to refresh the Instagram long-lived token.

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/admin-refresh-core.php';

// configuration: token file placed under gallery/secrets/token.txt (outside webroot ideally)
$tokenDir = __DIR__ . '/secrets';
$tokenFile = $tokenDir . '/token.txt';

if (session_status() === PHP_SESSION_NONE) session_start();

// Serve both the HTML admin page (GET) and the AJAX POST endpoint.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    // require admin auth
    gallery_require_admin();

    // CSRF check: expect gallery_csrf_token in session and in POST body
    $csrf = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['gallery_csrf_token']) || !hash_equals($_SESSION['gallery_csrf_token'], (string) $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Ensure token dir exists
    if (!is_dir($tokenDir)) {
        @mkdir($tokenDir, 0750, true);
    }

    $result = gallery_refresh_instagram_token($tokenFile);

    if (!empty($result['ok'])) {
        // record admin action
        gallery_admin_log('refresh_instagram_token', ['user' => gallery_current_admin(), 'result' => $result]);
        echo json_encode(['success' => true, 'message' => $result['message'], 'payload' => $result['payload'] ?? null]);
    } else {
        gallery_admin_log('refresh_instagram_token_failed', ['user' => gallery_current_admin(), 'message' => $result['message'], 'payload' => $result['payload'] ?? null]);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['message'], 'payload' => $result['payload'] ?? null]);
    }
    exit;
}

// GET: render admin page with a button; gallery_require_admin will redirect to login if unauthenticated
gallery_require_admin();
$csrfToken = $_SESSION['gallery_csrf_token'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin — Refresh Instagram Token</title>
  <link href="/scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/styles/custom.css" rel="stylesheet">
</head>
<body class="admin-friendly">
<?php include __DIR__ . '/admin-nav.php'; ?>
<div class="container">
  <h1>Refresh Instagram Token</h1>
  <p>Use this button to request a token refresh from Meta. This operation is logged and requires admin authentication.</p>
  <div>
    <button id="btn-refresh" class="btn btn-warning">Refresh Token Now</button>
    <span id="status" style="margin-left:12px"></span>
  </div>
  <h3>About this action</h3>
  <p>This button requests a refresh of the Instagram long-lived access token from the Meta Graph API and replaces the server token file with the updated token. The refresh is performed server-side and the token is never returned to the browser.</p>

  <h3>Why this is needed</h3>
  <p>Instagram long-lived tokens are valid for 60 days. Refreshing the token before it expires extends the validity (Meta's refresh endpoint will keep the session active). Running a refresh every ~30–55 days is a safe schedule; this page lets an admin trigger an immediate refresh when required.</p>

  <h3>Manual refresh via Facebook / Meta tools</h3>
  <p>If you prefer to refresh the token manually (for example, to obtain a new short-lived token and exchange it), you can use the Graph API or the Facebook for Developers tools. Typical steps:</p>
  <ol>
    <li>Obtain a short-lived Instagram user access token via the OAuth flow or the Graph API Explorer.</li>
    <li>Exchange the short-lived token for a long-lived token (60 days) using the Graph API:
      <pre>GET https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=APP_SECRET&access_token=SHORT_LIVED_TOKEN</pre>
    </li>
    <li>To refresh an existing long-lived token (extend its expiry), call:
      <pre>GET https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=LONG_LIVED_TOKEN</pre>
      The response will contain either a refreshed access_token or an updated expires_in value. If a new access_token is returned, replace the server token with it.</li>
    <li>After obtaining a fresh long-lived token, update the server token file (example, run on the server):
      <pre>printf '%s\n' "NEW_LONG_LIVED_TOKEN" > <?php echo htmlspecialchars($tokenFile) ?>
chown www-data:www-data <?php echo htmlspecialchars($tokenFile) ?>
chmod 0640 <?php echo htmlspecialchars($tokenFile) ?></pre>
    </li>
  </ol>

  <h3>Security notes</h3>
  <ul>
    <li>Token is stored in <code><?php echo htmlspecialchars($tokenFile) ?></code> on the server. Do not make this file web-accessible; ideally move it outside your document root and set the environment variable <code>INSTAGRAM_TOKEN_FILE</code> if you do.</li>
    <li>Only administrators may trigger a refresh; this page uses the same gallery admin session and CSRF token as other gallery admin pages.</li>
    <li>Refreshing via this button logs the admin action to the admin action log for audit.</li>
  </ul>
</div>
<script>
(function(){
  var btn = document.getElementById('btn-refresh');
  var status = document.getElementById('status');
  btn.addEventListener('click', function(){
    if (!confirm('Refresh Instagram token now?')) return;
    status.textContent = 'Refreshing...';
    var fd = new FormData();
    fd.append('csrf', '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES) ?>');
    fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(json){
        if (json && json.success) {
          status.textContent = 'Success';
        } else {
          status.textContent = 'Error: ' + (json && json.message ? json.message : 'Unknown');
        }
      }).catch(function(err){
        status.textContent = 'Network error';
        console.error(err);
      });
  });
})();
</script>
</body>
</html>
