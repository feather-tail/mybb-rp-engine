(function () {
  function ready(fn) {
    if (document.readyState === 'loading')
      document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function supportsSmooth() {
    return 'scrollBehavior' in document.documentElement.style;
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  ready(function () {
    var wrap = document.getElementById('qs-wrap');
    if (!wrap) return;

    var cfg = window.QuickScrollConfig || {};
    var enableUp = !!cfg.enableUp;
    var enableDown = !!cfg.enableDown;

    var btnUp = wrap.querySelector('.qs-up');
    var btnDown = wrap.querySelector('.qs-down');

    var ticking = false;

    function pageHeight() {
      return Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight,
        document.body.offsetHeight,
        document.documentElement.offsetHeight,
        document.body.clientHeight,
        document.documentElement.clientHeight,
      );
    }

    function scrollTop() {
      return (
        window.pageYOffset ||
        document.documentElement.scrollTop ||
        document.body.scrollTop ||
        0
      );
    }

    function doScrollTo(targetY) {
      targetY = clamp(targetY, 0, pageHeight());
      if (cfg.smooth && supportsSmooth()) {
        window.scrollTo({ top: targetY, behavior: 'smooth' });
      } else {
        window.scrollTo(0, targetY);
      }
    }

    function isNearBottom() {
      if (!cfg.hideDownNearBottom) return false;
      var gap = Number(cfg.bottomGap || 0);
      return scrollTop() + window.innerHeight >= pageHeight() - gap;
    }

    function updateVisibility() {
      var y = scrollTop();

      if (enableUp && btnUp) {
        var showAfter = Number(cfg.showUpAfter || 0);
        if (y >= showAfter) btnUp.classList.remove('qs-hidden');
        else btnUp.classList.add('qs-hidden');
      }

      if (enableDown && btnDown) {
        if (isNearBottom()) btnDown.classList.add('qs-hidden');
        else btnDown.classList.remove('qs-hidden');
      }
    }

    function onScroll() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        updateVisibility();
        ticking = false;
      });
    }

    if (btnUp) {
      btnUp.addEventListener('click', function (e) {
        e.preventDefault();
        doScrollTo(0);
      });
    }

    if (btnDown) {
      btnDown.addEventListener('click', function (e) {
        e.preventDefault();
        var y = scrollTop();

        if ((cfg.downAction || 'bottom') === 'page') {
          doScrollTo(y + window.innerHeight);
        } else {
          doScrollTo(pageHeight());
        }
      });
    }

    updateVisibility();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
  });
})();
