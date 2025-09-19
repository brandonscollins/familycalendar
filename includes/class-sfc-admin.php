<?php
class SFC_Admin {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('Strategicli Family Calendar Settings', 'strategicli-family-calendar'),
            __('Family Calendar', 'strategicli-family-calendar'),
            'manage_options',
            'sfc-settings',
            array($this, 'settings_page')
        );
    }

    public function settings_init() {
        register_setting('sfc_settings', 'sfc_options', array($this, 'sanitize_options'));
        add_settings_section('sfc_settings_section', __('Calendar Settings', 'strategicli-family-calendar'), array($this, 'settings_section_callback'), 'sfc-settings');
        add_settings_field('sfc_feeds', __('Calendar Feeds', 'strategicli-family-calendar'), array($this, 'feeds_field_callback'), 'sfc-settings', 'sfc_settings_section');
        add_settings_field('sfc_cache_duration', __('Cache Duration', 'strategicli-family-calendar'), array($this, 'cache_duration_callback'), 'sfc-settings', 'sfc_settings_section');
        add_settings_field('sfc_events_per_day', __('Events Per Day', 'strategicli-family-calendar'), array($this, 'events_per_day_callback'), 'sfc-settings', 'sfc_settings_section');
        add_settings_field('sfc_timezone', __('Timezone', 'strategicli-family-calendar'), array($this, 'timezone_callback'), 'sfc-settings', 'sfc_settings_section');
    }

    public function settings_section_callback() {
        echo '<p>' . __('Configure your calendar feeds and display options.', 'strategicli-family-calendar') . '</p>';
    }

    public function feeds_field_callback() {
        $this->options = get_option('sfc_options', array());
        $feeds = isset($this->options['feeds']) ? $this->options['feeds'] : array();
        ?>
        <div id="sfc-feeds-container">
            <?php
            $index = 0;
            foreach ($feeds as $feed) {
                $this->render_feed_row($index++, $feed);
            }
            ?>
        </div>
        <button type="button" class="button" id="sfc-add-feed"><?php _e('Add Calendar Feed', 'strategicli-family-calendar'); ?></button>
         <p class="description">
            <?php _e('Enter the iCal feed URL for each calendar you want to display.', 'strategicli-family-calendar'); ?>
            <br>
            <a href="https://support.google.com/calendar/answer/37103" target="_blank" rel="noopener">
                <?php _e('How to find Google Calendar URL', 'strategicli-family-calendar'); ?>
            </a> |
            <a href="https://support.microsoft.com/en-us/office/import-or-subscribe-to-a-calendar-in-outlook-com-cff1429c-5af6-41ec-a5b4-74f2c278e98c" target="_blank" rel="noopener">
                <?php _e('How to find Outlook Calendar URL', 'strategicli-family-calendar'); ?>
            </a>
        </p>
        <?php
    }

    private function render_feed_row($index, $feed) {
        ?>
        <div class="sfc-feed-row">
            <input type="text" name="sfc_options[feeds][<?php echo $index; ?>][name]" value="<?php echo esc_attr($feed['name'] ?? ''); ?>" placeholder="<?php _e('Calendar Name', 'strategicli-family-calendar'); ?>" class="sfc-feed-name" />
            <input type="url" name="sfc_options[feeds][<?php echo $index; ?>][url]" value="<?php echo esc_url($feed['url'] ?? ''); ?>" placeholder="<?php _e('iCal Feed URL...', 'strategicli-family-calendar'); ?>" class="sfc-feed-url" />
            <input type="color" name="sfc_options[feeds][<?php echo $index; ?>][color]" value="<?php echo esc_attr($feed['color'] ?? '#3788d8'); ?>" class="sfc-color-picker" />
            <input type="number" name="sfc_options[feeds][<?php echo $index; ?>][offset]" value="<?php echo esc_attr($feed['offset'] ?? '0'); ?>" title="<?php _e('Timezone Offset (hours)', 'strategicli-family-calendar'); ?>" step="1" style="width: 60px; text-align: center;" />
            <button type="button" class="button sfc-remove-feed"><?php _e('Remove', 'strategicli-family-calendar'); ?></button>
        </div>
        <?php
    }

    public function cache_duration_callback() {
        $this->options = get_option('sfc_options', array());
        $duration = $this->options['cache_duration'] ?? 30;
        printf('<input type="number" name="sfc_options[cache_duration]" value="%d" min="5" max="1440" /> %s', esc_attr($duration), __('minutes', 'strategicli-family-calendar'));
    }

    public function timezone_callback() {
        $this->options = get_option('sfc_options', array());
        $current_timezone = $this->options['timezone'] ?? wp_timezone_string();
        printf('<select name="sfc_options[timezone]">%s</select>', wp_timezone_choice($current_timezone, get_user_locale()));
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="notice notice-info" style="margin: 20px 0;">
                <p>
                    <strong><?php _e('Quick Start:', 'strategicli-family-calendar'); ?></strong>
                    <?php _e('Add your calendar feeds below, save the settings, then use the shortcode', 'strategicli-family-calendar'); ?>
                    <code>[sfc_calendar]</code>
                    <?php _e('on any page or post to display your calendar.', 'strategicli-family-calendar'); ?>
                </p>
                 <p>
                    <strong><?php _e('Timezone Offset Tip:', 'strategicli-family-calendar'); ?></strong>
                    <?php _e('If events on a specific calendar are off by a few hours, use the number field to apply a manual offset (e.g., -5 or +2). This only affects timed events, not all-day events.', 'strategicli-family-calendar'); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('sfc_settings');
                do_settings_sections('sfc-settings');
                submit_button();
                ?>
            </form>

             <div class="sfc-admin-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h3><?php _e('Available Shortcode Options:', 'strategicli-family-calendar'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>[sfc_calendar]</code> - <?php _e('Default calendar', 'strategicli-family-calendar'); ?></li>
                    <li><code>[sfc_calendar theme="dark"]</code> - <?php _e('Dark theme', 'strategicli-family-calendar'); ?></li>
                    <li><code>[sfc_calendar show_refresh="no"]</code> - <?php _e('Hide refresh button', 'strategicli-family-calendar'); ?></li>
                    <li><code>[sfc_calendar show_legend="no"]</code> - <?php _e('Hide calendar legend', 'strategicli-family-calendar'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function sanitize_options($input) {
        $sanitized = [];
        if (isset($input['feeds']) && is_array($input['feeds'])) {
            foreach ($input['feeds'] as $feed) {
                if (!empty($feed['url'])) {
                    $sanitized['feeds'][] = [
                        'name'   => sanitize_text_field($feed['name']),
                        'url'    => esc_url_raw($feed['url']),
                        'color'  => sanitize_hex_color($feed['color']),
                        'offset' => isset($feed['offset']) ? intval($feed['offset']) : 0,
                    ];
                }
            }
        }
        $sanitized['cache_duration'] = isset($input['cache_duration']) ? max(5, intval($input['cache_duration'])) : 30;
        $sanitized['events_per_day'] = isset($input['events_per_day']) ? max(1, intval($input['events_per_day'])) : 3;
        $sanitized['timezone'] = isset($input['timezone']) && in_array($input['timezone'], timezone_identifiers_list()) ? $input['timezone'] : wp_timezone_string();
        return $sanitized;
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_sfc-settings' !== $hook) return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('sfc-admin', SFC_PLUGIN_URL . 'assets/js/sfc-admin.js', ['jquery', 'wp-color-picker'], SFC_VERSION, true);
        wp_add_inline_style('sfc-admin-style', '.sfc-feed-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap; } .sfc-feed-url { flex: 1; }');
    }
	
	public function events_per_day_callback() {
        $this->options = get_option('sfc_options', array());
		$events_per_day = $this->options['events_per_day'] ?? 3;
		printf('<input type="number" name="sfc_options[events_per_day]" value="%d" min="1" max="10" /> %s', esc_attr($events_per_day), __('events', 'strategicli-family-calendar'));
	}
}
