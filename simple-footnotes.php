<?php
/*
 * Plugin Name: Simple Footnotes
 * Plugin URI: http://wordpress.org/extend/plugins/simple-footnotes/
 * Plugin Description: Create simple, elegant footnotes on your site. Use the <code>[ref]</code> shortcode ([ref]My note.[/ref]) and the plugin takes care of the rest. There's also a <a href="options-reading.php">setting</a> that enables you to move the footnotes below your page links, for those who paginate posts.
 * Version: 0.4
 * Author: Andrew Nacin
 * Author URI: http://andrewnacin.com/
 */

class nacin_footnotes {

	// Stores footnotes once crawled.
	var $footnotes = array();

	// Stores post and comment IDs that have already been crawled for footnotes.
	var $shortcodes_collected = array();

	// Holds option data.
	var $option_name = 'simple_footnotes';
	var $options = array();
	var $placement = 'content';

	// DB version, for schema upgrades.
	var $db_version = 1;

	function nacin_footnotes() {
		add_action( 'init', array( &$this, 'init' ) );
	}

	function init() {
		$this->footnotes = $this->shortcodes_collected = array( 'post' => array(), 'comment' => array() );

		//register shortcode
		add_shortcode( 'ref', array( &$this, 'shortcode' ) );

		// Fetch and set up options.
		$this->options = get_option( 'simple_footnotes' );
		if ( ! empty( $this->options ) && ! empty( $this->options['placement'] ) )
			$this->placement = $this->options['placement'];

		if ( 'page_links' == $this->placement && !is_feed() )
			add_filter( 'wp_link_pages_args', array( &$this, 'wp_link_pages_args' ) );
		else
			add_filter( 'the_content', array( &$this, 'the_content' ), 12 );

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		    add_filter( 'comment_text', array( &$this, 'do_shortcode_comments' ), 11 );;
			//????? add_filter( 'comment_text', array( &$this, 'comment_text' ), 12 );
		}

		if ( ! is_admin() )
			return;
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
	}

	function admin_init() {
		if ( false === $this->options || ! isset( $this->options['db_version'] ) || $this->options['db_version'] < $this->db_version ) {
			if ( ! is_array( $this->options ) )
				$this->options = array();
			$current_db_version = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;
			$this->upgrade( $current_db_version );
			$this->options['db_version'] = $this->db_version;
			update_option( $this->option_name, $this->options );
		}

		add_settings_field( 'simple_footnotes_placement', 'Footnotes placement', array( &$this, 'settings_field_cb' ), 'reading' );
		register_setting( 'reading', 'simple_footnotes', array( &$this, 'register_setting_cb' ) );
	}

	function register_setting_cb( $input ) {
		$output = array( 'db_version' => $this->db_version, 'placement' => 'content' );
		if ( ! empty( $input['placement'] ) && 'page_links' == $input['placement'] )
			$output['placement'] = 'page_links';
		return $output;
	}

	function settings_field_cb() {
		$fields = array(
			'content' => 'Below content',
			'page_links' => 'Below page links',
		);
		foreach ( $fields as $field => $label ) {
			echo '<label><input type="radio" name="simple_footnotes[placement]" value="' . $field . '"' . checked( $this->placement, $field, false ) . '> ' . $label . '</label><br/>';
		}
	}

	function upgrade( $current_db_version ) {
		if ( $current_db_version < 1 )
			$this->options['placement'] = 'content';
	}

	function shortcode( $atts, $content = null ) {
		if ( null === $content )
			return;

		if ( current_filter() == 'comment_text' ) {
			$type = 'comment';
			$id = $GLOBALS['comment']->comment_ID;
		} else {
			$type = 'post';
			$id = $GLOBALS['id'];
		}

		if ( ! isset( $this->footnotes[ $type ][ $id ] ) )
			$this->footnotes[ $type ][ $id ] = array( 0 => false );
		// Only collect shortcodes once, in case the_content gets called multiple times.
		if ( ! in_array( $id, $this->shortcodes_collected['post'] ) )
			$this->footnotes[ $type ][ $id ][] = $content;
		$note = count( $this->footnotes[ $type ][ $id ] ) - 1;
		return ' <a class="simple-footnote" title="' . esc_attr( wp_strip_all_tags( $content ) ) . '" id="return' .
			( 'comment' == $type ? '-comment' : '' ) . '-note-' . $id . '-' . $note . '" href="#note-' . $id . '-' .
			$note . '"><sup>' . $note . '</sup></a>';
	}

	function the_content( $content ) {
		$this->shortcodes_collected[] = $GLOBALS['id'];
		if ( 'content' == $this->placement || ! $GLOBALS['multipage'] )
			return $this->footnotes( $content );
		return $content;
	}

	function wp_link_pages_args( $args ) {
		// if wp_link_pages appears both before and after the content,
		// $this->footnotes[$id] will be empty the first time through,
		// so it works, simple as that.
		$args['after'] = $this->footnotes( $args['after'] );
		return $args;
	}

	function footnotes( $content ) {
		if ( current_filter() == 'comment_text' ) {
			$type = 'comment';
			$id = $GLOBALS['comment']->comment_ID;
			$anchor = 'comment-note-';
		} else {
			$type = 'post';
			$id = $GLOBALS['id'];
			$anchor = 'note-';
		}
		if ( empty( $this->footnotes[ $type ][ $id ] ) )
			return $content;
		$content .= '<div class="simple-footnotes">';
		if ( 'post' == $type )
			$content .= '<p class="notes">Notes:</p><ol>';
		foreach ( array_filter( $this->footnotes[ $type ][ $id ] ) as $num => $note )
			$content .= '<li id="' . $anchor . $id . '-' . $num . '">' . do_shortcode( $note ) .
				' <a href="#return-' . $anchor . $id . '-' . $num . '">&#8617;</a></li>';
		$content .= '</ol></div>';
		return $content;
	}

	function do_shortcode_comments( $text ) {
		global $shortcode_tags;
		$orig_shortcode_tags = $shortcode_tags;
		remove_all_shortcodes();
		add_shortcode( 'ref', array( &$this, 'footnotes' ) );
		$content = do_shortcode( $content );
		$shortcode_tags = $orig_shortcode_tags;
		return $content;
	}
	
}
new nacin_footnotes();
