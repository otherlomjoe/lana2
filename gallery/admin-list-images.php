<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
if (!$pdo) {
  http_response_code(500);
  exit('Gallery database is unavailable.');
}
$csrf = gallery_set_csrf_token();
$filterExhibition = isset($_GET['exhibition']) ? (int) $_GET['exhibition'] : 0;
if ($filterExhibition > 0) {
  $items = gallery_list_exhibition_images($filterExhibition, $pdo);
} else {
  $items = gallery_list_images($pdo, true);
}
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Images</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Images</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-upload.php">Add image</a></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Thumbnail</th><th>Title</th><th>Medium</th><th>Genre</th><th>Tags</th><th>Active</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr <?php if ($filterExhibition > 0): ?>data-image-id="<?= (int) $item['id'] ?>" data-ex-id="<?= $filterExhibition ?>"<?php endif; ?>>
            <td><?= (int) $item['id'] ?></td>
            <td>
              <?php
                $imagePath = (string) ($item['full_file'] ?? '');
                $imageInfo = false;
                if ($imagePath !== '' && is_file($imagePath)) {
                    $imageInfo = gallery_safe_getimagesize($imagePath);
                }
                $imageDetails = $imageInfo ? ((int) $imageInfo[0] . ' x ' . (int) $imageInfo[1] . ' px; ' . number_format((int) filesize($imagePath) / 1024, 1) . ' KB') : '';
              ?>
              <?php if (!empty($item['thumbnail'])): ?><img class="admin-list-thumbnail" src="<?= htmlspecialchars((string) $item['thumbnail'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['title'] ?? 'Image'), ENT_QUOTES, 'UTF-8') ?>"><br><?php endif; ?>
              <?php if ($imageDetails !== ''): ?><small><?= htmlspecialchars($imageDetails, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) $item['title']) ?></td>
            <td><?= htmlspecialchars((string) $item['medium']) ?></td>
            <td><?= htmlspecialchars((string) $item['genre']) ?></td>
            <td><?= htmlspecialchars(implode(', ', (array) $item['tags'])) ?></td>
            <td><?= !empty($item['active']) ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars((string) $item['status']) ?></td>
            <td>
              <a href="/gallery/work.html#<?= urlencode((string) $item['slug']) ?>" target="_blank">Preview</a> | <a href="/gallery/admin-edit.php?id=<?= (int) $item['id'] ?>">Edit</a>
              <?php if ($item['status'] === 'Deleted'): ?> | <a href="/gallery/image-restore.php?id=<?= (int) $item['id'] ?>">Undelete</a> | <a href="/gallery/image-delete-permanent.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Permanently delete this image and all files? This cannot be undone.')">Delete permanently</a><?php else: ?> | <a href="/gallery/image-delete.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Move this image to deleted items?')">Delete</a><?php endif; ?>
              <?php if ($filterExhibition > 0): ?>
                | <button type="button" class="btn btn-link img-ex-shift" data-direction="-1" title="Move up" aria-label="Move up">▲</button>
                <button type="button" class="btn btn-link img-ex-shift" data-direction="1" title="Move down" aria-label="Move down">▼</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <script>
  (function(){
    const csrf = <?= json_encode($csrf) ?>;
    function postForm(url, data){
      const params = new URLSearchParams();
      for (const k in data) params.append(k, data[k]);
      params.append('csrf_token', csrf);
      params.append('ajax', '1');
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: params.toString()
      }).then(async r => {
        const text = await r.text();
        try {
          return JSON.parse(text);
        } catch (e) {
          throw new Error('Invalid JSON response (status ' + r.status + '):\n' + text.slice(0,2000));
        }
      });
    }
    function showTempMessage(msg, ok = true){
      const container = document.querySelector('.container');
      const wrapper = document.createElement('div');
      wrapper.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
      try {
        if (typeof msg === 'string' && (msg.length > 200 || msg.indexOf('\n') !== -1 || msg.trim().startsWith('<') || msg.includes('<!DOCTYPE') || msg.toLowerCase().includes('<html'))) {
          const details = document.createElement('details');
          const summary = document.createElement('summary');
          const firstLine = msg.split('\n')[0].slice(0,200);
          summary.textContent = firstLine + (msg.length > 200 ? ' … (click to expand)' : ' (click to expand)');
          const pre = document.createElement('pre');
          pre.style.whiteSpace = 'pre-wrap';
          pre.style.maxHeight = '300px';
          pre.style.overflow = 'auto';
          pre.textContent = msg;
          details.appendChild(summary);
          details.appendChild(pre);
          wrapper.appendChild(details);
        } else {
          wrapper.textContent = msg;
        }
      } catch (e) {
        wrapper.textContent = String(msg);
      }
      container.insertBefore(wrapper, container.firstChild);
      setTimeout(() => { wrapper.remove(); }, 7000);
    }
    function animateSwapPair(row, other) {
      if (!row || !other || row.parentNode !== other.parentNode) return;
      const parent = row.parentNode;
      const rectRow = row.getBoundingClientRect();
      const rectOther = other.getBoundingClientRect();

      if (other === row.previousElementSibling) {
        parent.insertBefore(row, other);
      } else if (other === row.nextElementSibling) {
        parent.insertBefore(other, row);
      } else {
        parent.insertBefore(row, other);
      }

      const newRectRow = row.getBoundingClientRect();
      const newRectOther = other.getBoundingClientRect();

      const deltaRowY = rectRow.top - newRectRow.top;
      const deltaOtherY = rectOther.top - newRectOther.top;

      row.style.transition = 'none';
      other.style.transition = 'none';
      row.style.transform = `translateY(${deltaRowY}px)`;
      other.style.transform = `translateY(${deltaOtherY}px)`;

      row.getBoundingClientRect();

      row.style.transition = 'transform .32s cubic-bezier(.2,.8,.2,1)';
      other.style.transition = 'transform .32s cubic-bezier(.2,.8,.2,1)';
      row.style.transform = '';
      other.style.transform = '';

      const cleanup = (e) => {
        row.style.transition = '';
        row.style.transform = '';
        other.style.transition = '';
        other.style.transform = '';
        row.removeEventListener('transitionend', cleanup);
        other.removeEventListener('transitionend', cleanup);
      };
      row.addEventListener('transitionend', cleanup);
      other.addEventListener('transitionend', cleanup);
    }

    document.querySelectorAll('.img-ex-shift').forEach(btn => {
      btn.addEventListener('click', function (ev) {
        const row = ev.target.closest('tr');
        const id = row.getAttribute('data-image-id');
        const ex = row.getAttribute('data-ex-id');
        const direction = ev.target.getAttribute('data-direction') || '-1';
        if (!id || !ex) return;
        postForm('/gallery/image-exhibition-action.php', { id: id, exhibition_id: ex, direction: direction }).then(json => {
          if (json && json.success) {
            if (direction === '-1') {
              const prev = row.previousElementSibling;
              if (prev) animateSwapPair(row, prev);
            } else {
              const next = row.nextElementSibling;
              if (next) animateSwapPair(row, next);
            }
            row.classList.add('ex-row-highlight');
            setTimeout(() => row.classList.remove('ex-row-highlight'), 900);
            showTempMessage(json.message || 'Moved');
          } else {
            showTempMessage(json.error || 'Could not move', false);
          }
        }).catch(err => { showTempMessage('Error: ' + err, false); });
      });
    });
  })();
  </script>
  </body>
  </html>
