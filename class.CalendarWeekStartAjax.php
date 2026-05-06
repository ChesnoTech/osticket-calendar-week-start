<?php
/**
 * Calendar Week Start Plugin - AJAX Controller
 *
 * Serves static JS/CSS assets with filemtime ETag and 7-day cache.
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

require_once INCLUDE_DIR . 'class.ajax.php';

class CalendarWeekStartAjax extends AjaxController {

    function serveAsset($file) {
        // Whitelist — regex in router already limits, double-check here
        $allowed = array(
            'week-start.js'       => 'application/javascript; charset=UTF-8',
            'week-start-admin.js' => 'application/javascript; charset=UTF-8',
        );
        // Permit any *.css (router already restricts)
        if (preg_match('/^[a-z0-9_-]+\.css$/i', $file))
            $allowed[$file] = 'text/css; charset=UTF-8';

        if (!isset($allowed[$file])) {
            Http::response(404, 'Not found');
            return;
        }

        $path = INCLUDE_DIR . 'plugins/calendar-week-start/assets/' . $file;
        if (!is_file($path)) {
            Http::response(404, 'Asset missing');
            return;
        }

        $mtime = filemtime($path);
        $etag  = '"' . md5($file . $mtime) . '"';

        // 304 fast path
        $ifNone = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';
        if ($ifNone === $etag) {
            header('HTTP/1.1 304 Not Modified');
            header('ETag: ' . $etag);
            return;
        }

        header('Content-Type: ' . $allowed[$file]);
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=604800'); // 7 days
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    /**
     * GET /calendar-week-start/check-update
     * Returns JSON {current, latest, available, asset_url, release_url, error?}
     */
    function checkForUpdate() {
        global $thisstaff;
        if (!$thisstaff) {
            Http::response(403, 'Staff login required');
            return;
        }
        require_once INCLUDE_DIR . 'plugins/calendar-week-start/class.CalendarWeekStartPlugin.php';
        $result = CalendarWeekStartPlugin::checkForUpdate();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result);
    }

    /**
     * POST /calendar-week-start/apply-update
     * Returns JSON {success, version?, backup_file?, error?}
     */
    function applyUpdate() {
        global $thisstaff;
        if (!$thisstaff) {
            Http::response(403, 'Staff login required');
            return;
        }
        require_once INCLUDE_DIR . 'plugins/calendar-week-start/class.CalendarWeekStartPlugin.php';
        $result = CalendarWeekStartPlugin::applyUpdate();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($result);
    }
}
