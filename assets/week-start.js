/* Calendar Week Start v1.0.0 — overrides jQuery UI datepicker firstDay globally. */
(function(){
    var fd = (window.CWS_FIRST_DAY != null) ? parseInt(window.CWS_FIRST_DAY, 10) : 1;
    if (isNaN(fd) || fd < 0 || fd > 6) fd = 1;

    function apply() {
        if (!window.jQuery || !jQuery.datepicker) return false;

        // Override locale defaults — runs after the i18n locale file's setDefaults
        jQuery.datepicker.setDefaults({ firstDay: fd });

        // Monkey-patch $.fn.datepicker so any future init inherits firstDay
        var orig = jQuery.fn.datepicker;
        if (orig && !orig.__cwsPatched) {
            jQuery.fn.datepicker = function(opts) {
                if (typeof opts === 'object' && opts !== null && opts.firstDay == null) {
                    opts.firstDay = fd;
                }
                return orig.apply(this, arguments);
            };
            jQuery.fn.datepicker.__cwsPatched = true;
            // Preserve any static methods attached to the original
            for (var k in orig) {
                if (Object.prototype.hasOwnProperty.call(orig, k))
                    jQuery.fn.datepicker[k] = orig[k];
            }
        }

        // Re-apply to any pickers already constructed
        try {
            jQuery('.hasDatepicker').each(function(){
                jQuery(this).datepicker('option', 'firstDay', fd);
            });
        } catch (e) { /* swallow */ }

        return true;
    }

    if (!apply()) {
        var tries = 0;
        var t = setInterval(function(){
            if (apply() || ++tries > 30) clearInterval(t);
        }, 100);
    }
})();
