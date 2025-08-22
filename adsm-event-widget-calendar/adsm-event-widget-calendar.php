<?php
/**
 * Plugin Name: ADSM Event Widget Calendar
 * Description: Bloc pour afficher les prochains événements de The Events Calendar.
 * Author: Chazam
 * Version: 1.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function adsm_event_calendar_register_block() {
    add_action('admin_notices', function() {
    if ( ! function_exists('adsm_get_placeholder_image') ) {
        ?>
        <div class="notice notice-warning">
            <p><strong>⚠ Attention :</strong> ADSM Event Calendar, le plugin <em>ADSM Common Placeholders</em> n’est pas actif. 
            Certaines fonctionnalités peuvent ne pas fonctionner correctement.</p>
        </div>
        <?php
    }
    });
    wp_register_script(
        'adsm-event-widget-calendar-block',
        plugins_url( 'block.js', __FILE__ ),
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components'],
        filemtime( plugin_dir_path( __FILE__ ) . 'block.js' )
    );

    wp_register_style(
        'adsm-event-widget-calendar-style',
        plugins_url( 'style.css', __FILE__ ),
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'style.css' )
    );

    register_block_type( __DIR__, [
        'editor_script'   => 'adsm-event-widget-calendar-block',
        'style'           => 'adsm-event-widget-calendar-style',
        'render_callback' => 'adsm_event_calendar_render',
        'attributes'      => [
            'numberOfEvents' => [
                'type'    => 'integer',
                'default' => 5]
            ,
        ],
    ] );
}
add_action( 'init', 'adsm_event_calendar_register_block' );

function adsm_event_calendar_render( $attributes ) {
    
    if ( ! function_exists( 'tribe_get_events' ) ) {
        return '<p>Le plugin The Events Calendar est requis.</p>';
    }

    $number = isset( $attributes['numberOfEvents'] ) ? intval( $attributes['numberOfEvents'] ) : 5;
    $number = max( 1, min( 10, $number ) ); // sécurité : min 1, max 10
    if ( function_exists( 'adsm_get_placeholder_image' ) ) {
        $image_placeholder = adsm_get_placeholder_image(75);
    }else{
        $image_placeholder = '';
    }

    $events = tribe_get_events( [
        'posts_per_page' => $number,
        'start_date'     => current_time( 'Y-m-d H:i:s' ),
        'orderby'        => 'event_date',
        'order'          => 'ASC',
    ] );

    if ( empty( $events ) ) {
        return '<p>Aucun événement à venir.</p>';
    }

    ob_start();
    echo '<div class="adsm-event-widget-list">';
    foreach ( $events as $event ) {
        $permalink = get_permalink( $event );
        $title     = esc_html( get_the_title( $event ) );
        $start     = tribe_get_start_date( $event, false, 'j F Y' );
        $end       = tribe_get_end_date( $event, false, 'j F Y' );
        $thumbnail = get_the_post_thumbnail( $event, 'thumbnail', ['class' => 'adsm-event-widget-thumb'] );

        echo '<a class="adsm-event-widget-item" href="' . esc_url( $permalink ) . '">';
        if ( $thumbnail ) {
            echo '<div class="adsm-event-widget-thumb-wrap">' . $thumbnail . '</div>';
        } else {
            // Utiliser une image de remplacement si aucune miniature n'est disponible
            echo '<div class="adsm-event-widget-thumb-wrap"><img class="adsm-event-widget-thumb" src="' . $image_placeholder . '" width="75px" height="75px" alt="Aperçu de l\'événement" /></div>';
        }
        echo '<div class="adsm-event-widget-info">';
        echo '<h4 class="adsm-event-widget-title">' . $title . '</h4>';
        if ( $start && $end && $start !== $end ) {
            echo '<p class="adsm-event-widget-date">' . $start . ' - ' . $end . '</p>';
        } else {
            echo '<p class="adsm-event-widget-date">' . $start . '</p>';
        }
        echo '</div>';
        echo '</a>';
    }
    echo '</div>';
    return ob_get_clean();
}
