<?php
/**
 * Calendar Week Start Plugin - Configuration
 *
 * @author  ChesnoTech
 * @version 1.0.0
 */

require_once INCLUDE_DIR . 'class.forms.php';
require_once INCLUDE_DIR . 'class.plugin.php';

class CalendarWeekStartConfig extends PluginConfig {

    function getOptions() {
        return array(
            'cws_info' => new SectionBreakField(array(
                'label' => __('Calendar Week Start'),
                'hint'  => __('First day shown in all date pickers across staff panel and client portal.'),
            )),
            'first_day' => new ChoiceField(array(
                'label'    => __('First Day of Week'),
                'required' => true,
                'default'  => '1',
                'choices'  => array(
                    '0' => __('Sunday'),
                    '1' => __('Monday'),
                    '2' => __('Tuesday'),
                    '3' => __('Wednesday'),
                    '4' => __('Thursday'),
                    '5' => __('Friday'),
                    '6' => __('Saturday'),
                ),
                'hint'     => __('jQuery UI firstDay value (0=Sunday … 6=Saturday).'),
            )),
            'preview_anchor' => new FreeTextField(array(
                'configuration' => array(
                    'content' => '<style>'
                               . '#cws-preview-wrap{margin-top:10px;}'
                               . '#cws-preview-wrap label{display:block;font-weight:600;margin-bottom:4px;}'
                               . '#cws-preview{display:inline-block;}'
                               . '#cws-preview .ui-datepicker{width:auto;min-width:18em;}'
                               . '#cws-preview .ui-datepicker-inline{width:auto;}'
                               . '#cws-preview table{width:auto;}'
                               . '#cws-preview td,#cws-preview th{padding:.2em .35em;}'
                               . '</style>'
                               . '<div id="cws-preview-wrap">'
                               . '<label>' . __('Live Preview') . '</label>'
                               . '<div id="cws-preview"></div>'
                               . '</div>'
                               . '<div id="cws-updates-panel">'
                               . '<span class="cws-spinner"></span>'
                               . __('Checking for updates&hellip;')
                               . '</div>',
                ),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        // ChoiceField getClean() returns ['<key>' => '<label>']; raw posts may be scalar
        $raw = isset($config['first_day']) ? $config['first_day'] : null;
        $key = null;
        if (is_array($raw) && count($raw) >= 1) {
            $keys = array_keys($raw);
            $key  = (string)$keys[0];
        } elseif (is_string($raw) || is_numeric($raw)) {
            // Could be JSON string, plain int, or plain string
            $s = (string)$raw;
            if ($s !== '' && $s[0] === '{') {
                $d = @json_decode($s, true);
                if (is_array($d) && count($d) >= 1) {
                    $kk = array_keys($d);
                    $key = (string)$kk[0];
                }
            } else {
                $key = $s;
            }
        }
        if ($key === null || $key === '' || !ctype_digit($key)
                || (int)$key < 0 || (int)$key > 6) {
            $errors['first_day'] = __('Invalid day value.');
            return false;
        }
        return true;
    }
}
