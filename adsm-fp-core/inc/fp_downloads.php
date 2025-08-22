<?php
// à migrer en plugin
// Créer une règle de réécriture pour /download-fiche/nom-du-fichier.pdf
function fp_add_rewrite_rule() {
    add_rewrite_rule(
        '^download/([^/]+)?',
        'index.php?fp_file=$matches[1]',
        'top'
    );
}
add_action('init', 'fp_add_rewrite_rule');

// Déclarer la query var "fp_file"
function fp_query_vars($vars) {
    $vars[] = 'fp_file';
    return $vars;
}
add_filter('query_vars', 'fp_query_vars');

// Intercepter la requête et lancer le download
function fp_template_redirect() {
    $file = get_query_var('fp_file');
    if ($file) {
        $filename = basename($file); // sécurité
        $filepath = WP_CONTENT_DIR . '/uploads/fiches_pratiques_secure/' . $filename;

        if (!is_user_logged_in() || !file_exists($filepath)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            include(get_404_template());
            exit;
        }

        // Headers download
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}
add_action('template_redirect', 'fp_template_redirect');