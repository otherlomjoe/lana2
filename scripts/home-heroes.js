(function(){
  // Home heroes rendering moved out of index.html for better maintainability and CSP
  document.addEventListener('DOMContentLoaded', function(){
    fetch('/gallery-api.php?action=list-home-heroes',{headers:{Accept:'application/json'}})
    .then(function(res){ return res.text(); })
    .then(function(text){
      try{ var payload = JSON.parse(text); } catch(e){ console.error('Invalid heroes JSON', e); return; }
      if(!payload || !payload.success || !Array.isArray(payload.heroes)) return;
      var container = document.getElementById('home-heroes-container');
      // clear any existing rendered heroes to avoid duplicates
      container.innerHTML = '';
      payload.heroes.forEach(function(hero){
        if(!hero.visible) return;
        var style = hero.visualStyle || hero.visual_style || 'info';
        var wrap = document.createElement('div');
        wrap.className = 'home-hero alert alert-' + style + ' home-hero-panel';

        if(hero.image_url){
          var imgWrap = document.createElement('div');
          imgWrap.className = 'hero-image';
          var img = document.createElement('img');
          img.src = hero.image_url;
          img.alt = hero.image_alt || hero.title || '';
          img.loading = 'lazy';
          imgWrap.appendChild(img);
          wrap.appendChild(imgWrap);
        }

        var bodyWrap = document.createElement('div');
        bodyWrap.className = 'hero-body';
        if (hero.title) {
          var h = document.createElement('h2');
          // use textContent to avoid injecting raw HTML into the title
          var titleDiv = document.createElement('div');
          titleDiv.setAttribute('align', 'center');
          titleDiv.textContent = hero.title;
          h.appendChild(titleDiv);
          bodyWrap.appendChild(h);
        }
        if (hero.body || hero.bodyHtml) {
          var b = document.createElement('div');
          // render markdown-like body the same way admin preview does
          function escapeHtml(value) { return value.replace(/[&<>\"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]); }); }
          function render(text) {
            var escaped = escapeHtml((text||'').trim()).replace(/\r\n|\r/g,'\n')
              .replace(/\*\*(.+?)\*\*/g, '\x3Cstrong\x3E$1\x3C/strong\x3E')
              .replace(/\*(.+?)\*/g, '\x3Cem\x3E$1\x3C/em\x3E')
              .replace(/\[([^\]]+)\]\((https:\/\/[^)]+)\)/g, '\x3Ca href="$2" target="_blank" rel="noopener noreferrer"\x3E$1\x3C/a\x3E');
            return escaped.split(/\n\s*\n/).filter(Boolean).map(function(block){
              var lines = block.split('\n').map(function(line){ return line.trim(); }).filter(Boolean);
              if (lines.every(function(line){ return /^-\s+/.test(line); })) {
                return '\x3Cul\x3E' + lines.map(function(line){ return '\x3Cli\x3E' + line.replace(/^-\s+/, '') + '\x3C/li\x3E'; }).join('') + '\x3C/ul\x3E';
              }
              return lines.map(function(line){
                var match = line.match(/^(#{1,6})\s+(.+)$/);
                if (match) return '\x3Ch' + match[1].length + '\x3E' + match[2] + '\x3C/h' + match[1].length + '\x3E';
                return '\x3Cp\x3E' + line + '\x3C/p\x3E';
              }).join('');
            }).join('');
          }
          b.className = 'home-hero-body';
          if (hero.body) {
            b.innerHTML = render(hero.body);
          } else {
            // bodyHtml is server-rendered; sanitize on the client as defense-in-depth
            var raw = hero.bodyHtml || '';
            if (window.DOMPurify && typeof DOMPurify.sanitize === 'function') {
              b.innerHTML = DOMPurify.sanitize(raw);
            } else {
              // fallback: insert as text (will not render HTML tags)
              b.textContent = raw;
            }
          }
          bodyWrap.appendChild(b);
        }

        wrap.appendChild(bodyWrap);
        container.appendChild(wrap);
      });
    }).catch(function(err){ console.error('Failed to load home heroes', err); });
  });
})();
