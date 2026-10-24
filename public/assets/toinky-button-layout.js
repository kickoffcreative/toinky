/* Text Center "Force Same Width" buttons: size every button in the group to the
   widest one, so the group looks uniform. Skips groups that would overflow. */
(function () {
  function equalize(container) {
    var btns = container.querySelectorAll('.btn, .public-action');
    if (!btns.length) return;
    btns.forEach(function (b) { b.style.width = ''; });
    var widest = 0;
    btns.forEach(function (b) { widest = Math.max(widest, b.offsetWidth); });
    if (widest > 0 && widest <= container.clientWidth) {
      btns.forEach(function (b) { b.style.width = widest + 'px'; });
    }
  }
  function run() {
    document.querySelectorAll('[data-buttons-same-width="1"]').forEach(equalize);
  }
  window.addEventListener('resize', run);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(run);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
