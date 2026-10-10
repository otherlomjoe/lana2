<?php
require __DIR__ . '/gallery-lib.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$pdo = gallery_init_db();
$csrf = gallery_set_csrf_token();
$exhibitions = gallery_list_exhibitions($pdo, true);
$message = $_SESSION['gallery_admin_message'] ?? '';
unset($_SESSION['gallery_admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exhibitions</title>
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Exhibitions</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a href="/gallery/admin-exhibition-edit.php">Add exhibition</a></p>
    <p><small>Tip: When selecting images for an exhibition, hold Ctrl (Windows/Linux) or ⌘ Command (Mac) and click to select multiple images. If you prefer a checkbox-style multi-select in the images list, say so and it can be changed.</small></p>
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Title</th><th>Location</th><th>Dates</th><th>Images</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($exhibitions as $exhibition): ?>
          <tr data-ex-id="<?= (int) $exhibition['id'] ?>" data-ex-active="<?= !empty($exhibition['active']) ? '1' : '0' ?>">
            <td class="ex-id"><?= (int) $exhibition['id'] ?></td>
            <td class="ex-title"><?= htmlspecialchars((string) $exhibition['title']) ?></td>
            <td class="ex-location"><?= htmlspecialchars((string) $exhibition['location']) ?></td>
            <td class="ex-dates"><?= htmlspecialchars((string) ($exhibition['startDate'] ?? '')) ?> - <?= htmlspecialchars((string) ($exhibition['endDate'] ?? '')) ?></td>
            <td class="ex-count"><?= (int) ($exhibition['imageCount'] ?? 0) ?></td>
            <td class="ex-status"><span class="badge <?= !empty($exhibition['active']) ? 'badge-success' : 'badge-secondary' ?>"><?= !empty($exhibition['active']) ? 'Visible' : 'Hidden' ?></span></td>
            <td class="ex-actions">
              <a class="btn btn-link" href="/gallery/admin-exhibition-edit.php?id=<?= (int) $exhibition['id'] ?>">Edit</a>
              | <a class="btn btn-link text-error" href="/gallery/exhibition-delete.php?id=<?= (int) $exhibition['id'] ?>" onclick="return confirm('Delete this exhibition?')">Delete</a>
              | <button type="button" class="btn btn-link ex-shift" data-direction="-1" title="Move up" aria-label="Move up">Up</button>
              <button type="button" class="btn btn-link ex-shift" data-direction="1" title="Move down" aria-label="Move down">Down</button>
              | <button type="button" class="btn btn-link ex-toggle" title="Toggle visibility" aria-label="Toggle visibility">Toggle</button>
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

  document.querySelectorAll('.ex-toggle').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-ex-id');
      if (!id) return;
      if (!confirm('Change visibility for this exhibition?')) return;
      postForm('/gallery/gallery-api.php?action=exhibition-toggle', { id: id }).then(json => {
        if (json && json.success) {
          const active = json.active ? '1' : '0';
          row.setAttribute('data-ex-active', active);
          const badge = row.querySelector('.ex-status .badge');
          if (badge) {
            badge.textContent = json.active ? 'Visible' : 'Hidden';
            badge.className = 'badge ' + (json.active ? 'badge-success' : 'badge-secondary');
          }
          // brief highlight
          row.classList.add('ex-row-highlight');
          setTimeout(() => row.classList.remove('ex-row-highlight'), 1200);
          showTempMessage(json.message || 'Updated');
        } else {
          showTempMessage(json.error || 'Could not update', false);
        }
      }).catch(err => { showTempMessage('Error: ' + err, false); });
    });
  });

  function animateSwapPair(row, other) {
    if (!row || !other || row.parentNode !== other.parentNode) return;
    const parent = row.parentNode;
    const rectRow = row.getBoundingClientRect();
    const rectOther = other.getBoundingClientRect();

    // perform DOM swap
    if (other === row.previousElementSibling) {
      parent.insertBefore(row, other); // move row before other (up)
    } else if (other === row.nextElementSibling) {
      parent.insertBefore(other, row); // move other before row (so row moves down)
    } else {
      // non-adjacent or unexpected - do straight swap without animation fallback
      parent.insertBefore(row, other);
    }

    // after swap, compute new positions
    const newRectRow = row.getBoundingClientRect();
    const newRectOther = other.getBoundingClientRect();

    // compute deltas
    const deltaRowY = rectRow.top - newRectRow.top;
    const deltaOtherY = rectOther.top - newRectOther.top;

    // apply inverse transforms
    row.style.transition = 'none';
    other.style.transition = 'none';
    row.style.transform = `translateY(${deltaRowY}px)`;
    other.style.transform = `translateY(${deltaOtherY}px)`;

    // force reflow
    row.getBoundingClientRect();

    // animate to natural position
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

  document.querySelectorAll('.ex-shift').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-ex-id');
      const direction = ev.target.getAttribute('data-direction') || '-1';
      if (!id) return;
      postForm('/gallery/gallery-api.php?action=exhibition-shift', { id: id, direction: direction }).then(json => {
        if (json && json.success) {
          if (direction === '-1') {
            const prev = row.previousElementSibling;
            if (prev) animateSwapPair(row, prev);
          } else {
            const next = row.nextElementSibling;
            if (next) animateSwapPair(row, next);
          }
          // flash highlight
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
