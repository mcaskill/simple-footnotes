<?php

/**
 * TinyMCE Translations for Simple Footnotes
 *
 * @see wp-includes/js/tinymce/langs/wp-langs.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( '_WP_Editors' ) ) {
    require ABSPATH . WPINC . '/class-wp-editor.php';
}

$messages = [
    'dialogTitle'  => __( 'Insert a footnote', 'simple-footnotes' ),
    'textLabel'    => __( 'Content', 'simple-footnotes' ),
    'previewLabel' => __( 'Show when you hover on the marker', 'simple-footnotes' ),
];

$locale  = _WP_Editors::$mce_locale;
$strings = 'tinyMCE.addI18n( "' . $locale . '.simple-footnotes", ' . json_encode( $messages ) . " );\n";
