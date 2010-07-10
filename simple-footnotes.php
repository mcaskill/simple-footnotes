<?php
/*
 * Plugin Name: Simple Footnotes
 * Plugin URI: http://wordpress.org/extend/plugins/simple-footnotes/
 * Plugin Description: Create simple, elegant footnotes on your site. Use the <code>[ref]</code> shortcode ([ref]My note.[/ref]) and the plugin takes care of the rest.
 * Version: 0.1
 * Author: Andrew Nacin
 * Author URI: http://andrewnacin.com/
 */

class nacin_footnotes {
    var $footnotes = array();
    function nacin_footnotes() {
        add_shortcode( 'ref', array( &$this, 'shortcode' ) );
        add_filter( 'the_content', array( &$this, 'the_content' ), 12 );
    }

    function shortcode( $atts, $content = null ) {
        global $id;
        if ( null === $content )
            return;
        if ( ! isset( $this->footnotes[$id] ) )
            $this->footnotes[$id] = array( 0 => false );
        $this->footnotes[$id][] = $content;
        $note = count( $this->footnotes[$id] ) - 1;
        return ' <a class="simple-footnote" title="' . esc_attr( wp_strip_all_tags( $content ) ) . '" id="return-note-' . $id . '-' . $note . '" href="#note-' . $id . '-' . $note . '"><sup>' . $note . '</sup></a>';
    }

    function the_content( $content ) {
        global $id;
        if ( empty( $this->footnotes[$id] ) )
            return $content;
        $content .= '<div class="simple-footnotes"><p class="notes">Notes:</p><ol>';
        foreach ( array_filter( $this->footnotes[$id] ) as $num => $note )
            $content .= '<li id="note-' . $id . '-' . $num . '">' . do_shortcode( $note ) . ' <a href="#return-note-' . $id . '-' . $num . '">&#8617;</a></li>';
        $content .= '</ol></div>';
        return $content;
    }
}
new nacin_footnotes();