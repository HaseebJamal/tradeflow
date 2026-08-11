@php($accountTheme = auth()->user()?->theme_preference)
@php($initialTheme = in_array($accountTheme, ['light', 'dark'], true) ? $accountTheme : null)
<script>
window.TradeFlowThemeConfig = {
    accountTheme: @json($initialTheme),
    preferenceUrl: @json(auth()->check() ? route('theme.preference.update') : null),
    csrfToken: @json(csrf_token()),
};
(function(){
    try {
        var config = window.TradeFlowThemeConfig || {};
        var key = 'profit-point-theme';
        var saved = localStorage.getItem(key);
        var theme = (config.accountTheme === 'light' || config.accountTheme === 'dark')
            ? config.accountTheme
            : ((saved === 'light' || saved === 'dark')
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;
    } catch (e) {
        document.documentElement.dataset.theme = 'light';
    }
})();
</script>
