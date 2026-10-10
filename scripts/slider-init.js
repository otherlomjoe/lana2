(function(){
  // Camera slider init and slide loader (moved out of index.html)
  function startCamera() {
    var $wrap = window.jQuery ? window.jQuery('#camera_wrap') : null;
    if(!$wrap || !$wrap.camera) return;
    $wrap.camera({ fx: 'simpleFade, mosaicSpiralReverse', time: 2000, loader: 'none', playPause: false, navigation: true, height: '38%', pagination: true });
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Expose startCamera for legacy calls
    window.startCamera = startCamera;

    fetch('/gallery-api.php?action=list-slider-images', { headers: { Accept: 'application/json' } })
      .then(function (response) { if (!response.ok) throw new Error('Slider request failed'); return response.json(); })
      .then(function (payload) {
        var wrap = document.getElementById('camera_wrap');
        if (!wrap || !payload.success || !payload.slides.length) return;
        payload.slides.forEach(function (slide) {
          var slideDiv = document.createElement('div');
          slideDiv.setAttribute('data-src', slide.image);
          if (slide.linkUrl) slideDiv.setAttribute('data-link', slide.linkUrl);
          if (slide.title) {
            var caption = document.createElement('div');
            caption.className = 'camera_caption fadeFromBottom cap1';
            caption.textContent = slide.title;
            slideDiv.appendChild(caption);
          }
          wrap.appendChild(slideDiv);
        });
        startCamera();
      })
      .catch(function () {
        var wrapper = document.querySelector('.camera_full_width');
        if (wrapper) wrapper.hidden = true;
      });
  });
})();
