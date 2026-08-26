(() => {
  const stage = document.querySelector('[data-parallax-stage]');
  if (!stage || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const layers = [...stage.querySelectorAll('[data-depth]')];
  let pointerX = 0;
  let pointerY = 0;
  let ticking = false;

  const paint = () => {
    const hero = stage.closest('.toinky-cinematic');
    const rect = hero.getBoundingClientRect();
    const progress = Math.max(-.18, Math.min(1.12, -rect.top / Math.max(rect.height, 1)));

    layers.forEach((layer) => {
      const depth = Number(layer.dataset.depth || 0) / 100;
      const x = pointerX * depth * 24;
      const y = progress * depth * 150 + pointerY * depth * 15;
      layer.style.setProperty('--px', `${x.toFixed(2)}px`);
      layer.style.setProperty('--py', `${y.toFixed(2)}px`);
    });

    ticking = false;
  };

  const requestPaint = () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(paint);
    }
  };

  addEventListener('scroll', requestPaint, { passive: true });
  addEventListener('pointermove', (event) => {
    pointerX = event.clientX / innerWidth - .5;
    pointerY = event.clientY / innerHeight - .5;
    requestPaint();
  }, { passive: true });
  addEventListener('resize', requestPaint, { passive: true });
  requestPaint();
})();
