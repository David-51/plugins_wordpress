<?php
// Sécurité
if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue CSS pour le bloc fiches pratiques
function fp_enqueue_table_block_assets() {        
    $css_url = plugin_dir_url( __FILE__ ) . '../css/fp_table.css';
    $css_path = plugin_dir_path( __FILE__ ) . '../css/fp_table.css';

     // Vérifie que le fichier CSS existe
    if(!file_exists($css_path)){
        error_log("Le fichier CSS fp_table.css est introuvable à l'emplacement : " . $css_path);
        return;
    }
    wp_enqueue_style(
        'adsm-fp-table',
        $css_url,
        [], // dépendances
        filemtime( $css_path ) // version basée sur la date de modification
    );
}
add_action( 'enqueue_block_assets', 'fp_enqueue_table_block_assets' );
// Enregistrer le bloc dynamique [fiches_pratiques_table]
function fp_register_table_block() {
    register_block_type( 'fp/fiches-table', array(
        'render_callback' => 'fp_render_fiches_table',
    ) );
}
add_action( 'init', 'fp_register_table_block' );

// Callback pour afficher le tableau
function fp_render_fiches_table( $attributes ) {
    if ( ! is_tax( 'categorie-fiche' ) ) {
        return ''; // Rien si on n'est pas sur une catégorie
    }

    $term = get_queried_object();

    $args = array(
        'post_type' => 'fiche-pratique',
        'tax_query' => array(
            array(
                'taxonomy' => 'categorie-fiche',
                'field'    => 'slug',
                'terms'    => $term->slug,
            ),
        ),
        'posts_per_page' => -1,
    );
    $query = new WP_Query( $args );
	$description = get_field('description_fiche_pratique');
	$pdf_url = get_field('pdf_fiche_pratique'); 

    ob_start();
    ?>
    <div class="container mb-2">
		<?php if(!is_user_logged_in()){ ?>
		<p class="text-center text-danger">
				Pour télécharger les fiches pratiques, vous devez être membre de l'ADSM51
		</p><?php } ?>

    <?php if ( have_posts() ) : ?>
        <table class="table-fiches">            
            <tbody>
                <?php while ( have_posts() ) : the_post(); ?>
                    <tr>
						<td class="btn-center table-btn-cell table-noborder-right">
							 <?php 
							
							if ( $pdf_url ) : 
								if ( is_user_logged_in() ) : 
									$filename = basename($pdf_url); ?>
									
							<a href="/download/<?php echo urlencode($filename); ?>" target="_blank" class="btn-download">								
								<svg class="icon-download" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 640 640">
									<path d="M352 96C352 78.3 337.7 64 320 64C302.3 64 288 78.3 288 96L288 306.7L246.6 265.3C234.1 252.8 213.8 252.8 201.3 265.3C188.8 277.8 188.8 298.1 201.3 310.6L297.3 406.6C309.8 419.1 330.1 419.1 342.6 406.6L438.6 310.6C451.1 298.1 451.1 277.8 438.6 265.3C426.1 252.8 405.8 252.8 393.3 265.3L352 306.7L352 96zM160 384C124.7 384 96 412.7 96 448L96 480C96 515.3 124.7 544 160 544L480 544C515.3 544 544 515.3 544 480L544 448C544 412.7 515.3 384 480 384L433.1 384L376.5 440.6C345.3 471.8 294.6 471.8 263.4 440.6L206.9 384L160 384zM464 440C477.3 440 488 450.7 488 464C488 477.3 477.3 488 464 488C450.7 488 440 477.3 440 464C440 450.7 450.7 440 464 440z"/>
								</svg>
							</a>
								<?php else: ?>
									<span title="Connectez-vous pour télécharger">🔒</span>
								<?php endif; 
							else: ?>
								—
							<?php endif; ?>                            
                        </td>
                        <td class="">
							<h2 class="fp-title">
							<?php echo esc_html(get_the_title()); ?>
								
							</h2>
							<p style="">															
							<?php 
								/* the_excerpt(); */ 
							?> 
							<?php 
							if($description){
								echo esc_html($description);
							}else{
								echo "Aucune information";
							}							
							?>
								</p>
							<p class="text-ghost text-end">
								Ajoutée le <?php echo esc_html(get_the_date('d/m/Y')) ?> 
							</p>
						</td>

                        
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p class="text-center">Aucune fiche pratique dans cette catégorie.</p>
    <?php endif; ?>
</div>
    <?php
    return ob_get_clean();
}