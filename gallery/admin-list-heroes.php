<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$heroes = gallery_list_home_heroes(true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Home Heroes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
  <link href="../styles/admin.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Home heroes</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <p><a class="btn btn-primary" href="/gallery/admin-hero-edit.php">Add home hero</a></p>
    <p>Visible heroes appear on the homepage in the order shown below. Uncheck visibility to keep a hero as a draft.</p>
    <table class="table table-striped">
      <thead><tr><th>Order</th><th>Title</th><th>Style</th><th>Visible</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($heroes as $hero): ?>
        <tr data-hero-id="<?= (int) $hero['id'] ?>" data-hero-visible="<?= $hero['visible'] ? '1' : '0' ?>">
          <td><?= (int) $hero['displayOrder'] ?></td>
          <td><?= htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($hero['visualStyle'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="hero-visible-label"><?= $hero['visible'] ? 'Yes' : 'Draft' ?></td>
          <td><?= htmlspecialchars((string) ($hero['updatedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="admin-friendly-actions">
            <button type="button" class="btn btn-link hero-shift" data-direction="-1" aria-label="Move hero up">Up</button>
            <button type="button" class="btn btn-link hero-shift" data-direction="1" aria-label="Move hero down">Down</button>
            <a href="/gallery/admin-hero-edit.php?id=<?= (int) $hero['id'] ?>">Edit</a>
            <button type="button" class="btn btn-link hero-toggle"><?= $hero['visible'] ? 'Hide' : 'Publish' ?></button>
            <form method="post" action="/gallery/hero-action.php" style="display:inline" onsubmit="return confirm('Permanently delete this hero and its image? This cannot be undone.')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="id" value="<?= (int) $hero['id'] ?>">
              <input type="hidden" name="action" value="hard-delete">
              <button type="submit" class="btn-link text-error">Hard delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$heroes): ?><tr><td colspan="6">No home heroes have been created.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<script>
(function(){
  const csrf = <?= json_encode($csrf) ?>;
  function postForm(url, data){
    const params = new URLSearchParams();
    for (const k in data) params.append(k, data[k]);
    params.append('csrf', csrf);
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

  document.querySelectorAll('.hero-shift').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-hero-id');
      const direction = ev.target.getAttribute('data-direction') || '-1';
      if (!id) return;
      postForm('/gallery/hero-action.php', { id: id, action: 'shift', direction: direction }).then(json => {
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
          showTempMessage(json.message || 'Could not move', false);
        }
      }).catch(err => showTempMessage('Error: ' + err, false));
    });
  });

  document.querySelectorAll('.hero-toggle').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-hero-id');
      if (!id) return;
      if (!confirm('Change visibility for this hero?')) return;
      postForm('/gallery/hero-action.php', { id: id, action: 'visibility', visible: row.getAttribute('data-hero-visible') === '1' ? '0' : '1' }).then(json => {
        if (json && json.success) {
          const visible = row.getAttribute('data-hero-visible') === '1' ? '0' : '1';
          row.setAttribute('data-hero-visible', visible);
          const label = row.querySelector('.hero-visible-label');
          if (label) label.textContent = visible === '1' ? 'Yes' : 'Draft';
          row.classList.add('ex-row-highlight');
          setTimeout(() => row.classList.remove('ex-row-highlight'), 900);
          btn.textContent = visible === '1' ? 'Hide' : 'Publish';
          showTempMessage(json.message || 'Updated');
        } else {
          showTempMessage(json.message || 'Could not update', false);
        }
      }).catch(err => showTempMessage('Error: ' + err, false));
    });
  });
})();
</script>
</body>
</html>
