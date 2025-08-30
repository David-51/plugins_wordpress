<?php
namespace Xniris;
if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'XnirisBase.php';

use Xniris\XnirisBase;
class Admin extends XnirisBase{

    private mixed $check_api_key = null; 
    private string $admin_email = '';
    private bool $admin_exists_in_client = false;
    private ?string $stored_api_key = null;

    private array $stored_options = [];
    public function __construct(        
        private readonly BrevoClient $client,
        private readonly SecretKeyManager $secretKeyManager,
        private readonly ApiKeyManager $apiKeyManager,
        private readonly NewsletterOptions $newsLetterOptions
    ) {
        add_action('admin_notices', [$secretKeyManager, 'check_secret_key']);                  
    }

    public function add_menu():void {
        add_options_page(
            'Xniris - Newsletter hebdo (Brevo)',
            'Xniris - Newsletter hebdo (Brevo)',
            'manage_options',
            'xniris-newsletter',
            [$this, 'render_settings_page']
        );        
    }

    public function register_settings() {
        // Enregistrement des options
        register_setting(self::OPT_KEY, self::OPT_KEY, [$this->newsLetterOptions, 'sanitize_options']);
        if($_SERVER['REQUEST_METHOD'] === 'POST') return;
        
        add_settings_section('main', '', '__return_false', self::OPT_KEY);        
        
        $this->check_api_key = $this->apiKeyManager->test_api_key();                        
        
        if($this->check_api_key === true) {
            $this->admin_exists_in_client = $this->current_user_exists_in_client();
        }

        $stored_options = $this->newsLetterOptions->get_options();
        if($this->check_api_key !== true && $this->check_api_key !== 'empty') {
            add_settings_error(self::OPT_KEY, 'api_key_error', 'La clé API n\'est pas valide.');            
        }

        $fields = $this->newsLetterOptions->options();
        foreach ($fields as $key => $value) {            
            // On affiche toujours la clé API
            if ($key !== 'api_key'){
                if($this->check_api_key !== true) continue;
            }            

            add_settings_field(
                $key,
                $value['label'],
                [$this, 'field_callback'],
                self::OPT_KEY,
                'main',
                [
                    'key' => $key,
                    'label' => $value['label'],
                    'stored_options' => $stored_options
                ]
            ); 
        }
    }

    public function field_callback($args) {
        // $options = get_option(self::OPT_KEY, $this->options());
        $options = $this->newsLetterOptions->get_options();
        
        $key = $args['key'];
        $value = isset($options[$key]) ? $options[$key] : '';        

        switch ($key) {
            case 'api_key':
                // Affiche des étoiles si la clé est déjà définie
                $display_value = $value ? '********' : '';
                $placeholder = !$value ? "Entrez une clé API" : "Entrez une nouvelle clé API si vous voulez la changer";
                
                ?>
                <div style="display: flex; gap: 8px; align-items: center; max-width: 100%;">
                    <input type="text" 
                        style="flex: 1; width: 100%;" 
                        name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>" 
                        value="<?php echo esc_attr($display_value); ?>" 
                        placeholder="<?php echo esc_attr($placeholder); ?>" />

                    <?php if ($value): ?>
                        <button type="submit" 
                                name="<?php echo esc_attr(self::OPT_KEY . '_delete_api_key'); ?>" 
                                value="1"
                                class="button button-secondary"
                                onclick="return confirm('Supprimer la clé API ?')">
                            Supprimer
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($value): ?>
                    <p class="description">La clé existante reste inchangée si vous ne modifiez pas ce champ.</p>
                    <?php endif; ?>
                <?php
            break;
            case 'list_id':
                ?>
                <select name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>">
                    <option value="<?php echo esc_attr($value ?? ''); ?>">
                        <?php esc_html_e('Sélectionnez une liste', 'xniris-newsletter'); ?>
                    </option>
                    <?php
                    if($this->check_api_key === true) {
                        $this->client->set_apiKey($this->apiKeyManager->get_decrypted_api_key());
                            $brevo_lists = ($this->client->get_lists());
                            if (!empty($brevo_lists['lists'])) {
                                foreach ($brevo_lists['lists'] as $list) {
                                    printf(
                                        '<option value="%s"%s>%s</option>',
                                        esc_attr($list['id']),
                                        selected($value, $list['id'], false),
                                        esc_html($list['name'])
                                    );
                                }                        
                            }
                        }
                        ?>
                </select>
                <?php
            break;

            case 'include_updated':
                ?>
                <label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>" value="1" <?php checked($value, 1); ?> /> Oui</label>
                <?php
                break;

            case 'dow':
                ?>
                <select name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>">
                    <?php
                    $days = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
                    for ($i=0; $i<7; $i++) 
                        printf('<option value="%d"%s>%s</option>', $i, selected(intval($value), $i, false), esc_html($days[$i]));
                    ?>
                </select>
                <?php
                break;

            case 'hour':
                ?>
                <select name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>">
                    <?php for ($i=0; $i<24; $i++) printf('<option value="%d"%s>%02d:00</option>', $i, selected(intval($value), $i, false), $i); ?>
                </select>
                <?php
                break;

            case 'template_header':
            case 'template_footer':
                ?>
                <textarea rows="4" style="width: 100%;" name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
            case 'sender_email':                
                try{
                ?><select name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>">
                    <option value="<?php echo esc_attr($value ?? ''); ?>">
                        <?php esc_html_e('Sélectionnez un email', 'xniris-newsletter'); ?>
                    </option>
                    <?php
                    if($this->check_api_key === true) {
                        $this->client->set_apiKey($this->apiKeyManager->get_decrypted_api_key());
                            $brevo_senders = ($this->client->get_senders());
                            if (!empty($brevo_senders['senders'])) {
                                foreach ($brevo_senders['senders'] as $sender) {
                                    printf(
                                        '<option value="%s"%s>%s (%s)</option>',
                                        esc_attr($sender['email']),
                                        selected($value, $sender['email'], false),
                                        esc_html($sender['name']),
                                        esc_html($sender['email'])
                                    );
                                }                        
                            }
                        }
                        ?>
                </select>
                    <?php
                    }catch(\Exception $e){
                        error_log('request error : ' .$e->getMessage());                        
                        echo '<div class="notice notice-error"><p>';
                            printf('Une erreur s\'est produite : %s', esc_html($e->getMessage()));
                        echo '</p></div>';
                        add_settings_error(
                        self::OPT_KEY,
                        'sender_email_error',
                        'Erreur lors de la récupération des expéditeurs : ' . $e->getMessage(),
                        'error'
    );
                        // Handle error
                    }
                break;

            default:
                ?>
                <input type="text" style="width: 100%;" name="<?php echo esc_attr(self::OPT_KEY . "[$key]"); ?>" value="<?php echo esc_attr($value); ?>" />
                <?php
        }
    }

    public function render_settings_page() {        
        if (!current_user_can('manage_options')) return;
        $current_user = wp_get_current_user();        
        
        // vérifier que l'email existe dans brevo
        
        if (!$this->admin_exists_in_client) {
            echo '<div class="notice notice-warning"><p>';
            echo '⚠️ Votre email d\'administration WordPress (' . esc_html($current_user->user_email) . ') n\'existe pas dans votre Autorépondeur. ';
            echo 'Ajoutez-le en tant que contact pour pouvoir envoyer des mails de test.';
            echo '</p></div>';
        }
        
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
            <?php if($this->check_api_key === true && $this->admin_exists_in_client): ?>
            <hr />
            
            <h2>Email de Test</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('xniris_newsletter_send_now'); ?>
                <input type="hidden" name="action" value="xniris_newsletter_send_now" />
                <?php submit_button('Envoyer un test maintenant', 'secondary'); ?>
            </form>
            <?php endif; ?>
            <p><em>WP-Cron doit être déclenché (trafic ou cron système). Recommandé : cron serveur appelant <code>wp-cron.php?doing_wp_cron=1</code>.</em></p>
        </div>
        <?php
    }

    private function current_user_exists_in_client(): bool {        
        try {
            $current_user = wp_get_current_user();
            $admin_mail = $current_user->user_email;

            $this->client->set_apiKey($this->apiKeyManager->get_decrypted_api_key());
            $contact = $this->client->get_contact($admin_mail);
            return !empty($contact);
        } catch (\Exception $e) {
            return false;
        }        
    }
}