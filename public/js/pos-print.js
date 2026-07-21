(() => {
    if (document.body?.dataset.posPrint !== '1') return;

    window.addEventListener('load', () => window.print(), { once: true });
})();
