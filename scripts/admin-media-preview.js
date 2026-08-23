document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-media-input]').forEach(function (input) {
    const preview = document.querySelector('[data-media-preview="' + input.id + '"]');
    if (!preview) return;

    const update = function () {
      preview.innerHTML = '';
      const file = input.files && input.files[0];
      if (!file) return;

      const image = document.createElement('img');
      image.src = URL.createObjectURL(file);
      image.alt = 'Selected ' + (input.dataset.mediaInput || 'image');
      image.style.maxWidth = input.dataset.mediaInput === 'thumbnail' ? '200px' : '320px';
      image.style.maxHeight = input.dataset.mediaInput === 'thumbnail' ? '165px' : '320px';
      image.style.height = 'auto';

      const name = document.createElement('div');
      name.textContent = file.name;

      const clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'btn btn-small';
      clear.textContent = 'Clear selection';
      clear.addEventListener('click', function () {
        input.value = '';
        update();
        input.dispatchEvent(new Event('change'));
      });

      preview.appendChild(image);
      preview.appendChild(name);
      preview.appendChild(clear);
    };

    input.addEventListener('change', update);
  });
});
