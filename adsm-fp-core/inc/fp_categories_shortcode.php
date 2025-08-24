<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue CSS pour le bloc fiches pratiques
function fp_enqueue_adsm_fp_categories_block_assets() {    
    $css_file = plugin_dir_url( __FILE__ ) . '../css/fp_categories.css';
    if(file_exists($css_file)) {
        wp_enqueue_style(
            'fp-categories',
            $css_file,
            [], // dépendances
            filemtime( $css_file ));      
    } else {
        error_log("Le fichier CSS fp_categories.css est introuvable à l'emplacement : " . $css_file);
        return;
    }
    
}
add_action( 'enqueue_block_assets', 'fp_enqueue_adsm_fp_categories_block_assets' );
// Shortcode [fiches_pratiques_categories]
function fp_categories_shortcode() {
    $terms = get_terms([
        'taxonomy' => 'categorie-fiche',
        'hide_empty' => false,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return '<p>Aucune catégorie trouvée.</p>';
    }

    ob_start();
    echo '<div class="fp-categories-grid">';
    foreach ($terms as $term) {
        $term_link = get_term_link($term);
        echo '<a class="fp-category-btn" href="' . esc_url($term_link) . '">' . esc_html($term->name) . '</a>';
    }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('fiches_pratiques_categories', 'fp_categories_shortcode');