<?php
if (!defined('ABSPATH')) exit;

class Admin {    

    public function __construct(
        private readonly string $OPT_KEY,
        private readonly BrevoClient $client,
        private readonly SecretKeyManager $secretKeyManager
    ) {
        add_action('admin_notices', [$secretKeyManager, 'check_secret_key']);
        // Actions et filtres
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
        register_setting($this->OPT_KEY, $this->OPT_KEY, [$this, 'sanitize_options']);
        add_settings_section('main', '', '__return_false', $this->OPT_KEY);
        if($_SERVER['REQUEST_METHOD'] === 'POST') return;

        $stored_options = get_option($this->OPT_KEY, []); // tout le tableau d'options
        $fields = $this->options();
        
        $stored_api_key = isset($stored_options['api_key']) && !empty($stored_options['api_key']) ? $stored_options['api_key'] : '';
        $is_valid_api_key = false;
        
        if(!empty($stored_api_key)){
            if(!$this->is_valid_api_key($stored_api_key)){
                add_settings_error($this->OPT_KEY, 'api_key_error', 'La clé API n\'est pas valide.');
            }else{                
                $is_valid_api_key = true;
            }            
        }
    
        foreach ($fields as $key => $value) {            
            // On affiche toujours la clé API
            if ($key !== 'api_key') {
                if(!$stored_api_key) continue;
                if(!$is_valid_api_key) continue;
            }            

            add_settings_field(
                $key,
                $value['label'],
                [$this, 'field_callback'],
                $this->OPT_KEY,
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
        $options = get_option($this->OPT_KEY, $this->options());
        $key = $args['key'];
        $value = isset($options[$key]) ? $options[$key] : '';        

        switch ($key) {
            case 'api_key':
                // Affiche des étoiles si la clé est déjà définie
                $display_value = $value ? '********' : '';
                if(!$value) {
                    $placeholder = "Entrez une clé API";
                }else{
                    $placeholder = "Entrez une nouvelle clé API si vous voulez la changer";
                }
                ?>
                <div style="display: flex; gap: 8px; align-items: center; max-width: 100%;">
                    <input type="text" 
                        style="flex: 1; width: 100%;" 
                        name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>" 
                        value="<?php echo esc_attr($display_value); ?>" 
                        placeholder="<?php echo esc_attr($placeholder); ?>" />

                    <?php if ($value): ?>
                        <button type="submit" 
                                name="<?php echo esc_attr($this->OPT_KEY . '_delete_api_key'); ?>" 
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
                <select name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>">
                    <option value="<?php echo esc_attr($value ?? ''); ?>">
                        <?php esc_html_e('Sélectionnez une liste', 'xniris-newsletter'); ?>
                    </option>
                    <?php
                    if(!empty($options['api_key'])) {
                        $this->client->set_apiKey($this->decrypt_api_key_safe($options['api_key']));
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
                <label><input type="checkbox" name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>" value="1" <?php checked($value, 1); ?> /> Oui</label>
                <?php
                break;

            case 'dow':
                ?>
                <select name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>">
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
                <select name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>">
                    <?php for ($i=0; $i<24; $i++) printf('<option value="%d"%s>%02d:00</option>', $i, selected(intval($value), $i, false), $i); ?>
                </select>
                <?php
                break;

            case 'template_header':
            case 'template_footer':
                ?>
                <textarea rows="4" style="width: 100%;" name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
            case 'sender_email':                
                try{
                ?><select name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>">
                    <option value="<?php echo esc_attr($value ?? ''); ?>">
                        <?php esc_html_e('Sélectionnez un email', 'xniris-newsletter'); ?>
                    </option>
                    <?php
                    if($options['api_key']) {
                        $this->client->set_apiKey($this->decrypt_api_key_safe($options['api_key']));
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
                        $this->OPT_KEY,
                        'sender_email_error',
                        'Erreur lors de la récupération des expéditeurs : ' . $e->getMessage(),
                        'error'
    );
                        // Handle error
                    }
                break;

            default:
                ?>
                <input type="text" style="width: 100%;" name="<?php echo esc_attr($this->OPT_KEY . "[$key]"); ?>" value="<?php echo esc_attr($value); ?>" />
                <?php
        }
    }

    public function render_settings_page() {        
        if (!current_user_can('manage_options')) return;
        $options = get_option($this->OPT_KEY, $this->options());
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
                settings_fields($this->OPT_KEY);
                do_settings_sections($this->OPT_KEY);
                submit_button('Enregistrer et (re)programmer');
                ?>
            </form>

            <hr />
            <h2>Email de Test</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('xniris_newsletter_send_now'); ?>
                <input type="hidden" name="action" value="xniris_newsletter_send_now" />
                <?php submit_button('Envoyer un test maintenant', 'secondary'); ?>
            </form>

            <p><em>WP-Cron doit être déclenché (trafic ou cron système). Recommandé : cron serveur appelant <code>wp-cron.php?doing_wp_cron=1</code>.</em></p>
        </div>
        <?php
    }

    public function sanitize_options($in) {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $defaults = $this->options();
        $stored   = get_option($this->OPT_KEY, []);

        $out = $stored; // point de départ : garder les anciennes options        

        // Suppression de la clé API
        if (!empty($_POST[$this->OPT_KEY . '_delete_api_key']) && $_POST[$this->OPT_KEY . '_delete_api_key'] === '1') {
            unset($out['api_key']);
            add_settings_error($this->OPT_KEY, 'api_key_deleted', 'Clé API supprimée.', 'updated');
            return $out;
        }

        // Mise à jour de la clé API (si saisie)
        $api_key_input = trim(sanitize_text_field($in['api_key'] ?? ''));
        if ($api_key_input && $api_key_input !== '********') {
            error_log('Mise à jour de la clé API');
            $out['api_key'] = $this->secretKeyManager->encrypt($api_key_input);
            
            add_settings_error($this->OPT_KEY, 'api_key_error', 'Clé API enregistrée avec succès.', 'updated');
        }

        // Mise à jour des autres champs
        $out['sender_name']     = sanitize_text_field($in['sender_name']    ?? ($stored['sender_name']    ?? $defaults['sender_name']['default']));
        $out['sender_email']    = sanitize_email($in['sender_email']        ?? ($stored['sender_email']   ?? $defaults['sender_email']['default']));
        $out['list_id']         = intval($in['list_id']                     ?? ($stored['list_id']        ?? $defaults['list_id']['default']));
        $out['subject']         = sanitize_text_field($in['subject']        ?? ($stored['subject']        ?? $defaults['subject']['default']));
        $out['dow']             = min(6, max(0, intval($in['dow']          ?? ($stored['dow']            ?? $defaults['dow']['default']))));
        $out['hour']            = min(23, max(0, intval($in['hour']        ?? ($stored['hour']           ?? $defaults['hour']['default']))));
        $out['window_days']     = max(1, intval($in['window_days']          ?? ($stored['window_days']    ?? $defaults['window_days']['default'])));
        $out['include_updated'] = !empty($in['include_updated']) ? 1 : 0;
        $out['template_header'] = wp_kses_post($in['template_header']       ?? ($stored['template_header'] ?? ''));
        $out['template_footer'] = wp_kses_post($in['template_footer']       ?? ($stored['template_footer'] ?? ''));

    return $out;
}

    /**
     * Get default options
     * @return array{api_key: string, dow: int, hour: int, include_updated: int, list_id: int, sender_email: mixed, sender_name: string, subject: string, template_footer: string, template_header: string, window_days: int}
     */
    public function options(): array {
        return [
            'api_key'         => [
                'label' => 'Clé API Brevo (v3)',                     
                'default' => ''],
            'sender_name'     => [
                'label' => 'Nom expéditeur',                         
                'default' => get_bloginfo('name')],
            'sender_email'    => [
                'label' => 'Email expéditeur (domaine validé)',      
                'default' => get_option('admin_email')],
            'list_id'         => [
                'label' => 'Liste d\'envoie',
                'default' => 0],            
            'subject'         => [
                'label' => 'Sujet de l’email',                       
                'default' => 'Les articles de la semaine'],
            'dow'             => [
                'label' => 'Jour d’envoi',            
                'default' => 1],  // Lundi
            'hour'            => [
                'label' => 'Heure',                           
                'default' => 9],  // 09:00
            'window_days'     => [
                'label' => 'Période en jours (ex: 7)',               
                'default' => 7],
            'include_updated' => [
                'label' => 'Inclure articles modifiés (oui/non)',    
                'default' => 1],
            'template_header' => [
                'label' => 'Header HTML (optionnel)',
                'default' => ''],
            'template_footer' => [
                'label' => 'Footer HTML (optionnel)',
                'default' => ''],
        ];        
    }
    public function decrypt_api_key_safe(string $encrypted): string {
        try {
            return $this->secretKeyManager->decrypt($encrypted);
        } catch (\Exception $e) {
            error_log('Erreur déchiffrement clé API : ' . $e->getMessage());
            return '';
        }        
    }
    private function is_valid_api_key(string $api_key): bool {
        try{
            $safe_key = $this->decrypt_api_key_safe($api_key);
            $this->client->set_apiKey($safe_key);
            $this->client->get_senders();            
        }catch(\Exception $e){
            return false;
        }        
        return true;
    }
}