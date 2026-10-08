;(function ($) {
    "use strict";
    $(document).ready(function() {

        // Dark mode with localStorage persistence and system preference detection
        let prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        let selectedNightTheme = localStorage.getItem('body_dark');

        // Apply theme immediately to prevent FOUC
        function applyThemeOnInit() {
            if (selectedNightTheme === null) {
                // First time visitor - use system preference
                selectedNightTheme = prefersDark ? 'true' : 'false';
                localStorage.setItem('body_dark', selectedNightTheme);
            }
            
            if (selectedNightTheme === "true") {
                applyNight();
                $('.dark_mode_switcher').prop('checked', true);
            } else {
                applyDay();
                $('.dark_mode_switcher').prop('checked', false);
            }
        }
        
        applyThemeOnInit();
        
        function applyNight() {
            $('.dark_mode_switcher').prop('checked', true);
            $("body").addClass("body_dark");
        }

        function applyDay() {
            $('.dark_mode_switcher').prop('checked', false);
            $("body").removeClass("body_dark");
        }

        $('.dark_mode_switcher').change(function () {
            if ($(this).is(':checked')) {
                applyNight();
                localStorage.setItem('body_dark', 'true');
            } else {
                applyDay();
                localStorage.setItem('body_dark', 'false');
            }
        });
        
        // Listen for system preference changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (localStorage.getItem('body_dark') === null) {
                if (e.matches) {
                    applyNight();
                    $('.dark_mode_switcher').prop('checked', true);
                } else {
                    applyDay();
                    $('.dark_mode_switcher').prop('checked', false);
                }
            }
        });
    })
})(jQuery);