<?php
/**
 * Plugin Name: ADSM FP Core
 * Description: Regroupe toutes les fonctionnalités liées aux Fiches Pratiques : shortcodes, bloc Gutenberg, téléchargement sécurisé et sécurité des fichiers. 
 * Version: 1.0.0
 * Author: Chazam
 * Text Domain: adsm-fp-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Définir le chemin du dossier /inc
define( 'ADSM_FP_CORE_INC', plugin_dir_path( __FILE__ ) . 'inc/' );

// Inclure tous les fichiers
$fp_files = [
    'fp_categorie_titre_shortcode.php',
    'fp_categories_shortcode.php',
    'fp_downloads.php',
    'fp_security_folder.php',
    'fp_table.php',
];

foreach ( $fp_files as $file ) {
    $filepath = ADSM_FP_CORE_INC . $file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
}