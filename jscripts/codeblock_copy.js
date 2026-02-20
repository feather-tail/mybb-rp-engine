(() => {
  const cfg = {
    copied: 'Скопировано!',
    restoreMs: 1200
  };

  const getCodeText = (codeEl) =>
    (codeEl.textContent || '')
      .replace(/\r\n?/g, '\n')
      .replace(/^\n+/, '')
      .replace(/\n+$/, '');

  const copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }
    const ta = Object.assign(document.createElement('textarea'), { value: text });
    ta.setAttribute('readonly', '');
    Object.assign(ta.style, { position: 'fixed', left: '-9999px', top: '0' });
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
  };

  const flash = (titleEl) => {
    titleEl.dataset.orig ??= titleEl.textContent;
    titleEl.textContent = cfg.copied;
    titleEl.classList.add('cbcopy-copied');

    clearTimeout(Number(titleEl.dataset.timer));
    titleEl.dataset.timer = String(setTimeout(() => {
      titleEl.textContent = titleEl.dataset.orig;
      titleEl.classList.remove('cbcopy-copied');
    }, cfg.restoreMs));
  };

  document.addEventListener('click', async (e) => {
    const titleEl = e.target.closest?.('.codeblock > .title');
    if (!titleEl) return;

    const box = titleEl.parentElement;
    const codeEl = box?.querySelector('.body code, code');
    if (!codeEl) return;

    const text = getCodeText(codeEl);
    if (!text) return;

    try {
      await copyText(text);
      flash(titleEl);
    } catch {}
  });
})();
