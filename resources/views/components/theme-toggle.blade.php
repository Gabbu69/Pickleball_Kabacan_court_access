<button
    type="button"
    {{ $attributes->class(['theme-toggle']) }}
    :class="{ 'is-light': theme === 'light' }"
    @click="toggleTheme()"
    aria-label="Switch to dark mode"
    aria-pressed="false"
    :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
    :aria-pressed="(theme === 'dark').toString()"
    :title="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
>
    <span class="theme-toggle-icon theme-toggle-sun" aria-hidden="true"></span>
    <span class="theme-toggle-icon theme-toggle-moon" aria-hidden="true"></span>
    <span class="theme-toggle-knob" aria-hidden="true">
        <img class="theme-toggle-ball" src="{{ asset('images/hero/pickleplay-ball-real-v2.webp') }}" alt="">
    </span>
</button>
