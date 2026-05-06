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
                url_get ('^assets/(?P<file>week-start(-admin)?\.(js|css))$', 'serveAsset'),
                url_get ('^check-update$', 'checkForUpdate'),
                url_post('^apply-update$', 'applyUpdate')
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
            @filemtime($dir . 'week-start-admin.js'),
            @filemtime($dir . 'week-start-admin.css')
        );
        if (!$v) $v = time();

        $script  = '<script>window.CWS_FIRST_DAY=' . (int)$firstDay . ';</script>';
        $script .= '<script src="' . $base . 'week-start.js?v=' . $v . '"></script>';
        if ($isAdminPage) {
            $script .= '<link rel="stylesheet" type="text/css" href="'
                     . $base . 'week-start-admin.css?v=' . $v . '">';
            $script .= '<script src="' . $base . 'week-start-admin.js?v=' . $v . '"></script>';
        }

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
     * Look up the latest release on GitHub. Falls back to raw branch plugin.php
     * if the API is unreachable. Returns shape:
     *   ['current' => string, 'latest' => string, 'available' => bool,
     *    'asset_url' => string|null, 'release_url' => string|null,
     *    'error' => string]  (only one of 'available' or 'error' is meaningful)
     */
    static function checkForUpdate() {
        $local = self::getLocalManifestVersion();
        if (!$local) {
            return array('error' => 'Cannot read local plugin.php version.');
        }

        $remoteVersion = null;
        $remoteAsset   = null;
        $releaseUrl    = null;

        $apiUrl = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
        $body   = self::httpGet($apiUrl);
        if ($body) {
            $j = @json_decode($body, true);
            if (is_array($j) && !empty($j['tag_name'])) {
                $remoteVersion = ltrim((string)$j['tag_name'], 'v');
                if (!empty($j['html_url'])) $releaseUrl = (string)$j['html_url'];
                if (!empty($j['assets']) && is_array($j['assets'])) {
                    foreach ($j['assets'] as $a) {
                        if (!empty($a['browser_download_url'])
                                && substr($a['browser_download_url'], -4) === '.zip') {
                            $remoteAsset = (string)$a['browser_download_url'];
                            break;
                        }
                    }
                }
            }
        }

        if (!$remoteVersion) {
            $rawUrl = 'https://raw.githubusercontent.com/' . self::GITHUB_REPO
                    . '/' . self::GITHUB_BRANCH . '/plugin.php';
            $rawBody = self::httpGet($rawUrl);
            if ($rawBody && preg_match("/'version'\s*=>\s*'([^']+)'/", $rawBody, $m)) {
                $remoteVersion = $m[1];
            }
        }

        if (!$remoteVersion) {
            return array('error' => 'Cannot reach GitHub. Check server connectivity.');
        }

        return array(
            'current'     => $local,
            'latest'      => $remoteVersion,
            'available'   => version_compare($remoteVersion, $local, '>'),
            'asset_url'   => $remoteAsset,
            'release_url' => $releaseUrl,
        );
    }

    /**
     * Download latest release zip, backup current files, extract over plugin dir.
     * Returns {success: bool, version?: string, backup_file?: string, error?: string}.
     */
    static function applyUpdate() {
        $check = self::checkForUpdate();
        if (!empty($check['error'])) {
            return array('success' => false, 'error' => $check['error']);
        }
        if (empty($check['available'])) {
            return array('success' => false, 'error' => 'Already up to date.');
        }

        $local      = (string)$check['current'];
        $remote     = (string)$check['latest'];
        $pluginDir  = dirname(__FILE__);
        $backupsDir = $pluginDir . '/backups';

        if (!is_dir($backupsDir) && !@mkdir($backupsDir, 0755, true)) {
            return array('success' => false, 'error' => 'Cannot create backups/ directory.');
        }
        if (!is_writable($pluginDir)) {
            return array('success' => false, 'error' => 'Plugin directory not writable.');
        }
        if (!class_exists('ZipArchive')) {
            return array('success' => false, 'error' => 'ZipArchive PHP extension required.');
        }

        $backupFile = self::backupFiles($pluginDir, $backupsDir, $local, $remote);
        if (!$backupFile) {
            return array('success' => false, 'error' => 'Backup creation failed.');
        }

        $candidates = array();
        if (!empty($check['asset_url'])) $candidates[] = $check['asset_url'];
        $candidates[] = 'https://github.com/' . self::GITHUB_REPO
                      . '/releases/download/v' . $remote
                      . '/calendar-week-start-v' . $remote . '.zip';
        $candidates[] = 'https://codeload.github.com/' . self::GITHUB_REPO
                      . '/zip/refs/heads/' . self::GITHUB_BRANCH;
        $candidates[] = 'https://github.com/' . self::GITHUB_REPO
                      . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';

        $zipBody = null;
        foreach (array_unique($candidates) as $u) {
            $zipBody = self::httpGet($u);
            if ($zipBody && strlen($zipBody) > 256) break;
            $zipBody = null;
        }
        if (!$zipBody) {
            return array('success' => false, 'error' => 'Failed to download update zip.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'cws_update_');
        @file_put_contents($tmpFile, $zipBody);

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cws_update_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            self::recursiveDelete($tmpDir);
            return array('success' => false, 'error' => 'Cannot open downloaded zip.');
        }
        $zip->extractTo($tmpDir);
        $zip->close();
        @unlink($tmpFile);

        $dirs = glob($tmpDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $extractedRoot = $dirs ? $dirs[0] : null;
        if (!$extractedRoot) {
            self::recursiveDelete($tmpDir);
            return array('success' => false, 'error' => 'Invalid archive structure.');
        }

        if (!self::recursiveCopy($extractedRoot, $pluginDir)) {
            self::recursiveDelete($tmpDir);
            return array('success' => false, 'error' => 'File copy failed mid-way.');
        }
        self::recursiveDelete($tmpDir);

        if (defined('TABLE_PREFIX')) {
            @db_query(sprintf(
                "UPDATE `%splugin` SET `version` = %s WHERE `install_path` = 'plugins/calendar-week-start' LIMIT 1",
                TABLE_PREFIX,
                db_input($remote)
            ));
        }

        if (function_exists('opcache_reset')) @opcache_reset();

        return array(
            'success'     => true,
            'version'     => $remote,
            'backup_file' => basename($backupFile),
        );
    }

    /**
     * Zip the plugin directory (excluding backups/ and graphify-out/) into
     * backups/v{old}-to-v{new}-{Ymd-His}.zip. Returns absolute path on success,
     * null on failure.
     */
    private static function backupFiles($pluginDir, $backupsDir, $oldVer, $newVer) {
        if (!class_exists('ZipArchive')) return null;
        $stamp    = date('Ymd-His');
        $backFile = $backupsDir . '/v' . $oldVer . '-to-v' . $newVer . '-' . $stamp . '.zip';
        $zip      = new \ZipArchive();
        if ($zip->open($backFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $f) {
            $abs = $f->getPathname();
            $rel = ltrim(substr($abs, strlen($pluginDir)), '/\\');
            if (strpos($rel, 'backups' . DIRECTORY_SEPARATOR) === 0
                    || strpos($rel, 'backups/') === 0
                    || strpos($rel, 'graphify-out' . DIRECTORY_SEPARATOR) === 0
                    || strpos($rel, 'graphify-out/') === 0) {
                continue;
            }
            $zip->addFile($abs, $rel);
        }
        $zip->close();
        return file_exists($backFile) ? $backFile : null;
    }

    /**
     * Copy a directory tree onto another. Skips entries with traversal markers.
     * Does NOT delete files at destination that are absent in source — preserves
     * backups/ and any local-only files.
     */
    private static function recursiveCopy($src, $dst) {
        if (!is_dir($src)) return false;
        if (!is_dir($dst) && !@mkdir($dst, 0755, true)) return false;

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $rel = ltrim(substr($item->getPathname(), strlen($src)), '/\\');
            if ($rel === '' || strpos($rel, '..') !== false) continue;
            $relSlash = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            if (strpos($relSlash, 'backups/') === 0) continue;
            $target = $dst . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true)) return false;
            } else {
                if (!@copy($item->getPathname(), $target)) return false;
            }
        }
        return true;
    }

    /**
     * Recursively delete a directory.
     */
    private static function recursiveDelete($dir) {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if ($items === false) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) self::recursiveDelete($path);
            else @unlink($path);
        }
        @rmdir($dir);
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
