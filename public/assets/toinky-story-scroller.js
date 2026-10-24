/* Story cards scroller: prev/next buttons page through the row, four cards at a time.
   Buttons disable themselves when there is nothing to scroll to. */
(function () {
  function update(scroller) {
    var track = scroller.querySelector('.public-story-grid');
    var prev = scroller.querySelector('[data-story-scroll="-1"]');
    var next = scroller.querySelector('[data-story-scroll="1"]');
    if (!track || !prev || !next) return;
    var can = track.scrollWidth > track.clientWidth + 2;
    prev.disabled = !can || track.scrollLeft <= 2;
    next.disabled = !can || track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
  }
  document.querySelectorAll('[data-story-scroller]').forEach(function (scroller) {
    var track = scroller.querySelector('.public-story-grid');
    if (!track) return;
    scroller.querySelectorAll('[data-story-scroll]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        track.scrollBy({ left: parseInt(btn.dataset.storyScroll, 10) * track.clientWidth, behavior: 'smooth' });
      });
    });
    track.addEventListener('scroll', function () { update(scroller); }, { passive: true });
    window.addEventListener('resize', function () { update(scroller); });
    update(scroller);
  });
})();
