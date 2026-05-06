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

/* ------------------------------------------------------------------ */
/* Updates panel (v1.1.0)                                             */
/* ------------------------------------------------------------------ */
(function(){
    var BASE = '/scp/ajax.php/calendar-week-start';

    function findCsrf() {
        var m = document.querySelector('meta[name="csrf_token"]');
        return m ? m.getAttribute('content') : '';
    }

    function setStatus($panel, klass, html) {
        $panel
            .removeClass('cws-uptodate cws-available cws-error')
            .addClass(klass)
            .html(html);
    }

    function escapeHtml(s) {
        return jQuery('<i>').text(s == null ? '' : String(s)).html();
    }

    function initUpdatesPanel() {
        if (!window.jQuery) return false;
        var $panel = jQuery('#cws-updates-panel');
        if (!$panel.length) return false;

        jQuery.ajax({
            url:      BASE + '/check-update',
            method:   'GET',
            dataType: 'json',
            cache:    false
        })
        .done(function(d){
            if (d && d.error) {
                setStatus($panel, 'cws-error',
                    '<strong>Update check failed:</strong> ' + escapeHtml(d.error));
                return;
            }
            if (!d || !d.available) {
                setStatus($panel, 'cws-uptodate',
                    '✓ Up to date (v' + escapeHtml(d && d.current) + ')');
                return;
            }
            var html = '<strong>v' + escapeHtml(d.latest)
                     + '</strong> available (you have v'
                     + escapeHtml(d.current) + ').'
                     + '<br>'
                     + '<button type="button" id="cws-apply-btn" class="green button">Apply update</button>';
            if (d.release_url) {
                html += '<a class="cws-release-link" target="_blank" rel="noopener" href="'
                      + escapeHtml(d.release_url)
                      + '">View release notes ↗</a>';
            }
            setStatus($panel, 'cws-available', html);
            $panel.find('#cws-apply-btn').on('click', function(){
                applyUpdate($panel, d.latest);
            });
        })
        .fail(function(xhr){
            setStatus($panel, 'cws-error',
                '<strong>Update check failed:</strong> HTTP ' + xhr.status);
        });

        return true;
    }

    function applyUpdate($panel, latest) {
        if (!confirm('Apply v' + latest + '?\n\nFiles will be replaced. A backup will be saved into the plugin\'s backups/ directory before changes are made.')) return;
        $panel
            .removeClass('cws-uptodate cws-available cws-error')
            .html('<span class="cws-spinner"></span>Applying update…');

        jQuery.ajax({
            url:    BASE + '/apply-update',
            method: 'POST',
            headers: { 'X-CSRFToken': findCsrf() },
            dataType: 'json',
            cache:    false
        })
        .done(function(d){
            if (d && d.success) {
                setStatus($panel, 'cws-uptodate',
                    '✓ Updated to v' + escapeHtml(d.version) + '. '
                    + 'Backup: <code>' + escapeHtml(d.backup_file) + '</code>. '
                    + 'Reloading…');
                setTimeout(function(){ window.location.reload(); }, 2000);
            } else {
                setStatus($panel, 'cws-error',
                    '<strong>Update failed:</strong> '
                    + escapeHtml((d && d.error) || 'Unknown error'));
            }
        })
        .fail(function(xhr){
            setStatus($panel, 'cws-error',
                '<strong>Update failed:</strong> HTTP ' + xhr.status);
        });
    }

    if (!initUpdatesPanel()) {
        var tries = 0;
        var t = setInterval(function(){
            if (initUpdatesPanel() || ++tries > 30) clearInterval(t);
        }, 100);
    }
})();
