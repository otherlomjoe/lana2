<?php
require __DIR__ . '/gallery-lib.php';

session_start();
if (empty($_SESSION['gallery_admin_authenticated']) || $_SESSION['gallery_admin_authenticated'] !== true) {
    header('Location: /gallery/admin-login.php');
    exit;
}

$slides = gallery_list_slider_images(true);
$message = $_SESSION['gallery_admin_message'] ?? '';
$error = $_SESSION['gallery_admin_message_error'] ?? '';
unset($_SESSION['gallery_admin_message'], $_SESSION['gallery_admin_message_error']);
$csrf = gallery_set_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Home Slider</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="../scripts/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../styles/custom.css" rel="stylesheet">
</head>
<body>
  <div class="container admin-friendly">
    <?php require __DIR__ . '/admin-nav.php'; ?>
    <h1>Home slider</h1>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="admin-help-card">
      <p><strong>Recommended image size:</strong> 1600 &times; 600px, landscape, JPG/PNG/WEBP, under 10 MB.</p>
      <p>Active slides appear on the homepage in the order shown below. Turn a slide off to keep it without showing it.</p>
    </div>
    <p><a class="btn btn-primary btn-large" href="/gallery/admin-slider-edit.php">Add slide</a></p>
    <table class="table table-striped admin-friendly-table">
      <thead><tr><th>Image</th><th>Order</th><th>Title</th><th>Link</th><th>Active</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($slides as $slide): ?>
        <tr data-slide-id="<?= (int) $slide['id'] ?>" data-slide-active="<?= $slide['active'] ? '1' : '0' ?>">
          <td><?php if ($slide['image']): ?><img src="<?= htmlspecialchars($slide['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="admin-list-thumbnail"><?php endif; ?></td>
          <td><?= (int) $slide['displayOrder'] ?></td>
          <td><?= htmlspecialchars($slide['title'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $slide['linkUrl'] !== '' ? htmlspecialchars($slide['linkUrl'], ENT_QUOTES, 'UTF-8') : '&mdash;' ?></td>
          <td class="slide-active-label"><?= $slide['active'] ? 'Yes' : 'Off' ?></td>
          <td><?= htmlspecialchars((string) ($slide['updatedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="admin-friendly-actions">
            <button type="button" class="btn btn-link slide-shift" data-direction="-1" aria-label="Move slide up">Up</button>
            <button type="button" class="btn btn-link slide-shift" data-direction="1" aria-label="Move slide down">Down</button>
            <a href="/gallery/admin-slider-edit.php?id=<?= (int) $slide['id'] ?>">Edit</a>
            <button type="button" class="btn btn-link slide-toggle"><?= $slide['active'] ? 'Turn off' : 'Turn on' ?></button>
            <form method="post" action="/gallery/slider-action.php" style="display:inline" onsubmit="return confirm('Permanently delete this slide and its image? This cannot be undone.')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $slide['id'] ?>"><input type="hidden" name="action" value="hard-delete"><button type="submit" class="btn-link text-error">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$slides): ?><tr><td colspan="7">No slider images have been added.</td></tr><?php endif; ?>
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
    }).then(r => r.json());
  }
  function showTempMessage(msg, ok = true){
    const div = document.createElement('div');
    div.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    div.textContent = msg;
    const container = document.querySelector('.container');
    container.insertBefore(div, container.firstChild);
    setTimeout(() => { div.remove(); }, 2500);
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

  document.querySelectorAll('.slide-shift').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-slide-id');
      const direction = ev.target.getAttribute('data-direction') || '-1';
      if (!id) return;
      postForm('/gallery/slider-action.php', { id: id, action: 'shift', direction: direction }).then(json => {
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

  document.querySelectorAll('.slide-toggle').forEach(btn => {
    btn.addEventListener('click', function (ev) {
      const row = ev.target.closest('tr');
      const id = row.getAttribute('data-slide-id');
      if (!id) return;
      postForm('/gallery/slider-action.php', { id: id, action: 'active', active: row.getAttribute('data-slide-active') === '1' ? '0' : '1' }).then(json => {
        if (json && json.success) {
          const active = row.getAttribute('data-slide-active') === '1' ? '0' : '1';
          row.setAttribute('data-slide-active', active);
          const label = row.querySelector('.slide-active-label');
          if (label) label.textContent = active === '1' ? 'Yes' : 'Off';
          row.classList.add('ex-row-highlight');
          setTimeout(() => row.classList.remove('ex-row-highlight'), 900);
          btn.textContent = active === '1' ? 'Turn off' : 'Turn on';
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
