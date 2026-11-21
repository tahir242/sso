@props(['default' => 'system'])

{{-- Applies the saved light/dark preference before first paint (no flash). Pages may set a default. --}}
<script>
    (() => {
        let theme = @js($default);
        try { theme = localStorage.getItem('theme') || theme; } catch (e) {}
        const dark = theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
