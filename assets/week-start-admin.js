/* Calendar Week Start v1.1.1 — admin live preview + Updates tab. */

/* ------------------------------------------------------------------ */
/* Live preview datepicker (instance Config form)                     */
/* ------------------------------------------------------------------ */
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
/* Updates tab (top-level, plugin-detail page)                        */
/* ------------------------------------------------------------------ */
(function(){
    var BASE = '/scp/ajax.php/calendar-week-start';

    function findCsrf() {
        var m = document.querySelector('meta[name="csrf_token"]');
        return m ? m.getAttribute('content') : '';
    }

    function escapeHtml(s) {
        return jQuery('<i>').text(s == null ? '' : String(s)).html();
    }

    function setStatus($card, klass, html) {
        $card
            .removeClass('cws-uptodate cws-available cws-error')
            .addClass(klass)
            .find('.cws-update-body')
            .html(html);
    }

    function renderCard($container) {
        $container.html(
            '<div class="cws-update-card">' +
                '<div class="cws-update-header">' +
                    '<span class="cws-update-icon">&#x1F504;</span>' +
                    '<h3>Plugin Updates</h3>' +
                '</div>' +
                '<div class="cws-update-body">' +
                    '<span class="cws-spinner"></span>Checking for updates&hellip;' +
                '</div>' +
            '</div>'
        );

        var $card = $container.find('.cws-update-card');

        jQuery.ajax({
            url:      BASE + '/check-update',
            method:   'GET',
            dataType: 'json',
            cache:    false
        })
        .done(function(d){
            if (d && d.error) {
                setStatus($card, 'cws-error',
                    '<strong>Update check failed:</strong> ' + escapeHtml(d.error)
                    + ' <button type="button" class="cws-recheck-btn">Check again</button>');
                return;
            }
            if (!d || !d.available) {
                setStatus($card, 'cws-uptodate',
                    '<p>You are running the latest version <strong>v' + escapeHtml(d && d.current) + '</strong>.</p>'
                    + '<button type="button" class="cws-recheck-btn">Check again</button>');
                return;
            }
            var html = '<p><strong>v' + escapeHtml(d.latest)
                     + '</strong> is available (you have v' + escapeHtml(d.current) + ').</p>'
                     + '<div class="cws-update-actions">'
                     + '<button type="button" id="cws-apply-btn" class="cws-update-btn cws-apply">Apply update</button>';
            if (d.release_url) {
                html += '<a class="cws-release-link" target="_blank" rel="noopener" href="'
                      + escapeHtml(d.release_url)
                      + '">View release notes ↗</a>';
            }
            html += '</div>';
            setStatus($card, 'cws-available', html);
            $card.find('#cws-apply-btn').on('click', function(){
                applyUpdate($card, d.latest);
            });
        })
        .fail(function(xhr){
            setStatus($card, 'cws-error',
                '<strong>Update check failed:</strong> HTTP ' + xhr.status
                + ' <button type="button" class="cws-recheck-btn">Check again</button>');
        });

        // Re-check button delegate
        $container.off('click.cwsRecheck').on('click.cwsRecheck', '.cws-recheck-btn', function(){
            renderCard($container);
        });
    }

    function applyUpdate($card, latest) {
        if (!confirm('Apply v' + latest + '?\n\nFiles will be replaced. A backup will be saved into the plugin\'s backups/ directory before changes are made.')) return;
        $card.find('.cws-update-body')
            .html('<span class="cws-spinner"></span>Applying update&hellip;');

        jQuery.ajax({
            url:    BASE + '/apply-update',
            method: 'POST',
            headers: { 'X-CSRFToken': findCsrf() },
            dataType: 'json',
            cache:    false
        })
        .done(function(d){
            if (d && d.success) {
                setStatus($card, 'cws-uptodate',
                    '<p>✓ Updated to <strong>v' + escapeHtml(d.version) + '</strong>.</p>'
                    + '<p>Backup saved: <code>' + escapeHtml(d.backup_file) + '</code></p>'
                    + '<p>Reloading…</p>');
                setTimeout(function(){ window.location.reload(); }, 2000);
            } else {
                setStatus($card, 'cws-error',
                    '<strong>Update failed:</strong> '
                    + escapeHtml((d && d.error) || 'Unknown error')
                    + ' <button type="button" class="cws-recheck-btn">Check again</button>');
            }
        })
        .fail(function(xhr){
            setStatus($card, 'cws-error',
                '<strong>Update failed:</strong> HTTP ' + xhr.status
                + ' <button type="button" class="cws-recheck-btn">Check again</button>');
        });
    }

    function initUpdatesTab() {
        if (!window.jQuery) return false;
        var $ = jQuery;

        // Idempotent — never inject twice
        if ($('a[href="#cws-updates"]').length) return true;

        // Instance-edit page renders #cws-preview from FreeTextField anchor.
        // Skip there — Updates tab only belongs on the plugin-detail page.
        if ($('#cws-preview').length) return false;

        // Anchor to the #instances link's <ul> parent — that is the
        // *plugin* tab list, not the main staff nav. Avoids selector
        // collisions when osTicketAwesome theme renders multiple <ul.tabs>.
        var $instancesLink = $('a[href="#instances"]').first();
        if (!$instancesLink.length) return false;
        var $tabList = $instancesLink.closest('ul');
        if (!$tabList.length) return false;

        // Sibling tab content container — same parent as #instances pane
        var $instancesPane = $('#instances').first();
        if (!$instancesPane.length) return false;
        var $paneParent = $instancesPane.parent();

        // Inject tab + pane
        $tabList.append('<li class="cws-updates-tab-li"><a href="#cws-updates">Updates</a></li>');
        var $container = $(
            '<div id="cws-updates" class="tab_content cws-updates-tab" ' +
            'style="display:none;padding:15px;"></div>'
        );
        $paneParent.append($container);

        $tabList.on('click', 'a[href="#cws-updates"]', function(e){
            e.preventDefault();
            $tabList.find('li').removeClass('active');
            $(this).parent().addClass('active');
            $paneParent.find('.tab_content').hide();
            $container.show();
            renderCard($container);
        });

        return true;
    }

    function bootstrap() {
        if (initUpdatesTab()) return;
        var tries = 0;
        var t = setInterval(function(){
            if (initUpdatesTab() || ++tries > 30) clearInterval(t);
        }, 100);
    }

    // First boot
    bootstrap();

    // Re-bootstrap after PJAX swaps (osTicket admin uses PJAX for nav).
    if (window.jQuery) {
        jQuery(document).on('pjax:end ready', bootstrap);
    } else {
        document.addEventListener('DOMContentLoaded', bootstrap);
    }
})();
