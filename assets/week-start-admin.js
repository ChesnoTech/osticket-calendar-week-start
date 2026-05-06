/* Calendar Week Start v1.0.0 — admin live-preview datepicker. */
(function(){
    /**
     * osTicket DynamicForm renders ChoiceField with a hashed name (e.g.
     * `58910629124cdd[]`) so we cannot select it by `name="first_day"`.
     * Heuristic: pick the <select> whose options exactly match values 0..6.
     */
    function findFirstDaySelect() {
        var sels = document.querySelectorAll('select');
        for (var i = 0; i < sels.length; i++) {
            var s = sels[i];
            if (s.options.length !== 7) continue;
            var ok = true;
            for (var j = 0; j < 7; j++) {
                if (String(s.options[j].value) !== String(j)) { ok = false; break; }
            }
            if (ok) return s;
        }
        return null;
    }

    function init() {
        if (!window.jQuery || !jQuery.datepicker) return false;
        var selEl = findFirstDaySelect();
        var $prev = jQuery('#cws-preview');
        if (!selEl || !$prev.length) return false;
        var $sel = jQuery(selEl);

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
