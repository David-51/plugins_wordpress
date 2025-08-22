<?php
/**
 * Plugin Name: ADSM Event Views
 * Description: Bloc Gutenberg pour afficher les événements de The Events Calendar avec toggle Liste/Calendrier et code d’embed personnalisable.
 * Author: Chazam
 * Version: 1.2
 */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once plugin_dir_path( __FILE__ ) . 'adsm-event-calendar.php';
// Enregistrement du bloc + assets éditeur
function adsm_event_views_register_block() {
    add_action('admin_notices', function() {
    if ( ! function_exists('adsm_get_placeholder_image') ) {
        ?>
        <div class="notice notice-warning">
            <p><strong>⚠ Attention :</strong> ADSM Event Views, le plugin <em>ADSM Common Placeholders</em> n’est pas actif. 
            Certaines fonctionnalités peuvent ne pas fonctionner correctement.</p>
        </div>
        <?php
    }
    });
    add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'dashicons' );
});

    // JS éditeur
    wp_register_script(
        'adsm-event-views-block',
        plugins_url( 'index.js', __FILE__ ),
        ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-block-editor'],
        filemtime( plugin_dir_path( __FILE__ ) . 'index.js' )
    );

    // CSS front
    wp_register_style(
        'adsm-event-views-style',
        plugins_url( 'style.css', __FILE__ ),
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'style.css' )
    );

    // CSS éditeur
    wp_register_style(
        'adsm-event-views-editor-style',
        plugins_url( 'editor.css', __FILE__ ),
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'editor.css' )
    );

    register_block_type( __DIR__, [
        'editor_script'   => 'adsm-event-views-block',
        'style'           => 'adsm-event-views-style',
        'editor_style'    => 'adsm-event-views-editor-style',
        'render_callback' => 'adsm_event_views_render',
        'attributes'      => [
            'defaultView' => [
                'type'    => 'string',
                'default' => 'list',
            ],
            'numberOfEvents' => [
                'type'    => 'number',
                'default' => 5,
            ],
            'embedCode' => [
                'type'    => 'string',
                'default' => '',
            ],
        ],
    ] );
}
add_action( 'init', 'adsm_event_views_register_block' );

// JS front (toggle)
function adsm_event_views_enqueue_front_assets() {
    wp_enqueue_script(
        'adsm-event-views-frontend',
        plugins_url( 'views.js', __FILE__ ),
        [],
        filemtime( plugin_dir_path( __FILE__ ) . 'views.js' ),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'adsm_event_views_enqueue_front_assets' );

// Rendu serveur
function adsm_event_views_render( $attributes ) {
    if ( ! function_exists( 'tribe_get_events' ) ) {
        return '<p>Le plugin The Events Calendar est requis.</p>';
    }
    
    if(isset($_GET['calendar']) && intval($_GET['calendar']) && $_GET['calendar'] === '1') {
        $default_view = 'calendar';
    }else{
        $default_view = $attributes['defaultView'] ?? 'list';
    }
    $number       = intval( $attributes['numberOfEvents'] ?? 5 );
    $embed_code   = $attributes['embedCode'] ?? '';
    if ( function_exists( 'adsm_get_placeholder_image' ) ) {
        $placeholder_image = adsm_get_placeholder_image();
    }else{
        $placeholder_image = '';
    }

    // Liste des événements
    $events = tribe_get_events( [
        'posts_per_page' => $number,
        'start_date'     => current_time( 'Y-m-d H:i:s' ),
        'orderby'        => 'event_date',
        'order'          => 'ASC',
    ] );

    // Autoriser <iframe> et <style scoped> dans l’embed
    $allowed = [
        'iframe' => [
            'src'                  => true,
            'width'                => true,
            'height'               => true,
            'style'                => true,
            'frameborder'          => true,
            'allow'                => true,
            'allowfullscreen'      => true,
            'loading'              => true,
            'referrerpolicy'       => true,
            'data-tec-events-ece-iframe' => true,
        ],
        'style' => [
            'scoped' => true,
        ],
    ];
    $embed_html = $embed_code ? wp_kses( $embed_code, $allowed ) : '';

    // État initial
    $list_hidden     = ( $default_view === 'calendar' && $embed_html ) ? ' d-none' : '';
    $calendar_hidden = ( $default_view !== 'calendar' || ! $embed_html ) ? ' d-none' : '';
    $btn_list_active = ( $default_view !== 'calendar' || ! $embed_html ) ? ' active' : '';
    $btn_cal_active  = ( $default_view === 'calendar' && $embed_html ) ? ' active' : '';
    

    ob_start(); ?>
    <div class="adsm-event-views" data-default-view="<?php echo esc_attr( $default_view ); ?>" data-has-embed="<?php echo $embed_html ? '1' : '0'; ?>">
        <div class="adsm-views-toggle">
            <button class="adsm-toggle-btn<?php echo $btn_list_active; ?>" data-view="list">Liste</button>
            <button class="adsm-toggle-btn<?php echo $btn_cal_active; ?>" data-view="calendar" <?php echo $embed_html ? '' : 'disabled'; ?>>Calendrier</button>
        </div>

        <div class="adsm-event-list<?php echo $list_hidden; ?>">
            <?php if ( ! empty( $events ) ) : ?>
                <?php foreach ( $events as $event ) :
                    $permalink = get_permalink( $event );
                    $title     = esc_html( get_the_title( $event ) );
                    $start     = tribe_get_start_date( $event, false, 'j F Y' );
                    $end       = tribe_get_end_date( $event, false, 'j F Y' );
                    $thumbnail = get_the_post_thumbnail( $event, 'thumbnail', ['class' => 'adsm-event-thumb'] );
                    $city      = tribe_get_city( $event );
                ?>
                <a class="adsm-event-item" href="<?php echo esc_url( $permalink ); ?>">                                        
                    <div class="adsm-event-thumb-wrap d-none d-sm-block">
                        <?php if($thumbnail){
                            echo $thumbnail;
                        }else{
                            echo '<img class="adsm-event-thumb" src="' . esc_url( $placeholder_image ) . '" alt="Aperçu de l\'événement" />';
                        }?>
                    </div>
                    <div class="adsm-event-info">
                        <h2 class="adsm-event-title"><?php echo $title; ?></h2>
                        <?php if ( $city ) : ?>
                            <p class="adsm-event-venue"><span class="dashicons dashicons-location"></span>&ensp;<?php echo esc_html( $city ); ?></p>
                        <?php endif; ?>
                        <?php if ( $start && $end && $start !== $end ) : ?>
                            <p class="adsm-event-date"><span class="dashicons dashicons-calendar"></span>&ensp;<?php echo $start . ' - ' . $end; ?></p>
                        <?php else : ?>
                            <p class="adsm-event-date"><span class="dashicons dashicons-calendar"></span>&ensp;<?php echo $start; ?></p>
                        <?php endif; ?>
                        <p class="text-ghost mb-0">Cliquez pour plus d'infos</p>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else : ?>
                <p>Aucun événement à venir.</p>
            <?php endif; ?>
        </div>

        <div class="adsm-event-calendar<?php echo $calendar_hidden; ?>">
            
            <?php echo adsm_render_calendar_grid(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
