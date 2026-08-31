<!-- SB-Tech — theme boot (inline, blocking, prevents flash of default theme) -->
<script>
(function () {
    try {
        var mode = localStorage.getItem('app_color_mode') || 'light';
        var accent = localStorage.getItem('app_accent') || 'blue';
        document.documentElement.setAttribute('data-mode', mode);
        document.documentElement.setAttribute('data-accent', accent);
    } catch (e) {
        document.documentElement.setAttribute('data-mode', 'light');
        document.documentElement.setAttribute('data-accent', 'blue');
    }
})();
</script>
