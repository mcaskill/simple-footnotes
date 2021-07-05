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
    'buttonLabel'       => __( 'Insert/edit footnote', 'simple_footnotes' ),
    'dialogTitle'       => __( 'Insert/edit footnote', 'simple_footnotes' ),
    'idLabel'           => __( 'Global note', 'simple_footnotes' ),
    'idPlaceholder'     => __( 'Select a note', 'simple_footnotes' ),
    'idRequired'        => __( 'You must select a note.', 'simple_footnotes' ),
    'orTextLabel'       => __( 'Or, custom note', 'simple_footnotes' ),
    'textLabel'         => __( 'Custom note', 'simple_footnotes' ),
    'previewLabel'      => __( 'Show when you hover on the marker', 'simple_footnotes' ),
    'selectionRequired' => __( 'You must select text to add note.', 'simple_footnotes' ),
];

$locale  = _WP_Editors::$mce_locale;
$strings = 'tinyMCE.addI18n( "' . $locale . '.simple_footnotes", ' . json_encode( $messages ) . " );\n";
