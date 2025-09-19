<?php
class SFC_ICal_Parser {
    private $feeds;
    private $cache_duration;
    private $timezone;

    public function __construct() {
        $options = get_option('sfc_options', array());
        $this->feeds = isset($options['feeds']) ? $options['feeds'] : array();
        $this->cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 30;
        $this->timezone = wp_timezone(); 
        if (isset($options['timezone']) && !empty($options['timezone'])) {
            try {
                $this->timezone = new \DateTimeZone($options['timezone']);
            } catch (\Exception $e) {
                error_log('SFC: Invalid timezone in settings: ' . $options['timezone']);
            }
        }
    }

    public function get_events_for_month($year, $month) {
        $start_date = mktime(0, 0, 0, $month, 1, $year);
        $end_date = mktime(23, 59, 59, $month + 1, 0, $year);
        $all_events = array();
        foreach ($this->feeds as $feed) {
            if (empty($feed['url'])) continue;
            $events = $this->get_feed_events($feed, $start_date, $end_date);
            $all_events = array_merge($all_events, $events);
        }
        usort($all_events, function($a, $b) {
            if ($a['all_day'] && !$b['all_day']) return -1;
            if (!$a['all_day'] && $b['all_day']) return 1;
            return $a['start_timestamp'] - $b['start_timestamp'];
        });
        return $all_events;
    }

    private function get_feed_events($feed, $start_date, $end_date) {
        $cache_key = 'sfc_feed_' . md5($feed['url']);
        $events = get_transient($cache_key);
    
        if (false === $events) {
            $response = wp_remote_get($feed['url'], array('timeout' => 15, 'sslverify' => true));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                error_log('SFC: Failed to fetch feed ' . $feed['url'] . ' - ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response)));
                return array();
            }
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) { return array(); }
            $events = $this->parse_ical_data($body);
            set_transient($cache_key, $events, $this->cache_duration * MINUTE_IN_SECONDS);
        }
    
        // --- FINAL, CORRECTED OFFSET LOGIC ---
        // Only apply the offset if it's set and not zero.
        $offset_hours = isset($feed['offset']) ? intval($feed['offset']) : 0;
        if ($offset_hours !== 0) {
            $offset_seconds = $offset_hours * 3600;
            foreach ($events as &$event) {
                // IMPORTANT: Only apply the offset to TIMED events, not all-day events.
                if (!$event['all_day']) {
                    if (!empty($event['start'])) { $event['start'] += $offset_seconds; }
                    if (!empty($event['end'])) { $event['end'] += $offset_seconds; }
                }
            }
            unset($event);
        }
    
        return $this->filter_and_enhance_events($events, $start_date, $end_date, $feed);
    }

    private function parse_ical_data($ical_content) {
        $events = array();
        $lines = preg_split('/\\r\\n|\\n|\\r/', $ical_content);
        $event = null;
        $in_event = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (substr($line, 0, 1) === ' ' && $event !== null) {
                end($event); $lastKey = key($event);
                if (isset($event[$lastKey])) { $event[$lastKey] .= substr($line, 1); }
                continue;
            }

            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $event = array('uid' => '', 'summary' => '', 'description' => '', 'location' => '', 'start' => '', 'end' => '', 'all_day' => false);
            } elseif ($line === 'END:VEVENT' && $in_event) {
                if (!empty($event['summary']) && !empty($event['start'])) {
                    if (empty($event['end'])) { $event['end'] = $event['start']; }
                    $events[] = $event;
                }
                $event = null; $in_event = false;
            } elseif ($in_event && strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key_parts = explode(';', $key);
                $main_key = $key_parts[0];

                switch ($main_key) {
                    case 'UID': case 'SUMMARY': case 'DESCRIPTION': case 'LOCATION':
                        $event[strtolower($main_key)] = $this->unescape_text($value);
                        break;
                    case 'DTSTART':
                        $date_info = $this->parse_date($value, $key_parts);
                        $event['start'] = $date_info['timestamp'];
                        $event['all_day'] = $date_info['all_day'];
                        break;
                    case 'DTEND':
                        $date_info = $this->parse_date($value, $key_parts);
                        if ($event['all_day'] && $date_info['timestamp'] > $event['start']) {
                            // This is the correct way to handle exclusive end dates for all-day events.
                            // An event for the 10th ends on the 11th at 00:00. We subtract one second
                            // to keep it on the 10th.
                            $event['end'] = $date_info['timestamp'] - 1;
                        } else {
                            $event['end'] = $date_info['timestamp'];
                        }
                        break;
                }
            }
        }
        return $events;
    }

    private function parse_date($date_string, $params) {
        $is_all_day = false; $tzid = null;
        foreach ($params as $param) {
            if (strpos($param, 'VALUE=DATE') !== false) { $is_all_day = true;
            } elseif (strpos($param, 'TZID=') === 0) { $tzid = substr($param, 5); }
        }
        if (!$is_all_day && strlen(trim($date_string)) === 8) { $is_all_day = true; }
        $date_string = trim(str_replace('Z', '', $date_string));
        try {
            // =================== FIX STARTS HERE ===================
            // The logic has been changed to handle all-day events more reliably.
            if ($is_all_day) {
                // For all-day events, we parse them as UTC. This creates a stable, timezone-agnostic
                // timestamp that represents the exact start of the day. This prevents the calendar's
                // display timezone from causing the date to shift during this initial parsing step.
                // The correct timezone is applied later, only for display.
                $date = new \DateTime($date_string, new \DateTimeZone('UTC'));
            } else {
                // For timed events, we respect the timezone ID from the feed (if it exists)
                // or fall back to the plugin's configured display timezone. This is the correct
                // behavior for events that happen at a specific time.
                $source_tz = $tzid ? new \DateTimeZone($tzid) : $this->timezone;
                $date = new \DateTime($date_string, $source_tz);
            }
            // =================== FIX ENDS HERE =====================

            $timestamp = $date->getTimestamp();
        } catch (\Exception $e) {
            error_log('SFC: Date parse error - ' . $e->getMessage());
            return ['timestamp' => 0, 'all_day' => $is_all_day];
        }
        return ['timestamp' => $timestamp, 'all_day' => $is_all_day];
    }
    
    private function unescape_text($text) {
        return str_replace(['\\n', '\\N', '\\,', '\\;'], ["\n", "\n", ",", ";"], $text);
    }

    private function filter_and_enhance_events($events, $start_date, $end_date, $feed) {
        $filtered = array();
        $display_tz = $this->timezone;
    
        foreach ($events as $event) {
            if (empty($event['start']) || empty($event['end'])) continue;
            // This check is a simple timestamp comparison and remains correct.
            if ($event['start'] > $end_date || $event['end'] < $start_date) continue;
    
            // This logic correctly takes the parsed timestamps and creates DateTime objects
            // in the desired display timezone. Because we now parse all-day events into
            // stable UTC timestamps, this display logic will work as intended.
            if ($event['all_day']) {
                $start_dt_str = gmdate('Y-m-d 00:00:00', $event['start']);
                $end_dt_str   = gmdate('Y-m-d 23:59:59', $event['end']);
                $start_dt = new \DateTime($start_dt_str, $display_tz);
                $end_dt   = new \DateTime($end_dt_str, $display_tz);
            } else {
                $start_dt = (new \DateTime('@' . $event['start']))->setTimezone($display_tz);
                $end_dt   = (new \DateTime('@' . $event['end']))->setTimezone($display_tz);
            }
            
            $loop_start_dt = new \DateTime($start_dt->format('Y-m-d 00:00:00'), $display_tz);
            $loop_end_dt   = new \DateTime($end_dt->format('Y-m-d 00:00:00'), $display_tz);
    
            $interval = new \DateInterval('P1D');
    
            while ($loop_start_dt <= $loop_end_dt) {
                $current_day_ts = $loop_start_dt->getTimestamp();
                if ($current_day_ts >= $start_date && $current_day_ts <= $end_date) {
                    $event_start_date_str = $start_dt->format('Y-m-d');
                    $event_end_date_str   = $end_dt->format('Y-m-d');
                    
                    $day_event = $event;
                    $day_event['feed_name'] = $feed['name'];
                    $day_event['feed_color'] = $feed['color'];
                    $day_event['start_timestamp'] = $event['start'];
                    $day_event['end_timestamp'] = $event['end'];
                    $day_event['display_date'] = $current_day_ts;
                    $day_event['is_multi_day'] = ($event_start_date_str !== $event_end_date_str);
                    $day_event['is_first_day'] = ($loop_start_dt->format('Y-m-d') === $event_start_date_str);
                    $day_event['is_last_day']  = ($loop_start_dt->format('Y-m-d') === $event_end_date_str);
                    $day_event['day'] = $loop_start_dt->format('j');

                    if ($event['all_day']) {
                        $day_event['start_time'] = '';
                        $day_event['end_time'] = '';
                        $day_event['display_time'] = 'All day';
                    } else {
                        $day_event['start_time'] = $start_dt->format('g:i a');
                        $day_event['end_time'] = $end_dt->format('g:i a');
                        $day_event['display_time'] = ($day_event['is_first_day'] || !$day_event['is_multi_day']) ? $day_event['start_time'] : '';
                    }
                    $filtered[] = $day_event;
                }
                $loop_start_dt->add($interval);
            }
        }
        return $filtered;
    }
}
