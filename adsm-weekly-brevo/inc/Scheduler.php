<?php
namespace Xniris;

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'XnirisBase.php';

class Scheduler extends XnirisBase {
    public function __construct() {
        add_action('xniris_newsletter_send_event', [$this, 'send_newsletter']);
    }

    public function send_newsletter() {
        // Logic to send the newsletter
    }

    private function next_timestamp() {
        $o = get_option(self::OPT_KEY, $this->admin->options());
        $tz_string = get_option('timezone_string');
        if ($tz_string) {
            try { $dtz = new \DateTimeZone($tz_string); }
            catch (\Exception $e) { $dtz = wp_timezone(); }
        } else {
            $dtz = wp_timezone();
        }
        $now = new \DateTime('now', $dtz);
        // WordPress: 0=dim ... 6=sam
        $targetDow = intval($o['dow']);
        $targetHour = intval($o['hour']);

        $next = clone $now;
        $next->setTime($targetHour, 0, 0);
        while (intval($next->format('w')) !== $targetDow || $next <= $now) {
            $next->modify('+1 day');
        }
        return $next->getTimestamp();
    }
}
