<?php

namespace McAskill\WordPress\Footnotes;

/*
 * Plugin Name: Simple Footnotes
 * Plugin URI: https://github.com/mcaskill/wp-simple-footnotes
 * Description: Create simple, elegant footnotes on your site. Use the <code>[ref]</code> shortcode (<code>[ref]My note.[/ref]</code>) and the plugin takes care of the rest. There's also a <a href="options-reading.php">setting</a> that enables you to move the footnotes below your page links, for those who paginate posts.
 * Version: 1.0
 * Author: Chauncey McAskill, Andrew Nacin
 * Text Domain: simple-footnotes
 */

if ( ! defined('ABSPATH') ) {
    exit; // Don't access directly
}

require_once __DIR__ . '/includes/class-footnotes.php';

/**
 * Get the main instance of Simple Footnotes.
 *
 * @return Footnotes
 */
function simple_footnotes()
{
    global $wp_simple_footnotes;

    if ( ! isset( $wp_simple_footnotes ) ) {
        $wp_simple_footnotes = new Footnotes();
        $wp_simple_footnotes->boot();
    }

    return $wp_simple_footnotes;

}

simple_footnotes();
