<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Affiche le nom de la catégorie pour categorie-fiche
function fp_categorie_titre_shortcode() {
    if ( is_tax( 'categorie-fiche' ) ) {
        $term = get_queried_object();
        if ( $term && ! is_wp_error( $term ) ) {
            return '<h1 class="text-center">' . esc_html( $term->name ) . "</h1>";
        }
    }
    return '';
}
add_shortcode( 'fp_categorie_titre', 'fp_categorie_titre_shortcode' );