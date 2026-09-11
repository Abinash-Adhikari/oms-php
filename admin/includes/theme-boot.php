<!-- Theme boot (inline, blocking, prevents flash of default theme).
     Writes BOTH theme systems:
       data-theme/data-accent  → smart-school cms CSS (cms-theme.css, custom.css)
       data-mode/data-accent   → legacy SB-Tech tokens (theme-variables.css)
     Derived from the smart-school cmsTheme* localStorage keys. -->
<script>
(function() {
  try {
    var m = localStorage.getItem('cmsThemeMode') || 'light';
    var a = localStorage.getItem('cmsThemeAccent') || 'emerald';
    var mode = m === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', mode);
    document.documentElement.setAttribute('data-accent', a);
    document.documentElement.setAttribute('data-mode', mode);
    document.documentElement.setAttribute('data-accent', a);
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.setAttribute('data-mode', 'light');
    document.documentElement.setAttribute('data-accent', 'emerald');
  }
})();
</script>