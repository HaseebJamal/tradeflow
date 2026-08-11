(() => {
    const storageKey = 'profit-point-theme';
    const root = document.documentElement;
    const mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');
    const config = window.TradeFlowThemeConfig || {};
    const accountTheme = config.accountTheme === 'light' || config.accountTheme === 'dark'
        ? config.accountTheme
        : null;

    const storedTheme = () => {
        try {
            const value = localStorage.getItem(storageKey);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (_) {
            return null;
        }
    };

    const resolvedTheme = () => accountTheme || storedTheme() || (mediaQuery?.matches ? 'dark' : 'light');

    const updateControls = (theme) => {
        const dark = theme === 'dark';
        const label = dark ? 'Switch to light mode' : 'Switch to dark mode';

        document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);
            toggle.setAttribute('aria-pressed', String(dark));
            toggle.querySelector('[data-theme-toggle-label]')?.replaceChildren(label);
        });
    };

    const applyTheme = (theme, persist = false) => {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = nextTheme;
        root.style.colorScheme = nextTheme;

        if (persist) {
            try {
                localStorage.setItem(storageKey, nextTheme);
            } catch (_) {
                // A blocked storage area should never prevent theme switching.
            }

            if (config.preferenceUrl && config.csrfToken) {
                fetch(config.preferenceUrl, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ theme: nextTheme }),
                }).catch(() => {});
            }
        }

        updateControls(nextTheme);
        window.dispatchEvent(new CustomEvent('tradeflow:themechange', { detail: { theme: nextTheme } }));
    };

    window.TradeFlowTheme = {
        get: () => root.dataset.theme || resolvedTheme(),
        set: (theme) => applyTheme(theme, true),
        toggle: () => applyTheme((root.dataset.theme || resolvedTheme()) === 'dark' ? 'light' : 'dark', true),
    };

    applyTheme(root.dataset.theme || resolvedTheme());

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-theme-toggle]');
        if (toggle) window.TradeFlowTheme.toggle();
    });

    mediaQuery?.addEventListener?.('change', (event) => {
        if (!storedTheme()) applyTheme(event.matches ? 'dark' : 'light');
    });
})();
