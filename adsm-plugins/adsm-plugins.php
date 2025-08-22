<?php
/**
 * Plugin Name: ADSM Plugins 
 * Description: Correctifs divers (miniatures, blocs, etc.)
 * Version: 1.0
 * Author: Chazam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Inclure ton fix
require_once __DIR__ . '/inc/fix-latest-posts-thumbnails.php';
require_once __DIR__ . '/inc/adsm-placeholder-image.php';