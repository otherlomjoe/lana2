(function () {
  'use strict';

  // Integration module for admin-side helper behaviors.
  // The original tail referenced helpers (API_URL, fetchJson, getUploadedItems, saveUploadedItems,
  // statusText, formData, AUTH_KEY, showAuthPanel, ensureLoggedInState). This module exposes
  // a safe, self-contained API and defensive implementations so the tail behavior can be
  // invoked by other admin scripts without top-level await or missing symbols.

  const DEFAULT_API = '/gallery/gallery-api.php';
  const API_URL = (window.API_URL && String(window.API_URL)) || DEFAULT_API;
  const AUTH_KEY = (window.AUTH_KEY && String(window.AUTH_KEY)) || 'gallery_admin_authenticated';
  const STORAGE_KEY = 'gallery_uploaded_items';

  async function fetchJson(url, options = {}) {
    const opts = Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, options);
    const res = await fetch(url, opts);
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error('Invalid JSON response: ' + (text ? text.slice(0, 2000) : '(empty)'));
    }
  }

  function getUploadedItems() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch (e) {
      console.warn('gallery-admin: corrupt uploaded items in localStorage, resetting');
      localStorage.removeItem(STORAGE_KEY);
      return [];
    }
  }

  function saveUploadedItems(items) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items || []));
    } catch (e) {
      console.warn('gallery-admin: could not persist uploaded items', e);
    }
  }

  // Handle a successful upload result object (as returned by the API).
  // This mirrors the original tail: merge the returned item into locally stored list,
  // show a status message, and redirect back to gallery.html after a short delay.
  function handleUploadSaved(result) {
    const uploadedItem = result && result.item ? result.item : null;
    if (uploadedItem) {
      const existing = getUploadedItems();
      const next = [uploadedItem, ...existing.filter(item => item.slug !== uploadedItem.slug)];
      saveUploadedItems(next);
    }

    const statusEl = document.getElementById('gallery-admin-status') || document.querySelector('.gallery-admin-status');
    if (statusEl) statusEl.textContent = 'Upload saved. Returning to the gallery…';

    setTimeout(() => {
      window.location.href = '/gallery.html';
    }, 700);
  }

  // Handle an upload error by setting status text if available
  function handleUploadError(err) {
    const statusEl = document.getElementById('gallery-admin-status') || document.querySelector('.gallery-admin-status');
    if (statusEl) statusEl.textContent = (err && err.message) ? err.message : 'There was a problem uploading the images.';
    else console.error(err);
  }

  // Attempt to wire up an upload event if a form and submit flow exists. If your other admin
  // scripts call galleryAdmin.handleUploadSaved(result) then this auto-binding is optional.
  // This module purposely does not attempt to reimplement the full upload flow; instead it
  // provides helpers and a public handler for the result.

  // Expose logout handling: attach to #gallery-admin-logout if present
  (function attachLogout() {
    const logoutBtn = document.getElementById('gallery-admin-logout');
    if (!logoutBtn) return;
    logoutBtn.addEventListener('click', async function (ev) {
      ev.preventDefault();
      try {
        await fetchJson(API_URL + '?action=logout');
      } catch (error) {
        // ignore server logout errors
        console.info('gallery-admin: logout request failed', error);
      }
      try {
        sessionStorage.removeItem(AUTH_KEY);
      } catch (e) { /* ignore */ }
      if (typeof window.showAuthPanel === 'function') {
        try { window.showAuthPanel(); } catch (e) { console.warn('showAuthPanel threw', e); }
      }
      const authStatus = document.getElementById('gallery-auth-status');
      if (authStatus) authStatus.textContent = 'Logged out.';
    });
  })();

  // Basic ensureLoggedInState fallback. If the real admin page defines ensureLoggedInState,
  // prefer that; otherwise provide a minimal implementation that toggles UI elements based on sessionStorage.
  function ensureLoggedInStateFallback() {
    const isAuthed = !!sessionStorage.getItem(AUTH_KEY);
    const shell = document.querySelector('.gallery-admin-shell');
    const login = document.querySelector('.gallery-admin-login');
    if (shell) shell.hidden = !isAuthed;
    if (login) login.hidden = isAuthed;
  }

  if (typeof window.ensureLoggedInState !== 'function') {
    window.ensureLoggedInState = ensureLoggedInStateFallback;
  }

  // Expose public API for other admin scripts to call when an upload completes.
  window.galleryAdmin = Object.assign(window.galleryAdmin || {}, {
    fetchJson,
    getUploadedItems,
    saveUploadedItems,
    handleUploadSaved,
    handleUploadError,
    API_URL,
    AUTH_KEY,
  });

  // If the page defines a global variable 'AUTOBIND_GALLERY_ADMIN_RESULT' with a Promise or function,
  // attempt to bind it. (This is a non-invasive helper for legacy flows.)
  try {
    if (window.AUTOBIND_GALLERY_ADMIN_RESULT && typeof window.AUTOBIND_GALLERY_ADMIN_RESULT.then === 'function') {
      window.AUTOBIND_GALLERY_ADMIN_RESULT.then(handleUploadSaved, handleUploadError);
    }
  } catch (e) { /* ignore */ }

  // Run the ensureLoggedInState hook now to set initial UI state.
  try { window.ensureLoggedInState(); } catch (e) { console.warn('ensureLoggedInState threw', e); }

  // Auto-enhance image-save forms (submit via fetch to gallery-api?action=upload and call handlers)
  (function autoEnhanceUploadForms() {
    try {
      const selector = 'form[action="/gallery/image-save.php"]';
      document.querySelectorAll(selector).forEach(form => {
        // opt-out: set data-ajax="0" on form to skip enhancement
        if (form.dataset.ajax === '0') return;
        if (form.dataset.enhanced === '1') return;
        form.dataset.enhanced = '1';
        form.addEventListener('submit', async function (ev) {
          ev.preventDefault();
          const submitter = ev.submitter;
          const formData = new FormData(form);
          // include clicked submit button value if present
          if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value);
          }

          try {
            // Submit to the form's action URL (preserves original server behavior). If the form's action is not present, fall back to the API URL.
            const targetUrl = form.action && form.action.trim() ? form.action : (API_URL + '?action=upload');
            const res = await fetchJson(targetUrl, { method: 'POST', body: formData });
            if (res && res.success) {
              // server returns { success:true, result: {...} }
              const saved = res.result || res.item || null;
              try { window.galleryAdmin.handleUploadSaved({ item: saved }); } catch (e) { console.warn('handleUploadSaved threw', e); }
            } else {
              const err = new Error(res && res.error ? res.error : 'Upload failed');
              try { window.galleryAdmin.handleUploadError(err); } catch (e) { console.warn('handleUploadError threw', e); }
            }
          } catch (err) {
            try { window.galleryAdmin.handleUploadError(err); } catch (e) { console.error('galleryAdmin error handling failed', e); }
          }
        });
      });
    } catch (e) {
      console.warn('gallery-admin: autoEnhanceUploadForms failed to initialize', e);
    }
  })();

})();
