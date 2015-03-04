<?php
/*
 * Plugin Name: Simple Footnotes
 * Plugin URI: http://wordpress.org/extend/plugins/simple-footnotes/
 * Plugin Description: Create simple, elegant footnotes on your site. Use the <code>[ref]</code> shortcode ([ref]My note.[/ref]) and the plugin takes care of the rest. There's also a <a href="options-reading.php">setting</a> that enables you to move the footnotes below your page links, for those who paginate posts.
 * Version: 0.4
 * Author: Andrew Nacin
 * Author URI: http://andrewnacin.com/
 * Text Domain: simple-footnotes
 */

class nacin_footnotes {

	// Stores footnotes once crawled.
	var $footnotes = array();
	var $pagination = array();

	// Holds option data.
	var $option_name = 'simple_footnotes';
	var $options = array();
	var $placement = 'content';

	// DB version, for schema upgrades.
	var $db_version = 1;
	
	//instance
	static $instance;
	
	/**
	 * Fires when class is constructed, adds init hook
	 */
	function nacin_footnotes() {
	
		//allow this instance to be called from outside the class
		self::$instance = $this;
		
		//add init hook
		add_action( 'init', array( &$this, 'init' ) );
		
		//add admin panel
		add_action( 'admin_init', array( &$this, 'admin_init' ) );

	}

	/**
	 * Init Callback
	 */
	function init() {

		//register shortcode
		add_shortcode( 'ref', array( &$this, 'shortcode' ) );
		
		//add high-priority hook to clear footnotes array
		add_filter( 'the_content', array( &$this, 'clear_footnotes' ), 1 );

		//Fetch and set up options.
		$this->options = get_option( 'simple_footnotes' );
		if ( ! empty( $this->options ) && ! empty( $this->options['placement'] ) )
			$this->placement = $this->options['placement'];

		//Tell WP to use our filters in the proper place
		if ( 'page_links' == $this->placement && !is_feed() )
			add_filter( 'wp_link_pages_args', array( &$this, 'wp_link_pages_args' ) );
		else
			add_filter( 'the_content', array( &$this, 'the_content' ), 12 );

		//Allow logged in users to add footnotes to comments
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) 
		    add_filter( 'comment_text', array( &$this, 'do_shortcode_comments' ), 11 );
			
		//pagination
		add_filter( 'footnote_number', array( &$this, 'maybe_paginate_footnotes' ), 10, 4 );

	}

	/**
	 * Admin init Callback
	 */
	function admin_init() {
	
		//check if DB needs to be upgraded
		if ( false === $this->options || ! isset( $this->options['db_version'] ) || $this->options['db_version'] < $this->db_version ) {
			
			//init options array
			if ( ! is_array( $this->options ) )
				$this->options = array();
				
			//establish DB version 
			$current_db_version = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;
			
			//run upgrade and store new version #
			$this->upgrade( $current_db_version );
			$this->options['db_version'] = $this->db_version;
			update_option( $this->option_name, $this->options );
			
		}

		load_plugin_textdomain( 'simple-footnotes' );

		//add options fields
		add_settings_field( 'simple_footnotes_placement', __( 'Footnotes placement', 'simple-footnotes' ), array( &$this, 'settings_field_cb' ), 'reading' );
		register_setting( 'reading', 'simple_footnotes', array( &$this, 'register_setting_cb' ) );
	
	}

	/**
	 * Sanitizes settings before saving
	 * @param string $input the user input
	 * @returns string the sanitized input
	 */
	function register_setting_cb( $input ) {
		
		//set defaults
		$output = array( 'db_version' => $this->db_version, 'placement' => 'content' );
		
		//if placement is specified as page links, change
		if ( ! empty( $input['placement'] ) && 'page_links' == $input['placement'] )
			$output['placement'] = 'page_links';
			
		//return
		return $output;
	}
	
	/**
	 * Callback to output settings field UI
	 */
	function settings_field_cb() {
	
		//options for where the footnotes can be
		$fields = array(
			'content'    => __( 'Below content',    'simple-footnotes' ),
			'page_links' => __( 'Below page links', 'simple-footnotes' ),
		);
		
		//loop through each option and output
		foreach ( $fields as $field => $label ) 
			echo '<label><input type="radio" name="simple_footnotes[placement]" value="' . $field . '"' . checked( $this->placement, $field, false ) . '> ' . $label . '</label><br/>';
		
	}

	/**
	 * Upgrades Database
	 * @param int $current_db_version the current DB version
	 */
	function upgrade( $current_db_version ) {
	
		if ( $current_db_version < 1 )
			$this->options['placement'] = 'content';
	
	}

	/**
	 * Processes ref short code
	 * @param array $atts the attributes passed within the short code
	 * @param string $content the content within the short code tags
	 */
	function shortcode( $atts, $content = null ) {
	
		//if no footnote is provided, kick
		if ( null === $content )
			return;

		//Get the ID of the current comment or post
		if ( current_filter() == 'comment_text' ) {
			$type = 'comment';
			$id = $GLOBALS['comment']->comment_ID;
		} else {
			$type = 'post';
			$id = $GLOBALS['id'];
		}
		
		//If the ID is not already in the array, create the array
		if ( ! isset( $this->footnotes[ $type ][ $id ] ) )
			$this->footnotes[ $type ][ $id ] = array( 0 => false );
		
		//store the footnote in the array
		$this->footnotes[ $type ][ $id ][] = $content;
		
		//Calculate the footnote #
		$note = apply_filters( 'footnote_number', count( $this->footnotes[ $type ][ $id ] ) - 1, $id, $content, $type );

		//format footnote and output
		return ' <a class="simple-footnote" title="' . esc_attr( wp_strip_all_tags( $content ) ) . '" id="return' .
			( 'comment' == $type ? '-comment' : '' ) . '-note-' . $id . '-' . $note . '" href="#note-' . $id . '-' .
			$note . '"><sup>' . $note . '</sup></a>';
	}

	/**
	 * The content filter to process footnotes
	 * @param string $content the content
	 * @return string the modified content
	 */
	function the_content( $content ) {

		if ( 'content' == $this->placement || ! $GLOBALS['multipage'] )
			return $this->footnotes( $content );
			
		return $content;
	}
	
	/**
	 * Callback to clear collected footnotes incase the_content is called more than once
	 * @param string $content the content
	 * @returns string the same content
	 */
	function clear_footnotes($content) {
		$this->footnotes = array();
		return $content;
	}

	/**
	 * if wp_link_pages appears both before and after the content,
	 * $this->footnotes[$id] will be empty the first time through,
	 * so it works, simple as that.
	 */
	function wp_link_pages_args( $args ) {

		$args['after'] = $this->footnotes( $args['after'] );
		return $args;
	}

	/**
	 * Outputs the footnotes
	 * @param string $content the content
	 * @returns string the content with footnotes
	 */
	function footnotes( $content ) {
		
		global $numpages;
		global $pagenow;
		global $post;
	
		//establish type
		if ( current_filter() == 'comment_text' ) {
			$type = 'comment';
			$id = $GLOBALS['comment']->comment_ID;
			$anchor = 'comment-note-';
		} else {
			$type = 'post';
			$id = $GLOBALS['id'];
			$anchor = 'note-';
		}
		
		//if there are no footnotes, simply return
		if ( empty( $this->footnotes[ $type ][ $id ] ) )
			return $content;
		
		//post is paginated, make sure footnotes stay consistent	
		if ( $numpages > 1 ) 
			$start = $this->pagination[ $id ][ $pagenow ];
		else
			$start = 0;
		
		//append footnotes to content and format
		$content .= '<div class="simple-footnotes">';
		
		if ( 'post' == $type ) {
			load_plugin_textdomain( 'simple-footnotes' );
			$content .= '<p class="notes">' . __( 'Notes:', 'simple-footnotes' ) . '</p><ol start="'. ( $start + 1 ) .'">';
		}
			
		//loop through footnotes and format output
		foreach ( array_filter( $this->footnotes[ $type ][ $id ] ) as $num => $note ) {
			$num = apply_filters( 'footnote_number', $num, $id, $note, $type );
			$content .= '<li id="' . $anchor . $id . '-' . $num . '">' . do_shortcode( $note ) .
				' <a href="#return-' . $anchor . $id . '-' . $num . '">&#8617;</a></li>';
		}
				
		//close tags
		$content .= '</ol></div>';
		
		//return
		return $content;
	}

	/**
	 * Shortcode callback for comments
	 * @param string $content the content
	 * @returns string the modified content
	 */
	function do_shortcode_comments( $content ) {
	
		//get a lis tof all short code tags
		global $shortcode_tags;
		
		//save the original list
		$orig_shortcode_tags = $shortcode_tags;
		
		//remove all short codes from comments
		remove_all_shortcodes();
		
		//add ours back in
		add_shortcode( 'ref', array( &$this, 'footnotes' ) );
		
		//run comment through short codes
		$content = do_shortcode( $content );
		
		//return short codes to original state
		$shortcode_tags = $orig_shortcode_tags;
		
		//return content
		return $content;
	}
	
	/**
	 * Given a footnote, figures out the absolute numbering for that footnote
	 * @param string $content the content (all)
	 * @param string $footnote the footnote
	 * @returns int the zero indexed absolute footnote
	 */	
	function get_footnote_absolute_index( $content, $footnote ) {
		preg_match_all('/(.?)\[(ref)\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)/s', $content, $matches );
		return array_search( $footnote, $matches[5] );
	}
	
	/**
	 * If post is paginated adjusts the footnote numbering to remain consistent across pages
	 * @param int $number the original footnote number
	 * @param int $postID the ID of the post
	 * @param string $content the footnote's content
	 * @return int the adjusted number
	 */
	function maybe_paginate_footnotes( $number, $postID, $content, $type ) {

		global $numpages;
		global $pagenow;
		global $post;
		
		//don't worry about pagination for comments
		if ( $type == 'comment' )
			return $number;
		
		//if the post isn't paginated, just return the footnote number as is
		if ( !isset( $numpages ) || !$numpages == 1 )
			return $number;
		
		//if this is the first footnote being processed on this page, figure out the starting # for this page's footnotes	
		if ( !isset( $this->pagination[ $postID ][ $pagenow ] ) ) 
			$this->pagination[ $postID ][ $pagenow ] = $this->get_footnote_absolute_index( $post->post_content, $content );
		
		return $this->pagination[ $postID ][ $pagenow ] + $number;
	} 
	
}

new nacin_footnotes();
