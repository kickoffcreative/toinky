/* Delegated, authored actions replace inline HTML event attributes. */
(function () {
  'use strict';
  if (window.toinkyActionsBound) return;
  window.toinkyActionsBound = true;
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-window-close],[data-brand-color-remove],[data-brand-color-add],[data-media-confirm-open],[data-media-confirm-backdrop],[data-media-confirm-close]');
    if (!button) return;
    if (button.hasAttribute('data-media-confirm-open')) button.nextElementSibling.hidden = false;
    if (button.hasAttribute('data-media-confirm-backdrop') && event.target === button) button.hidden = true;
    if (button.hasAttribute('data-media-confirm-close')) button.closest('.toinky-confirm-overlay').hidden = true;
    if (button.hasAttribute('data-window-close')) window.close();
    if (button.hasAttribute('data-brand-color-remove')) button.closest('.brand-color-row')?.remove();
    if (button.hasAttribute('data-brand-color-add')) window.addBrandColor?.();
  });
  document.addEventListener('change', function (event) {
    const select = event.target.closest('[data-pb-select-sync]');
    if (select) window.toinkyPBSelectSync?.(select);
  });
})();
