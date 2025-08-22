<?php
// Les fiches pratiques téléchargé via le plugin ACF sont versées dans un dossier sécurisé inaccessible aux utilisateurs déconnectés.

if ( ! defined( 'ABSPATH' ) ) exit;

function fp_secure_upload_dir() {
    $secure_dir = WP_CONTENT_DIR . '/uploads/fiches_pratiques_secure';
    if (!file_exists($secure_dir)) {
        wp_mkdir_p($secure_dir);
    }

    $htaccess = $secure_dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        $rules = "Order deny,allow\nDeny from all\n";
        file_put_contents($htaccess, $rules);
    }
}
add_action('after_switch_theme', 'fp_secure_upload_dir');

add_filter('acf/upload_prefilter/name=pdf_fiche_pratique', 'fp_secure_upload_prefilter');
function fp_secure_upload_prefilter($errors){
    $upload_dir = WP_CONTENT_DIR . '/uploads/fiches_pratiques_secure/';
    
    if( ! file_exists($upload_dir) ){
        mkdir($upload_dir, 0755, true);
    }

    // On force ACF à utiliser ce dossier
    add_filter('upload_dir', function($dir) use ($upload_dir){
        return array(
            'path'   => $upload_dir,
            'url'    => content_url('/uploads/fiches_pratiques_secure'),
            'subdir' => '',
            'basedir'=> $upload_dir,
            'baseurl'=> content_url('/uploads/fiches_pratiques_secure'),
            'error'  => false,
        );
    });

    return $errors;
}