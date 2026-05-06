<?php
/**
 * Calendar Week Start Plugin - Main Class
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

require_once 'config.php';

class CalendarWeekStartPlugin extends Plugin {

    var $config_class = 'CalendarWeekStartConfig';

    const GITHUB_REPO   = 'ChesnoTech/osticket-calendar-week-start';
    const GITHUB_BRANCH = 'master';
    const HTTP_TIMEOUT  = 15;
    const HTTP_UA       = 'CalendarWeekStart-Updater/1.1.0';

    static private $bootstrapped = false;

    /**
     * Block osTicket's built-in auto-upgrade flow — we manage upgrades via
     * applyUpdate() so the admin can confirm and back up first.
     */
    function pre_upgrade(&$errors) {
        return false;
    }

    function bootstrap() {
        self::bootstrapStatic($this);
    }

    static function bootstrapStatic($pluginInstance = null) {
        if (self::$bootstrapped) return;
        self::$bootstrapped = true;

        if (PHP_SAPI === 'cli') return;

        // Asset routes on BOTH dispatchers — staff and client
        Signal::connect('ajax.scp',    array(__CLASS__, 'registerAjaxRoutes'));
        Signal::connect('ajax.client', array(__CLASS__, 'registerAjaxRoutes'));

        // Inject script before </body> on every full HTML response
        ob_start(array(__CLASS__, 'injectScript'));
    }

    static function registerAjaxRoutes($dispatcher) {
        $dir = INCLUDE_DIR . 'plugins/calendar-week-start/';
        $dispatcher->append(
            url('^/calendar-week-start/', patterns(
                $dir . 'class.CalendarWeekStartAjax.php:CalendarWeekStartAjax',
                url_get('^assets/(?P<file>week-start(-admin)?\.js|.*\.css)$', 'serveAsset')
            ))
        );
    }

    static function injectScript($html) {
        // Skip PJAX (already in DOM)
        if (!empty($_SERVER['HTTP_X_PJAX']))
            return $html;
        // Skip non-HTML output
        if (stripos($html, '</body>') === false)
            return $html;

        // Read first_day directly from config table (static context, no $this)
        $firstDay = self::readFirstDay();
        if ($firstDay < 0 || $firstDay > 6) $firstDay = 1;

        // Detect staff vs client context for correct asset path
        $reqUri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isStaff = (stripos($reqUri, '/scp/') !== false);
        $base    = ROOT_PATH . ($isStaff ? 'scp/ajax.php/' : 'ajax.php/') . 'calendar-week-start/assets/';

        // Admin config page detection (staff plugins.php)
        $isAdminPage = $isStaff && (stripos($reqUri, 'plugins.php') !== false);

        $dir = dirname(__FILE__) . '/assets/';
        $v   = max(
            @filemtime($dir . 'week-start.js'),
            @filemtime($dir . 'week-start-admin.js')
        );
        if (!$v) $v = time();

        $script  = '<script>window.CWS_FIRST_DAY=' . (int)$firstDay . ';</script>';
        $script .= '<script src="' . $base . 'week-start.js?v=' . $v . '"></script>';
        if ($isAdminPage)
            $script .= '<script src="' . $base . 'week-start-admin.js?v=' . $v . '"></script>';

        return str_ireplace('</body>', $script . '</body>', $html);
    }

    /**
     * Read first_day from this plugin's config without instantiating
     * the plugin object via the OO API — required because injectScript runs in
     * a static context where the plugin instance lifecycle is uncertain.
     *
     * osTicket PluginInstance::getNamespace() returns
     *   "plugin.{plugin_id}.instance.{instance_id}"
     * — see WebRoot/include/class.plugin.php::PluginInstance::getNamespace().
     *
     * Falls back to default 1 (Monday) on any error.
     */
    static function readFirstDay() {
        if (!defined('TABLE_PREFIX')) return 1;
        $sql = sprintf(
            "SELECT pi.id AS instance_id, p.id AS plugin_id
             FROM `%splugin` p
             JOIN `%splugin_instance` pi ON pi.plugin_id = p.id
             WHERE p.install_path = 'plugins/calendar-week-start'
               AND (pi.flags & 1) = 1
             ORDER BY pi.id LIMIT 1",
            TABLE_PREFIX, TABLE_PREFIX
        );
        $res = @db_query($sql);
        if (!$res) return 1;
        $row = db_fetch_array($res);
        if (!$row) return 1;

        $namespace = sprintf('plugin.%d.instance.%d',
            (int)$row['plugin_id'], (int)$row['instance_id']);

        $cfgRes = @db_query(sprintf(
            "SELECT `value` FROM `%sconfig`
             WHERE `namespace` = %s AND `key` = 'first_day' LIMIT 1",
            TABLE_PREFIX,
            db_input($namespace)
        ));
        if (!$cfgRes) return 1;
        $cfgRow = db_fetch_array($cfgRes);
        if (!$cfgRow) return 1;

        // ChoiceField stores values as JSON {key: label} — extract first key.
        // Fallback to raw scalar interpretation if not JSON.
        $raw = (string)$cfgRow['value'];
        $i = -1;
        if ($raw !== '' && $raw[0] === '{') {
            $decoded = @json_decode($raw, true);
            if (is_array($decoded) && count($decoded) >= 1) {
                $keys = array_keys($decoded);
                $i = (int)$keys[0];
            }
        }
        if ($i < 0) $i = (int)$raw;
        return ($i >= 0 && $i <= 6) ? $i : 1;
    }

    /**
     * Read 'version' from this plugin's plugin.php manifest.
     * Returns null if not readable.
     */
    private static function getLocalManifestVersion() {
        $f = dirname(__FILE__) . '/plugin.php';
        if (!file_exists($f)) return null;
        $info = @include $f;
        return is_array($info) && !empty($info['version'])
            ? (string)$info['version']
            : null;
    }

    /**
     * GET an HTTP(S) URL. curl preferred; file_get_contents fallback.
     * Returns body string on success, null on failure.
     */
    private static function httpGet($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT      => self::HTTP_UA,
                CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github+json'),
            ));
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 400) return $body;
            return null;
        }
        $opts = array('http' => array(
            'method'  => 'GET',
            'timeout' => self::HTTP_TIMEOUT,
            'header'  => "User-Agent: " . self::HTTP_UA . "\r\n"
                       . "Accept: application/vnd.github+json\r\n",
        ));
        $body = @file_get_contents($url, false, stream_context_create($opts));
        return $body !== false ? $body : null;
    }
}

// Static bootstrap on staff or client request
if (defined('STAFFINC_DIR') || defined('CLIENTINC_DIR'))
    CalendarWeekStartPlugin::bootstrapStatic();
