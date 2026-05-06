/* Calendar Week Start v1.0.0 — admin live-preview datepicker. */
(function(){
    function init() {
        if (!window.jQuery || !jQuery.datepicker) return false;
        var $sel  = jQuery('select[name="first_day"]');
        var $prev = jQuery('#cws-preview');
        if (!$sel.length || !$prev.length) return false;

        function render() {
            var fd = parseInt($sel.val(), 10);
            if (isNaN(fd) || fd < 0 || fd > 6) fd = 1;

            // Destroy existing instance if any
            if ($prev.hasClass('hasDatepicker')) {
                try { $prev.datepicker('destroy'); } catch(e) {}
                $prev.empty();
            }

            $prev.datepicker({
                firstDay: fd,
                inline:   true,
                showOtherMonths: true,
                selectOtherMonths: false,
                changeMonth: false,
                changeYear:  false
            });
        }

        $sel.off('change.cwsPreview').on('change.cwsPreview', render);
        render();
        return true;
    }

    if (!init()) {
        var tries = 0;
        var t = setInterval(function(){
            if (init() || ++tries > 30) clearInterval(t);
        }, 100);
    }
})();
