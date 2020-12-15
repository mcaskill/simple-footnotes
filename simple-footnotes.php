<?php

/**
 * @wordpress-plugin
 *
 * Plugin Name:  Simple Footnotes
 * Plugin URI:   https://github.com/mcaskill/wp-simple-footnotes
 * Description:  Create simple, elegant footnotes on your site. Use the <code>[ref]</code> shortcode (<code>[ref]My note.[/ref]</code>) and the plugin takes care of the rest. There's also a <a href="options-reading.php">setting</a> that enables you to move the footnotes below your page links, for those who paginate posts.
 * Version:      2.0.0-dev
 * Author:       Chauncey McAskill, Andrew Nacin
 * Text Domain:  simple-footnotes
 * License:      MIT License
 */

namespace McAskill\WordPress\Footnotes;

if ( ! is_blog_installed() ) {
    return;
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

    if ( ! ( $wp_simple_footnotes instanceof Footnotes ) ) {
        $wp_simple_footnotes = new Footnotes();
        $wp_simple_footnotes->boot();
    }

    return $wp_simple_footnotes;
}

simple_footnotes();
