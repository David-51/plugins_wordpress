<?php

// Sécurité : blocage accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fix miniatures "Derniers articles" : 1 seule image 75x75, pas de doublons
function ddg_fix_latest_posts_thumbs( $block_content, $block ) {
    // on ne touche qu'au bloc Gutenberg "Derniers articles" en frontend
    if ( is_admin() || empty( $block_content ) ) return $block_content;
    if ( empty( $block['blockName'] ) || $block['blockName'] !== 'core/latest-posts' ) return $block_content;

    // Parser le HTML du bloc
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $block_content);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);

    // Pour chaque <li> du bloc
    foreach ( $xpath->query('//li') as $li ) {
        // 1) Supprimer toute image/contener existant ajouté par WP (évite les doublons)
        foreach ( $xpath->query(".//div[contains(@class,'wp-block-latest-posts__featured-image')]", $li) as $imgDiv ) {
            $li->removeChild($imgDiv);
        }

        // 2) Récupérer le lien vers l’article
        $a = $xpath->query('.//a[contains(@class,"wp-block-latest-posts__post-title")]', $li)->item(0);
        if ( ! $a ) continue;

        $href    = $a->getAttribute('href');
        $post_id = url_to_postid( $href );
        if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) continue;

        // 3) Générer la miniature EXACTEMENT en 75x75
        $thumb_html = get_the_post_thumbnail(
            $post_id,
            array(75,75),
            array(
                'class'    => 'attachment-thumbnail size-thumbnail wp-post-image',
                'style'    => 'max-width:75px;max-height:75px;object-fit:cover;',
                'decoding' => 'async',
            )
        );
        if ( ! $thumb_html ) continue;

        // 4) L'envelopper comme WP le fait : <div class="... alignleft">...</div>
        $fragment = $doc->createDocumentFragment();
        $fragment->appendXML(
            '<div class="wp-block-latest-posts__featured-image alignleft">'.$thumb_html.'</div>'
        );

        // 5) Insérer le conteneur image au début du <li>, avant le titre
        $li->insertBefore($fragment, $li->firstChild);
    }

    // Retourner le HTML sans les balises <html><body>
    $body = $doc->getElementsByTagName('body')->item(0);
    $out  = '';
    foreach ($body->childNodes as $child) { $out .= $doc->saveHTML($child); }
    return $out;
}
add_filter( 'render_block', 'ddg_fix_latest_posts_thumbs', 20, 2 );