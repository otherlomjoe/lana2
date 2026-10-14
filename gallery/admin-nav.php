<div class="centered_menu">
  <div class="navbar">
    <button type="button" class="btn btn-navbar-highlight btn-large btn-primary" data-toggle="collapse" data-target=".admin-nav-collapse">
      NAVIGATION <span class="icon-chevron-down icon-white"></span>
    </button>
    <div class="admin-nav-collapse nav-collapse collapse">
      <ul class="nav nav-pills ddmenu">
        <li><a href="/gallery/admin-index.php">Admin Home</a></li>
        <li class="public-nav"><a class="public-link" href="/index.html">Home</a></li>
        <li class="public-nav"><a class="public-link" href="/gallery.html">Gallery</a></li>
        <!-- Public gallery was previously removed from admin menu -->
        <li><a href="/gallery/admin-list-sliders.php">Home slider</a></li>
        <li><a href="/gallery/admin-list-heroes.php">Home heroes</a></li>
        <li><a href="/gallery/admin-list-exhibitions.php">Exhibitions</a></li>
        <li><a href="/gallery/admin-list-images.php">Images</a></li>
        <li><a href="/gallery/admin-import-json.php">Import JSON</a></li>
        <li><a href="/gallery/admin-refresh.php">Refresh Instagram Token</a></li>
        <li><a href="/gallery/admin-clear-all.php" onclick="return confirm('Open Clear All page? You will still have to confirm on that page.')">Clear All</a></li>
        <li><a href="/gallery/admin-logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</div>
  <?php
    // show deploy/version badge on all admin pages (reads repo root version.txt)
    $versionFile = dirname(__DIR__) . '/version.txt';
    if (is_readable($versionFile)) {
      $v = trim((string) @file_get_contents($versionFile));
      if ($v !== '') {
        echo '<div id="deploy-version" class="deploy-version">v' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</div>';
      }
    }
  ?>
  <div id="contentInnerSeparator"></div>
  <script src="/scripts/jquery.min.js"></script>
  <script src="/scripts/bootstrap/js/bootstrap.min.js"></script>
  <script>
    // Add a required-field marker to labels on admin pages for inputs/selects/textareas with the required attribute
    (function () {
      try {
        var forms = document.querySelectorAll('.admin-friendly form');
        forms.forEach(function (form) {
          var groups = form.querySelectorAll('.control-group');
          groups.forEach(function (group) {
            var hasRequired = group.querySelector('input[required], select[required], textarea[required]');
            if (hasRequired) {
              var label = group.querySelector('label');
              if (label && !label.querySelector('.required')) {
                var span = document.createElement('span');
                span.className = 'required';
                span.textContent = '*';
                label.appendChild(span);
                // also add aria-required to the form control for clarity
                try {
                  var control = group.querySelector('input, select, textarea');
                  if (control) control.setAttribute('aria-required', 'true');
                } catch (e) {}
              }
            }
          });
        });
      } catch (e) {
        /* ignore errors on older browsers */
      }
    })();

    // Handle dismissing gallery admin warnings. Dismissal is recorded in session via admin-dismiss-warnings.php
    (function () {
      function dismissWarnings(forId, el) {
        try {
          var fd = new FormData();
          fd.append('for', forId);
          fetch('/gallery/admin-dismiss-warnings.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
              if (json && json.success) {
                if (el && el.closest) {
                  var parent = el.closest('.alert');
                  if (parent) parent.style.display = 'none';
                }
              }
            }).catch(function(){});
        } catch (e) {}
      }

      document.addEventListener('click', function (ev) {
        var target = ev.target;
        if (!target) return;
        // support clicking the button or its inner <span>
        var btn = target.closest && target.closest('.gallery-warnings-dismiss');
        if (!btn) return;
        ev.preventDefault();
        var forId = btn.getAttribute('data-for');
        if (forId === null) forId = btn.getAttribute('data-for-id');
        dismissWarnings(forId, btn);
      }, false);
    })();
  </script>

