<?php
class SFC_Calendar_Display {

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'theme' => 'light',
            'show_refresh' => 'yes',
            'show_legend' => 'yes'
        ), $atts);

        $current_month = date('n');
        $current_year = date('Y');

        ob_start();
        ?>
        <div class="sfc-calendar-wrapper sfc-theme-<?php echo esc_attr($atts['theme']); ?>"
             data-theme="<?php echo esc_attr($atts['theme']); ?>"
             data-month="<?php echo $current_month; ?>"
             data-year="<?php echo $current_year; ?>">

            <div class="sfc-calendar-header">
                <button class="sfc-nav-button sfc-prev-month" aria-label="Previous month">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>

                <h2 class="sfc-current-month-year">
                    <span class="sfc-month-name"><?php echo date('F'); ?></span>
                    <span class="sfc-year"><?php echo date('Y'); ?></span>
                </h2>

                <button class="sfc-nav-button sfc-next-month" aria-label="Next month">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>

                <?php if ($atts['show_refresh'] === 'yes'): ?>
                <button class="sfc-refresh-button" title="Refresh Calendar">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <?php endif; ?>
            </div>

            <div class="sfc-calendar-content">
                <?php echo $this->render_calendar($current_year, $current_month); ?>
            </div>

            <?php if ($atts['show_legend'] === 'yes'): ?>
            <div class="sfc-calendar-legend">
                <?php echo $this->render_legend(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_calendar($year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = date('n');

        // Get events for this month
        require_once SFC_PLUGIN_DIR . 'includes/class-sfc-ical-parser.php';
        $parser = new SFC_ICal_Parser();
        $events = $parser->get_events_for_month($year, $month);

        // Group events by day
        $events_by_day = array();
        foreach ($events as $event) {
            $day = intval($event['day']);
            if (!isset($events_by_day[$day])) {
                $events_by_day[$day] = array();
            }
            $events_by_day[$day][] = $event;
        }

        ob_start();
        ?>
        <div class="sfc-calendar-grid">
            <div class="sfc-weekdays">
                <?php
                $weekdays = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
                foreach ($weekdays as $day) {
                    echo '<div class="sfc-weekday">' . $day . '</div>';
                }
                ?>
            </div>

            <div class="sfc-days">
                <?php
                $first_day = mktime(0, 0, 0, $month, 1, $year);
                $days_in_month = date('t', $first_day);
                $start_weekday = date('w', $first_day);
                $today = date('Y-m-d');

                // Add empty cells for days before month starts
                for ($i = 0; $i < $start_weekday; $i++) {
                    echo '<div class="sfc-day sfc-other-month"></div>';
                }

                // Add days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $is_today = ($current_date === $today);
                    $day_events = isset($events_by_day[$day]) ? $events_by_day[$day] : array();

                    $this->render_day($day, $day_events, $is_today);
                }

                // Add empty cells for remaining grid spaces
                $total_cells = $start_weekday + $days_in_month;
                $remaining_cells = 35 - $total_cells; // 5 rows x 7 days
                if ($remaining_cells < 0) $remaining_cells += 7; // Add another row if needed

                for ($i = 0; $i < $remaining_cells; $i++) {
                    echo '<div class="sfc-day sfc-other-month"></div>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

	private function render_day($day, $events, $is_today) {
		$options = get_option('sfc_options', array());
		$max_display = isset($options['events_per_day']) ? intval($options['events_per_day']) : 3;
		?>
		<div class="sfc-day <?php echo $is_today ? 'sfc-today' : ''; ?>" data-day="<?php echo $day; ?>">
			<div class="sfc-day-number"><?php echo $day; ?></div>
			<?php if (!empty($events)): ?>
			<div class="sfc-day-events" data-max-display="<?php echo $max_display; ?>">
				<?php
				foreach ($events as $index => $event):
					$event_classes = array('sfc-event');
					if ($event['all_day']) {
						$event_classes[] = 'sfc-all-day-event';
					}
					if (isset($event['is_multi_day']) && $event['is_multi_day']) {
						$event_classes[] = 'sfc-multi-day-event';
						if ($event['is_first_day']) $event_classes[] = 'sfc-first-day';
						if ($event['is_last_day']) $event_classes[] = 'sfc-last-day';
					}

					// Add class for hidden events
					if ($index >= $max_display) {
						$event_classes[] = 'sfc-event-hidden';
					}
				?>
				<div class="<?php echo implode(' ', $event_classes); ?>"
					 style="background-color: <?php echo esc_attr($event['feed_color']); ?>;"
					 data-event-title="<?php echo esc_attr($event['summary']); ?>"
					 data-event-time="<?php echo esc_attr($event['start_time'] . ($event['end_time'] ? ' - ' . $event['end_time'] : '')); ?>"
					 data-event-location="<?php echo esc_attr($event['location']); ?>">
					<?php if (!$event['all_day'] && $event['display_time']): ?>
					<span class="sfc-event-time"><?php echo esc_html($event['display_time']); ?></span>
					<?php endif; ?>
					<span class="sfc-event-title"><?php echo esc_html($event['summary']); ?></span>
				</div>
				<?php
				endforeach;

				if (count($events) > $max_display): ?>
				<div class="sfc-more-events" data-hidden-count="<?php echo (count($events) - $max_display); ?>">
					<span class="sfc-more-text">+<?php echo (count($events) - $max_display); ?> <?php _e('more', 'strategicli-family-calendar'); ?></span>
					<span class="sfc-less-text" style="display: none;"><?php _e('Show less', 'strategicli-family-calendar'); ?></span>
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}



    private function render_legend() {
        $options = get_option('sfc_options', array());
        $feeds = isset($options['feeds']) ? $options['feeds'] : array();

        if (empty($feeds)) {
            return '';
        }

        ob_start();
        ?>
        <div class="sfc-legend-items">
            <?php foreach ($feeds as $feed): ?>
                <?php if (!empty($feed['name']) && !empty($feed['url'])): ?>
                <div class="sfc-legend-item">
                    <span class="sfc-legend-color"
                          style="background-color: <?php echo esc_attr($feed['color']); ?>"></span>
                    <span class="sfc-legend-name"><?php echo esc_html($feed['name']); ?></span>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_refresh_calendar() {
        check_ajax_referer('sfc_calendar_nonce', 'nonce');

        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        // Clear calendar cache
        $options = get_option('sfc_options', array());
        $feeds = isset($options['feeds']) ? $options['feeds'] : array();

        foreach ($feeds as $feed) {
            if (!empty($feed['url'])) {
                delete_transient('sfc_feed_' . md5($feed['url']));
            }
        }

        $html = $this->render_calendar($year, $month);

        wp_send_json_success(array(
            'html' => $html
        ));
    }

    public function ajax_navigate_month() {
        check_ajax_referer('sfc_calendar_nonce', 'nonce');

        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        $html = $this->render_calendar($year, $month);

        wp_send_json_success(array(
            'html' => $html,
            'month' => $month,
            'year' => $year,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year))
        ));
    }
}
