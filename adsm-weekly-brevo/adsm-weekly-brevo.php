<?php
/**
 * Plugin Name: ADSM – Newsletter hebdo (Brevo)
 * Description: Envoie chaque semaine un récap des articles (7 derniers jours par défaut) via l’API Brevo. Page d’options incluse.
 * Version: 1.0.0
 * Author: Chazam
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/inc/BrevoClient.php';

class Asso_Weekly_Brevo {
    public const OPT_KEY = 'asso_weekly_brevo_options';
    public const CRON_HOOK = 'asso_weekly_brevo_send_event';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action(self::CRON_HOOK, [$this, 'build_and_send']);
        add_action('update_option_' . self::OPT_KEY, [$this, 'maybe_reschedule'], 10, 3);
        register_activation_hook(__FILE__, [$this, 'on_activate']);
        register_deactivation_hook(__FILE__, [$this, 'on_deactivate']);
        add_action('admin_post_asso_weekly_brevo_send_now', [$this, 'handle_send_now']);        
    }

    /* -------------------- Admin UI -------------------- */

    public function add_menu() {
        add_options_page(
            'Newsletter hebdo (Brevo)',
            'Newsletter hebdo (Brevo)',
            'manage_options',
            'asso-weekly-brevo',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // Enregistrement des options
        register_setting(self::OPT_KEY, self::OPT_KEY, [$this, 'sanitize_options']);
        add_settings_section('main', '', '__return_false', self::OPT_KEY);

        $stored_options = get_option(self::OPT_KEY, []); // tout le tableau d'options

        // Vérification clé API
        // if (empty($stored_options['api_key'])) {
        //     add_settings_error(self::OPT_KEY, 'api_key_error', 'Veuillez configurer votre clé API Brevo.', 'error');
        // } else {
        //     add_settings_error(self::OPT_KEY, 'api_key_info', 'Clé API configurée', 'updated'); 
        //     // On n'affiche pas la clé réelle pour sécurité
        // }

        $fields = [
            'api_key'      => 'Clé API Brevo (v3)',
            'sender_name'  => 'Nom expéditeur',
            'sender_email' => 'Email expéditeur (domaine validé chez Brevo)',
            'list_id'      => 'ID liste Brevo (marketing)',
            'subject'      => 'Sujet de l’email',
            'dow'          => 'Jour d’envoi (0=Dim, 6=Sam)',
            'hour'         => 'Heure (0–23)',
            'window_days'  => 'Période en jours (ex: 7)',
            'include_updated' => 'Inclure articles modifiés (oui/non)',
            'template_header' => 'Header HTML (optionnel)',
            'template_footer' => 'Footer HTML (optionnel)',
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [$this, 'field_cb'],
                self::OPT_KEY,
                'main',
                ['key' => $key, 'label' => $label, 'stored_options' => $stored_options]
            );
        }
    }

    public function field_cb($args) {
        $o = get_option(self::OPT_KEY, $this->defaults_options());
        $k = $args['key'];
        $v = isset($o[$k]) ? $o[$k] : '';

        switch ($k) {
            case 'api_key':
                // Affiche des étoiles si la clé est déjà définie
                $display_value = $v ? '********' : '';
                ?>
                <input type="text" style="width: 100%;" name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>" value="<?php echo esc_attr($display_value); ?>" placeholder="Entrez une nouvelle clé si vous voulez la changer" />
                <p class="description">La clé existante reste inchangée si vous ne modifiez pas ce champ.</p>
                <?php
                break;

            case 'include_updated':
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>" value="1" <?php checked($v, 1); ?> /> Oui</label>
                <?php
                break;

            case 'dow':
                ?>
                <select name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>">
                    <?php
                    $days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
                    for ($i=0; $i<7; $i++) printf('<option value="%d"%s>%s</option>', $i, selected(intval($v), $i, false), esc_html($days[$i]));
                    ?>
                </select>
                <?php
                break;

            case 'hour':
                ?>
                <select name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>">
                    <?php for ($i=0; $i<24; $i++) printf('<option value="%d"%s>%02d:00</option>', $i, selected(intval($v), $i, false), $i); ?>
                </select>
                <?php
                break;

            case 'template_header':
            case 'template_footer':
                ?>
                <textarea rows="4" style="width: 100%;" name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>"><?php echo esc_textarea($v); ?></textarea>
                <?php
                break;

            default:
                ?>
                <input type="text" style="width: 100%;" name="<?php echo esc_attr(self::OPT_KEY . "[$k]"); ?>" value="<?php echo esc_attr($v); ?>" />
                <?php
        }
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        $options = get_option(self::OPT_KEY, $this->defaults_options());
        if (isset($_GET['sent'])) {
            if ($_GET['sent'] == '1') {
                echo '<div class="notice notice-success is-dismissible"><p>Envoi test réussi ! Vérifiez votre boîte email.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Échec de l’envoi test. Vérifiez la configuration API et les logs.</p></div>';
            }
        }
        ?>
        
        <div class="wrap">
            <h1>Newsletter hebdo (Brevo)</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                do_settings_sections(self::OPT_KEY);
                submit_button('Enregistrer et (re)programmer');
                ?>
            </form>

            <hr />
            <h2>Actions</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('asso_weekly_brevo_send_now'); ?>
                <input type="hidden" name="action" value="asso_weekly_brevo_send_now" />
                <?php submit_button('Envoyer maintenant (test)', 'secondary'); ?>
            </form>

            <p><em>WP-Cron doit être déclenché (trafic ou cron système). Recommandé : cron serveur appelant <code>wp-cron.php?doing_wp_cron=1</code>.</em></p>
        </div>
        <?php
    }

    public function sanitize_options($in) {
        $d = $this->defaults_options();
        $out = [];

        // Clé API : ne pas écraser si champ vide ou contient juste des étoiles
        $api_key_input = trim(sanitize_text_field($in['api_key'] ?? ''));
        $stored = get_option(self::OPT_KEY, []);
        if ($api_key_input && $api_key_input !== '********') {
            $out['api_key'] = $api_key_input;
        } else {
            // garde l’ancienne valeur
            $out['api_key'] = $stored['api_key'] ?? '';
        }

        // Autres champs
        $out['sender_name'] = sanitize_text_field($in['sender_name'] ?? '');
        $out['sender_email'] = sanitize_email($in['sender_email'] ?? '');
        $out['list_id'] = intval($in['list_id'] ?? 0);
        $out['subject'] = sanitize_text_field($in['subject'] ?? $d['subject']);
        $out['dow'] = min(6, max(0, intval($in['dow'] ?? $d['dow'])));
        $out['hour'] = min(23, max(0, intval($in['hour'] ?? $d['hour'])));
        $out['window_days'] = max(1, intval($in['window_days'] ?? $d['window_days']));
        $out['include_updated'] = !empty($in['include_updated']) ? 1 : 0;
        $out['template_header'] = wp_kses_post($in['template_header'] ?? '');
        $out['template_footer'] = wp_kses_post($in['template_footer'] ?? '');

        return $out;
    }

    /**
     * Get default options
     * @return array{api_key: string, dow: int, hour: int, include_updated: int, list_id: int, sender_email: mixed, sender_name: string, subject: string, template_footer: string, template_header: string, window_days: int}
     */
    private function defaults_options() {
        return [
            'api_key' => '',
            'sender_name' => get_bloginfo('name'),
            'sender_email' => get_option('admin_email'),
            'list_id' => 0,
            'subject' => 'Les articles de la semaine',
            'dow' => 1,         // Lundi
            'hour' => 9,        // 09:00
            'window_days' => 7,
            'include_updated' => 1,
            'template_header' => '',
            'template_footer' => '',
        ];
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
        $o = get_option(self::OPT_KEY, $this->defaults_options());
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
        check_admin_referer('asso_weekly_brevo_send_now');

        $ok = $this->build_and_send(true); // true = test manuel

        // rediriger avec paramètre ?sent=1 ou ?sent=0
        wp_safe_redirect(add_query_arg('sent', $ok ? '1' : '0', admin_url('options-general.php?page=asso-weekly-brevo')));
        exit;
    }

    /* -------------------- Core: build & send -------------------- */

    public function build_and_send($test = false) {
        $options = get_option(self::OPT_KEY, $this->defaults_options());
        if (empty($options['api_key']) || empty($options['sender_email']) || empty($options['list_id'])) {
            error_log('[Asso Weekly Brevo] Options incomplètes.');
            return false;
        }

        $posts_html = $this->collect_posts_html($options);
        if (!$posts_html) {
            error_log('[Asso Weekly Brevo] Aucun article dans la période.');
            return true; // Rien à envoyer, mais pas une erreur
        }

        $html = $this->wrap_html($options, $posts_html);

        $subject = $options['subject'];

        // Envoi via Marketing API: créer une campagne + sendNow
        $client = new BrevoClient($options['api_key']);
        $resp = $client->create_campaign($options, $subject, $html, $test);
        if(is_array($resp) && isset($resp['id'])) {
            $campaignId = $resp['id'];
        } else {
            error_log('[Asso Weekly Brevo] Réponse inattendue lors de la création de campagne : ' . print_r($resp, true));
            return false;
        }        
        if (!$campaignId) {
            error_log('[Asso Weekly Brevo] Échec création campagne.');
            return false;
        }
        if($test) {
            $sent = $client->send_campaign_now($campaignId, true); // test
        }else{
            $sent = $client->send_campaign_now($campaignId);
        }
        if (!$sent) {
            error_log('[Asso Weekly Brevo] Échec envoi campagne.');
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
                    <td align="center" style="padding:24px;">
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

new Asso_Weekly_Brevo();