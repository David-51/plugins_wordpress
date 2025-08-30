<?php
namespace Xniris;

require_once plugin_dir_path(__FILE__) . 'XnirisBase.php';

class NewsletterOptions extends XnirisBase{
    public function __construct(private readonly SecretKeyManager $secretKeyManager) {
        // Initialize options
    }

    public function get(string $key, $fallback = null) {
        $opts = $this->get_options();
        return $opts[$key] ?? $fallback;
    }

    public function get_label(string $key): string {
        $opts = $this->options();
        return $opts[$key]['label'] ?? '';
    }

    public function get_default(string $key) {
        $opts = $this->options();
        return $opts[$key]['default'] ?? null;
    }

    public function get_options(): array {
        $stored = get_option(self::OPT_KEY, []);
        $defaults = array_map(fn($v) => $v['default'], $this->options());
        return wp_parse_args($stored, $defaults);
    }

    public function update_options($new_options) {
        $allowed = array_keys($this->options());
        $cleaned = array_intersect_key($new_options, array_flip($allowed));
        update_option(self::OPT_KEY, $cleaned);
    }
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
    private function get_field($in, $stored, $defaults, $key, $sanitize, $defaultOverride = null) {
        $value = $in[$key] ?? $stored[$key] ?? ($defaults[$key]['default'] ?? $defaultOverride);
        return call_user_func($sanitize, $value);
    }

    public function sanitize_options($in) {
        global $wp_settings_errors;
        $wp_settings_errors = [];

        $defaults = $this->options();
        $stored   = get_option(self::OPT_KEY, []);

        $out = $stored; // point de départ : garder les anciennes options        

        // Suppression de la clé API
        if (!empty($_POST[self::OPT_KEY . '_delete_api_key']) && $_POST[self::OPT_KEY . '_delete_api_key'] === '1') {
            unset($out['api_key']);
            add_settings_error(self::OPT_KEY, 'api_key_deleted', 'Clé API supprimée.', 'updated');
            return $out;
        }

        // Mise à jour de la clé API (si saisie)
        $api_key_input = trim(sanitize_text_field($in['api_key'] ?? ''));
        if ($api_key_input && $api_key_input !== '********') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Mise à jour de la clé API');
            }
            $out['api_key'] = $this->secretKeyManager->encrypt($api_key_input);

            add_settings_error(self::OPT_KEY, 'api_key_error', 'Clé API enregistrée avec succès.', 'updated');
        }

        // Mise à jour des autres champs
        $out['sender_name'] = $this->get_field($in, $stored, $defaults, 'sender_name', 'sanitize_text_field');
        $out['sender_email'] = $this->get_field($in, $stored, $defaults, 'sender_email', 'sanitize_email');
        $out['list_id'] = $this->get_field($in, $stored, $defaults, 'list_id', 'intval');
        $out['subject'] = $this->get_field($in, $stored, $defaults, 'subject', 'sanitize_text_field');
        $out['dow'] = min(6, max(0, $this->get_field($in, $stored, $defaults, 'dow', 'intval')));
        $out['hour'] = min(23, max(0, $this->get_field($in, $stored, $defaults, 'hour', 'intval')));
        $out['window_days'] = max(1, $this->get_field($in, $stored, $defaults, 'window_days', 'intval'));
        $out['include_updated'] = !empty($in['include_updated']) ? 1 : 0;
        $out['template_header'] = wp_kses_post($in['template_header'] ?? ($stored['template_header'] ?? ''));
        $out['template_footer'] = wp_kses_post($in['template_footer'] ?? ($stored['template_footer'] ?? ''));

    return $out;
    }
}
