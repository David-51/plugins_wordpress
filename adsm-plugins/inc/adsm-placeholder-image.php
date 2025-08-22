<?php

/**
 * Summary of adsm_get_placeholder_image
 * @param mixed $int Image Size
 * @return string
 */
function adsm_get_placeholder_image($int = "200") {
    $filename = 'calendar_placeholder_' . $int . '.webp'; // nom de ton image
    $plugin_path = plugin_dir_path(__FILE__) . '/../images/' . $filename; // chemin côté plugin

    if (!file_exists($plugin_path)) {
        $plugin_path = plugin_dir_path(__FILE__) . 'calendar_placeholder_200.webp'; // chemin par défaut si l'image spécifique n'existe pas
    }

    $upload_dir = wp_upload_dir(); // infos sur le dossier uploads
    $upload_path = $upload_dir['basedir'] . '/' . $filename; // chemin final dans uploads
    $upload_url  = $upload_dir['baseurl'] . '/' . $filename; // URL pour l'affichage

    // Si le fichier n'existe pas encore dans uploads, on le copie depuis le plugin
    if (!file_exists($upload_path) && file_exists($plugin_path)) {
        copy($plugin_path, $upload_path);
    }

    // Retourne l'URL de l'image
    return $upload_url;
}