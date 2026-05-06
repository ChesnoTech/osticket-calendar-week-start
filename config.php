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
                    'content' => '<div id="cws-preview-wrap" style="margin-top:10px;">'
                               . '<label style="display:block;font-weight:600;margin-bottom:4px;">'
                               . __('Live Preview') . '</label>'
                               . '<div id="cws-preview"></div>'
                               . '</div>',
                ),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        $fd = isset($config['first_day']) ? (string)$config['first_day'] : '';
        if ($fd === '' || !ctype_digit($fd) || (int)$fd < 0 || (int)$fd > 6) {
            $errors['first_day'] = __('Invalid day value.');
            return false;
        }
        // Normalize to canonical string form
        $config['first_day'] = (string)(int)$fd;
        return true;
    }
}
