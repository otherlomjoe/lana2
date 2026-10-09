(function () {
  'use strict';

  function escapeHtml(value) {
    return (value || '').replace(/[&<>'"]/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":"&#039;"}[c] || c);
    });
  }

  function safeLinkify(text) {
    return text.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g, function(_, label, url){
      return '<a href="'+ url.replace(/\"/g,'') +'" target="_blank" rel="noopener noreferrer">'+ escapeHtml(label) +'</a>';
    });
  }

  function renderMarkdownLike(text) {
    if (!text) return '';
    var s = escapeHtml(text.trim()).replace(/\r\n|\r/g,'\n');
    s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
    s = safeLinkify(s);
    return s.split(/\n\s*\n/).map(function(p){ return '<p>'+ p.split('\n').map(function(line){ return line.trim(); }).join('<br>') +'</p>'; }).join('');
  }

  function createHeroElement(hero) {
    var wrap = document.createElement('div');
    var style = hero.visualStyle || hero.visual_style || 'info';
    wrap.className = 'home-hero alert alert-' + (style.replace(/[^a-z0-9\-]/ig,'') || 'info') + ' home-hero-panel';

    if (hero.image_url) {
      var imgWrap = document.createElement('div'); imgWrap.className = 'hero-image';
      var img = document.createElement('img');
      img.src = hero.image_url;
      img.alt = hero.image_alt || hero.title || '';
      img.loading = 'lazy';
      img.decoding = 'async';
      imgWrap.appendChild(img);
      wrap.appendChild(imgWrap);
    }

    var bodyWrap = document.createElement('div'); bodyWrap.className = 'hero-body';
    if (hero.title) {
      var h = document.createElement('h2'); h.textContent = hero.title; bodyWrap.appendChild(h);
    }
    if (hero.body || hero.bodyHtml) {
      var b = document.createElement('div'); b.className = 'home-hero-body';
      if (hero.body) {
        b.innerHTML = renderMarkdownLike(hero.body);
      } else if (hero.bodyHtml) {
        // Assume server-sanitized HTML; if not, add DOMPurify and sanitize here before use.
        b.innerHTML = hero.bodyHtml;
      }
      bodyWrap.appendChild(b);
    }

    wrap.appendChild(bodyWrap);
    return wrap;
  }

  function isMobileDevice() {
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('home-heroes-container');
    if (!container) return;

    fetch('/gallery-api.php?action=list-home-heroes', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('Network response not ok: ' + res.status);
        return res.json();
      })
      .then(function (payload) {
        var heroes = Array.isArray(payload && payload.heroes) ? payload.heroes : (Array.isArray(payload) ? payload : []);
        container.innerHTML = '';
        heroes.forEach(function (hero) {
          if (!hero || hero.visible === false) return;
          try {
            var el = createHeroElement(hero);
            container.appendChild(el);
          } catch (e) { console.error('Render hero failed', e); }
        });
      })
      .catch(function (err) {
        console.error('Failed to load home heroes', err);
      });

    if (isMobileDevice()) {
      var fb = document.getElementById('fb-plugin'); if (fb) fb.style.display = 'none';
      var fbMsg = document.getElementById('fb-mobile-message'); if (fbMsg) fbMsg.style.display = 'block';
    }
  });
})();
