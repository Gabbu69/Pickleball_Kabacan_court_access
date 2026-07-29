<script>
    (() => {
        const key = 'kpp-theme';
        let theme = 'light';

        try {
            const savedTheme = window.localStorage.getItem(key);
            theme = savedTheme === 'dark' || savedTheme === 'light' ? savedTheme : theme;
        } catch {
            theme = 'light';
        }

        document.documentElement.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute(
            'content',
            theme === 'dark' ? '#06141d' : '#fffdf8',
        );
    })();
</script>
