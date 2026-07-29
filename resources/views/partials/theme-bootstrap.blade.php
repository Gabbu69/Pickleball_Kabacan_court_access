<script>
    (() => {
        const storageKey = 'kpp-theme';
        const root = document.documentElement;
        let transitionTimer;

        const normalizeTheme = (theme) => theme === 'dark' ? 'dark' : 'light';

        const storedTheme = () => {
            try {
                return normalizeTheme(window.localStorage.getItem(storageKey));
            } catch {
                return 'light';
            }
        };

        const syncControls = () => {
            const theme = normalizeTheme(root.dataset.theme);
            const isDark = theme === 'dark';
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

            document.querySelectorAll('[data-theme-toggle]').forEach((control) => {
                control.classList.toggle('is-light', !isDark);
                control.setAttribute('aria-label', label);
                control.setAttribute('aria-checked', isDark ? 'true' : 'false');
                control.setAttribute('title', label);
            });
        };

        const applyTheme = (theme, { persist = false, animate = false } = {}) => {
            const nextTheme = normalizeTheme(theme);

            if (animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                root.classList.add('is-theme-switching');
                window.clearTimeout(transitionTimer);
                transitionTimer = window.setTimeout(() => {
                    root.classList.remove('is-theme-switching');
                }, 520);
            }

            root.dataset.theme = nextTheme;
            document.querySelector('meta[name="theme-color"]')?.setAttribute(
                'content',
                nextTheme === 'dark' ? '#06141d' : '#fffdf8',
            );

            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, nextTheme);
                } catch {
                    // The theme still works when private browsing blocks storage.
                }
            }

            syncControls();
            document.dispatchEvent(new CustomEvent('kpp:theme-change', {
                detail: { theme: nextTheme },
            }));

            return nextTheme;
        };

        window.KabacanTheme = {
            get: () => normalizeTheme(root.dataset.theme),
            set: (theme) => applyTheme(theme, { persist: true, animate: true }),
            toggle: () => applyTheme(
                root.dataset.theme === 'dark' ? 'light' : 'dark',
                { persist: true, animate: true },
            ),
        };

        applyTheme(storedTheme());

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const control = event.target.closest('[data-theme-toggle]');

            if (!control) {
                return;
            }

            event.preventDefault();
            window.KabacanTheme.toggle();
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', syncControls, { once: true });
        } else {
            syncControls();
        }
    })();
</script>
