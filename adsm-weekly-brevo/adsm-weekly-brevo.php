<?php
/**
 * Plugin Name: Xniris – Newsletter hebdo (Brevo)
 * Description: Envoie chaque semaine un récap des articles (7 derniers jours par défaut) via l’API d'un service d'emailing. (Brevo Uniquement)
 * Version: 1.0.0
 * Author: David G
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/inc/BrevoClient.php';
require_once __DIR__ . '/inc/Admin.php';
require_once __DIR__ . '/inc/SecretKeyManager.php';
require_once __DIR__ . '/inc/ApiKeyManager.php';

use Xniris\Admin;
use Xniris\BrevoClient;
use Xniris\SecretKeyManager;
use Xniris\ApiKeyManager;
use Xniris\ClientInterface;

$OPT_KEY = 'xniris_weekly_brevo_options';
$CRON_HOOK = 'xniris_weekly_brevo_send_event';
$client = new BrevoClient();
$secret_key_manager = new SecretKeyManager();
$api_key_manager = new ApiKeyManager($secret_key_manager, $client);
$admin = new Admin($client, $secret_key_manager, $api_key_manager);


class Xniris_Newsletter {
    public const OPT_KEY = 'xniris_weekly_brevo_options';
    public const CRON_HOOK = 'xniris_weekly_brevo_send_event';

    public function __construct(
        public readonly Admin $admin, 
        public readonly ClientInterface $client,
        public readonly ApiKeyManager $apiKeyManager
        ) {
        add_action('admin_menu', [$admin, 'add_menu']);
        add_action('admin_init', [$admin, 'register_settings']);
        add_action(self::CRON_HOOK, [$this, 'build_and_send']);
        add_action('update_option_' . self::OPT_KEY, [$this, 'maybe_reschedule'], 10, 3);
        register_activation_hook(__FILE__, [$this, 'on_activate']);
        register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
        add_action('admin_post_xniris_newsletter_send_now', [$this, 'handle_send_now']);        
    }
    /* -------------------- Scheduling -------------------- */
    public function on_activate() {
        $this->schedule_next();
    }

    public function on_deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function maybe_reschedule($old, $value, $option) {
        $this->schedule_next(true);
    }

    private function schedule_next($clear = false) {
        if ($clear) wp_clear_scheduled_hook(self::CRON_HOOK);
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $ts = $this->next_timestamp();
            if ($ts) wp_schedule_event($ts, 'weekly', self::CRON_HOOK);
        }
    }

    private function next_timestamp() {
        $o = get_option(self::OPT_KEY, $this->admin->options());
        $tz_string = get_option('timezone_string');
        if ($tz_string) {
            try { $dtz = new DateTimeZone($tz_string); }
            catch (\Exception $e) { $dtz = wp_timezone(); }
        } else {
            $dtz = wp_timezone();
        }
        $now = new DateTime('now', $dtz);
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

    /* -------------------- Manual trigger -------------------- */
    
    public function handle_send_now() {
        if (!current_user_can('manage_options')) wp_die('Nope.');
        check_admin_referer('xniris_newsletter_send_now');

        $ok = $this->build_and_send(true); // true = test manuel

        // rediriger avec paramètre ?sent=1 ou ?sent=0
        wp_safe_redirect(add_query_arg('sent', $ok ? '1' : '0', admin_url('options-general.php?page=xniris-newsletter')));
        exit;
    }

    /* -------------------- Core: build & send -------------------- */

    public function build_and_send($test = false) {
        $options = get_option(self::OPT_KEY, $this->admin->options());
        if (empty($options['api_key']) || empty($options['sender_email']) || empty($options['list_id'])) {
            error_log('[Xniris Newsletter] Options incomplètes.');
            return false;
        }

        $posts_html = $this->collect_posts_html($options);
        if (!$posts_html) {
            error_log('[Xniris Newsletter] Aucun article dans la période.');
            return true; // Rien à envoyer, mais pas une erreur
        }

        $html = $this->wrap_html($options, $posts_html);

        $subject = $options['subject'];

        // Envoi via Marketing API: créer une campagne + sendNow
        $decrypted_api_key = $this->apiKeyManager->get_decrypted_api_key();
        $this->client->set_apiKey($decrypted_api_key);
        // $client = new BrevoClient($options['api_key']);
        $resp = $this->client->create_campaign($options, $subject, $html, $test);
        if(is_array($resp) && isset($resp['id'])) {
            $campaignId = $resp['id'];
        } else {
            error_log('[Xniris Newsletter] Réponse inattendue lors de la création de campagne : ' . print_r($resp, true));
            return false;
        }        
        if (!$campaignId) {
            error_log('[Xniris Newsletter] Échec création campagne.');
            return false;
        }
        if($test) {
            $sent = $this->client->send_campaign_now($campaignId, true); // test
        }else{
            $sent = $this->client->send_campaign_now($campaignId);
        }
        if (!$sent) {
            error_log('[Xniris Newsletter] Échec envoi campagne.');
            return false;
        }

        return true;
    }

    private function collect_posts_html($o) {
        $days = max(1, intval($o['window_days']));
        $date_query = [
            'after' => $days . ' days ago',
            'inclusive' => true
        ];

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'date_query'     => [ $date_query ],
        ];

        if (!$o['include_updated']) {
            // Ne considérer que la date de publication
            $args['orderby'] = 'date';
        } else {
            // Inclure modifiés récemment
            $args['orderby'] = 'modified';
        }

        $q = new WP_Query($args);
        if (!$q->have_posts()) return '';

        ob_start();
        echo '<div>';
        echo '<h2 style="margin:0 0 16px;">Articles des ' . esc_html($days) . ' derniers jours</h2>';
        echo '<ul style="margin:0;padding-left:18px;">';
        while ($q->have_posts()) {
            $q->the_post();
            $title = get_the_title();
            $perma = get_permalink();
            $excerpt = wp_kses_post(wp_trim_words(get_the_excerpt() ?: strip_tags(get_the_content()), 30));
            $date = get_the_date(get_option('date_format'));
            ?>
            <li style="margin-bottom:12px;">
                <a href="<?php echo esc_url($perma); ?>" style="text-decoration:none;font-weight:bold;"><?php echo esc_html($title); ?></a>
                <div style="font-size:12px;opacity:.75;"><?php echo esc_html($date); ?></div>
                <div style="margin-top:4px;"><?php echo $excerpt; ?></div>
            </li>
            <?php
        }
        echo '</ul></div>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    private function wrap_html($o, $inner) {
        $header = $o['template_header'];
        $footer = $o['template_footer'];
        $site = get_bloginfo('name');
        $home = home_url('/');
        ob_start(); ?>
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta http-equiv="x-ua-compatible" content="ie=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php echo esc_html($o['subject']); ?></title>
            </head>
            <body style="margin:0;padding:0;background:#f6f6f6;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;">
                <tr>
                    <td style="padding:24px; text-align: center;">
                        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="padding:0px;">
                                    <?php if(empty($header)){
                                        ?>
                                    
                                    <div style="font-size:20px;font-weight:700;margin-bottom:8px;">
                                        <a href="<?php echo esc_url($home); ?>" style="text-decoration:none;color:#111;">
                                            <?php echo esc_html($site); ?>
                                        </a>
                                    </div>
                                    <?php } else {
                                    echo wp_kses_post($header);
                                    };
                                    echo $inner;
                                    if ($footer) echo wp_kses_post($footer);?>
                                    <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
                                    <div style="font-size:12px;color:#666;">
                                        Vous recevez cet email car vous êtes inscrit à la newsletter de <?php echo esc_html($site); ?>.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            </body>
            </html>
            <?php
        return ob_get_clean();
    }

}

new Xniris_Newsletter($admin, $client, $api_key_manager);