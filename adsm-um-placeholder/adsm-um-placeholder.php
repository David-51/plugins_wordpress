<?php
/**
 * Plugin Name: ADSM - Ultimate Member - Logo Placeholder
 * Description: Ajoute un placeholder {site_logo} configurable via l’admin.
 * Version: 1.0
 * Author: Chazam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Ajout du menu dans l'admin
add_action('admin_menu', function(){
    add_options_page(
        'UM Email Logo',
        'UM Email Logo',
        'manage_options',
        'um-email-logo',
        'um_email_logo_settings_page'
    );
});

// Affichage de la page
function um_email_logo_settings_page(){
    ?>
    <div class="wrap">
        <h1>Logo des emails Ultimate Member</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('um_email_logo_settings');
            do_settings_sections('um-email-logo');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Enregistrement du champ
add_action('admin_init', function(){
    register_setting('um_email_logo_settings', 'um_email_logo_url');

    add_settings_section('um_email_logo_section', '', null, 'um-email-logo');

    add_settings_field(
        'um_email_logo_url',
        'URL du logo',
        function(){
            $value = get_option('um_email_logo_url', '');
            echo '<input type="text" name="um_email_logo_url" value="'.esc_attr($value).'" class="regular-text" />';
        },
        'um-email-logo',
        'um_email_logo_section'
    );
});

// Ajout du placeholder {site_logo}
add_filter('um_template_tags_patterns_hook', function($placeholders){
    $placeholders[] = '{site_logo}';
    return $placeholders;
});

// Remplacement du placeholder
add_filter('um_template_tags_replaces_hook', function($replacements){
    $logo_url = get_option('um_email_logo_url');
    if(!$logo_url){
        $logo_id = get_theme_mod('custom_logo');
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : get_site_icon_url(150);
    }
    $logo_html = '<img src="'.$logo_url.'" alt="'.get_bloginfo('name').'" style="max-width:150px;height:auto;margin:0 auto;" />';
    $replacements[] = $logo_html;
    return $replacements;
});