<footer class="main-footer">
    <strong>Modifiad by 2025 <a href="https://saweria.co/septianirawan25">Mesjid Rahmatullah</a>.</strong>
    
    <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> 1.0
    </div>
</footer>


<script>
$(document).ready(function() {
    // Theme switching functionality (jika ada)
    $('.theme-option').click(function(e) {
        e.preventDefault();
        const theme = $(this).data('theme');
        $('body').removeClass('dark-mode light-mode').addClass(theme + '-mode');
        $('.theme-indicator').text(theme.charAt(0).toUpperCase() + theme.slice(1));
        localStorage.setItem('theme', theme);
    });
    
    // Color scheme switching functionality (jika ada)
    $('.color-option').click(function(e) {
        e.preventDefault();
        const color = $(this).data('color');
        $('body').removeClass(function(index, className) {
            return (className.match(/(^|\s)scheme-\S+/g) || []).join(' ');
        }).addClass('scheme-' + color);
        localStorage.setItem('colorScheme', color);
    });
    
    // Load saved preferences (jika ada)
    const savedTheme = localStorage.getItem('theme') || 'light';
    const savedColor = localStorage.getItem('colorScheme') || 'blue';
    $('body').addClass(savedTheme + '-mode scheme-' + savedColor);
    $('.theme-indicator').text(savedTheme.charAt(0).toUpperCase() + savedTheme.slice(1));

    // Enable control sidebar (pastikan elemen control-sidebar ada)
    $('[data-widget="control-sidebar"]').click(function() {
        $('.control-sidebar').toggleClass('control-sidebar-open');
    });
    
    // Enable push menu (pastikan elemen pushmenu ada)
    $('[data-widget="pushmenu"]').click(function() {
        $("body").toggleClass('sidebar-collapse');
    });
});
</script>