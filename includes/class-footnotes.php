<?php

namespace McAskill\WordPress\Footnotes;

/**
 * Simple Footnotes for WordPress
 */
class Footnotes
{
    /**
     * Store the footnotes.
     *
     * @var array
     */
    protected $footnotes = [];

    /**
     * Store the footnote numbering for the currently paginated posts.
     *
     * @var array
     */
    protected $pagination = [];

    /**
     * The plugin's current settings.
     *
     * @var array
     */
    protected $options = [];

    /**
     * The location for footnotes, derived from current settings.
     *
     * @var string
     */
    protected $placement = 'content';

    /**
     * The plugin's option name.
     *
     * @var string
     */
    protected $option_name = 'simple_footnotes';

    /**
     * The plugin's database version number, for schema upgrades.
     *
     * @var int
     */
    protected $db_version = 1;

    /**
     * Bootstrap the plugin.
     *
     * @return void
     */
    public function boot()
    {
        add_action( 'init',       [ $this, 'init' ] );
        add_action( 'admin_init', [ $this, 'admin_init' ] );
    }

    /**
     * @listens WP#action:init
     *
     * @return void
     */
    public function init()
    {
        $this->add_shortcode();

        // Add high-priority hook to clear footnotes array
        $this->add_action_on_filter( 'the_content', [ $this, 'clear' ], 1 );

        // Fetch and set up options.
        $this->load_settings();

        // Tell WP to use our filters in the proper place
        if ( 'page_links' === $this->placement && ! is_feed() ) {
            add_filter( 'wp_link_pages_args', [ $this, 'wp_link_pages_args' ] );
        } else {
            add_filter( 'the_content', [ $this, 'append_to_post_content' ], 12 );
        }

        // Allow logged in users to add footnotes to comments
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            add_filter( 'comment_text', [ $this, 'run_shortcode' ], 31 );
            add_filter( 'comment_text', [ $this, 'append_to_comment_text' ], 32 );
        }

        // Footnote pagination
        add_filter( 'footnote_number', [ $this, 'maybe_paginate_footnotes' ], 10, 4 );
    }

    /**
     * @listens WP#action:admin_init
     *
     * @return void
     */
    public function admin_init()
    {
        $this->upgrade();

        load_plugin_textdomain( 'simple-footnotes' );

        add_settings_field(
            'simple_footnotes_placement',
            __( 'Footnotes placement', 'simple-footnotes' ),
            [ $this, 'display_settings_field' ],
            'reading'
        );
        register_setting( 'reading', $this->option_name, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => $this->get_default_settings(),
        ] );
    }

    /**
     * Upgrade the database settings.
     *
     * @return void
     */
    protected function upgrade()
    {
        // Check if DB needs to be upgraded
        if (
            false === $this->options ||
            ! isset( $this->options['db_version'] ) ||
            $this->options['db_version'] < $this->db_version
        ) {
            // Initialize options array
            if ( ! is_array( $this->options ) ) {
                $this->options = [];
            }

            // Establish the DB version
            $current_db_version = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;

            // Run upgrade and store new version #
            if ( $current_db_version < 1 ) {
                $this->options['placement'] = 'content';
            }

            $this->options['db_version'] = $this->db_version;
            update_option( $this->option_name, $this->options );
        }
    }

    /**
     * Load the current settings.
     *
     * @return void
     */
    protected function load_settings()
    {
        $this->options = get_option( $this->option_name );
        if ( ! empty( $this->options ) && ! empty( $this->options['placement'] ) ) {
            $this->placement = $this->options['placement'];
        }
    }

    /**
     * Get the default settings.
     *
     * @listens WP#filter:default_option_{$option}
     *
     * @return array
     */
    public function get_default_settings()
    {
        return [
            'db_version' => $this->db_version,
            'placement'  => 'content',
        ];
    }

    /**
     * Sanitize settings before saving.
     *
     * @listens WP#filter:sanitize_option_{$option}
     *
     * @param  mixed $prefs The user options to process.
     * @return array The processed settings.
     */
    public function sanitize_settings( $prefs )
    {
        $settings = $this->get_default_settings();

        if ( ! empty( $prefs['placement'] ) && 'page_links' === $prefs['placement'] ) {
            $settings['placement'] = $prefs['placement'];
        }

        return $settings;
    }

    /**
     * Render the settings field.
     *
     * @listens WP#callback:do_settings_fields()
     *
     * @param  array $args Field output arguments.
     * @return void
     */
    public function display_settings_field( array $args = [] )
    {
        $fields = [
            'content'    => __( 'Below content',    'simple-footnotes' ),
            'page_links' => __( 'Below page links', 'simple-footnotes' ),
        ];

        echo '<fieldset><p>';

        $html = '<label><input type="radio" name="{name}" value="{value}"{checked}> {label}</label><br/>';
        foreach ( $fields as $field => $label ) {
            echo strtr( $html, [
                '{name}'    => $this->option_name . '[placement]',
                '{value}'   => $field,
                '{label}'   => $label,
                '{checked}' => checked( $this->placement, $field, false ),
            ] );
        }

        echo '</p></fieldset>';
    }

    /**
     * Add the `[ref]` shortcode.
     *
     * @return void
     */
    public function add_shortcode()
    {
        add_shortcode( 'ref', [ $this, 'shortcode' ] );
    }

    /**
     * Process the `[ref]` shortcode.
     *
     * This function removes all existing shortcodes, registers the `[ref]` shortcode,
     * calls {@see do_shortcode()}, and then re-registers the old shortcodes.
     *
     * @listens WP#filter:comment_text
     *
     * @global array $shortcode_tags List of shortcode tags and their callback hooks.
     *
     * @param  string $content Content to parse.
     * @return string Content with shortcode parsed.
     */
    public function run_shortcode( $content )
    {
        global $shortcode_tags;

        // Back up current registered shortcodes and clear them all out
        $orig_shortcode_tags = $shortcode_tags;
        remove_all_shortcodes();

        $this->add_shortcode();

        // Do the shortcode (only the [embed] one is registered)
        $content = do_shortcode( $content, true );

        // Put the original shortcodes back
        $shortcode_tags = $orig_shortcode_tags;

        return $content;
    }

    /**
     * Process the `[ref]` shortcode.
     *
     * @listens WP#callback:do_shortcode()
     *
     * @global \WP_Comment $comment Global comment object.
     * @global \WP_Post    $post    Global post object.
     *
     * @fires WP#filter:footnote_number
     * @fires WP#filter:footnote_reference_html
     *
     * @param  array       $atts Shortcode attributes.
     * @param  string|null $note The content within the shortcode tags.
     * @return ?string
     */
    public function shortcode( $atts, $note = null )
    {
        global $comment, $post;

        if ( null === $note || '' === $note ) {
            return null;
        }

        $is_comment = ( current_filter() === 'comment_text' );
        if ( $is_comment ) {
            if ( null === $comment ) {
                return null;
            }

            $type   = 'comment';
            $id     = $comment->comment_ID;
            $anchor = 'comment-note-';
        } else {
            if ( null === $post ) {
                return null;
            }

            $type   = 'post';
            $id     = $post->ID;
            $anchor = 'note-';
        }

        // If the ID is not already in the array, create the array
        if ( ! isset( $this->footnotes[$type][$id] ) ) {
            $this->footnotes[$type][$id] = [ 0 => false ];
        }

        // Store the footnote in the array
        $this->footnotes[$type][$id][] = $note;

        /**
         * Calculates the footnote #
         *
         * @event filter:footnote_number
         *
         * @param  int     $mark    The reference number or mark. Defaults to the next sequential number.
         * @param  int     $id      The post or comment ID.
         * @param  string  $note    The footnote.
         * @param  string  $type    The current object type.
         * @return int    |string A number or mark.
         */
        $mark = apply_filters(
            'footnote_number',
            count( $this->footnotes[$type][$id] ) - 1,
            $id,
            $note,
            $type
        );

        /**
         * Filters the HTML anchor tag of a footnote.
         *
         * @event filter:footnote_reference_html
         *
         * @param  string $html The HTML anchor tag of a footnote.
         * @return string The filtered HTML link to a footnote.
         */
        $html = apply_filters(
            'footnote_reference_html',
            '<a class="simple-footnote" title="{title}" id="{id}" href="{href}"><sup>{mark}</sup></a>'
        );

        $key = $anchor . $id . '-' . $mark;

        return strtr( $html, [
            '{title}' => esc_attr( wp_strip_all_tags( $note ) ),
            '{id}'    => esc_attr( 'return-' . $key ),
            '{href}'  => esc_attr( '#' . $key ),
            '{mark}'  => $mark,
        ] );
    }

    /**
     * Append the footnotes to the comment text.
     *
     * @listens WP#filter:comment_text
     *
     * @param  string $content Content of the current comment.
     * @return string The processed comment.
     */
    public function append_to_comment_text( $content )
    {
        return $this->append( $content );
    }

    /**
     * Append the footnotes to the post content.
     *
     * @listens WP#filter:the_content
     *
     * @global int $multipage Boolean indicator for whether multiple pages are in play.
     *
     * @param  string $content Content of the current post.
     * @return string The processed content.
     */
    public function append_to_post_content( $content )
    {
        global $multipage;

        if ( 'content' === $this->placement || ! $multipage ) {
            return $this->append( $content );
        }

        return $content;
    }

    /**
     * Process the footnotes after the page links for paginated posts.
     *
     * If {@see wp_link_pages()} appears both before and after the content,
     * `$this->footnotes[$id]` will be empty the first time through,
     * so it works, simple as that.
     *
     * @listens WP#filter:wp_link_pages_args
     *
     * @param  array $args Arguments for page links for paginated posts.
     * @return array The filtered arguments.
     */
    public function wp_link_pages_args( $args )
    {
        $args['after'] = $this->append( $args['after'] );

        return $args;
    }

    /**
     * Remove all collected footnotes.
     *
     * @return void
     */
    public function clear()
    {
        $this->footnotes = [];
    }

    /**
     * Append the footnotes.
     *
     * @global \WP_Comment $comment  Global comment object.
     * @global int         $numpages Number of pages in the current post.
     * @global string      $pagenow  Current Admin page
     * @global \WP_Post    $post     Global post object.
     *
     * @param  string $content The content to process.
     * @return string The content with footnotes.
     */
    public function append( $content )
    {
        global $comment, $numpages, $pagenow, $post;

        $is_comment = ( current_filter() === 'comment_text' );
        if ( $is_comment ) {
            if ( null === $comment ) {
                return $content;
            }

            $type   = 'comment';
            $id     = $comment->comment_ID;
            $anchor = 'comment-note-';
        } else {
            if ( null === $post ) {
                return $content;
            }

            $type   = 'post';
            $id     = $post->ID;
            $anchor = 'note-';
        }

        // If there are no footnotes, bail early.
        if ( empty( $this->footnotes[$type][$id] ) ) {
            return $content;
        }

        // If post is paginated, make sure footnotes stay consistent.
        if ( ! $is_comment && $numpages > 1 ) {
            $start = $this->pagination[$id][$pagenow];
        } else {
            $start = 0;
        }

        $content .= '<aside class="simple-footnotes">';

        if ( ! $is_comment ) {
            load_plugin_textdomain( 'simple-footnotes' );
            $content .= '<p class="notes">' . __( 'Notes:', 'simple-footnotes' ) . '</p>';
        }

        $content .= '<ol start="'. ( $start + 1 ) .'">';

        // Iterate footnotes and format output
        $html = '<li id="{id}">{note} <a href="{href}">{ref}</a></li>';
        foreach ( array_filter( $this->footnotes[$type][$id] ) as $num => $note ) {
            $mark = apply_filters( 'footnote_number', $num, $id, $note, $type );
            $key  = $anchor . $id . '-' . $mark;
            $note = strtr( $html, [
                '{id}'   => esc_attr( $key ),
                '{href}' => esc_attr( '#return-' . $key ),
                '{ref}'  => '&#8617;',
                '{note}' => do_shortcode( $note ),
            ] );

            $content .= $note;
        }

        $content .= '</ol></aside>';

        return $content;
    }

    /**
     * Resolve the absolute numbering for the given footnote.
     *
     * @param  string $content  The haystack of `[ref]` tags.
     * @param  string $footnote The searched footnote.
     * @return int The zero-based indexed for $footnote.
     */
    public function get_footnote_absolute_index( $content, $footnote )
    {
        $pattern = '/(.?)\[(ref)\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)/s';
        preg_match_all( $pattern, $content, $matches );
        return array_search( $footnote, $matches[5] );
    }

    /**
     * If post is paginated adjusts the footnote numbering to remain consistent across pages.
     *
     * @listens filter:footnote_number
     *
     * @global int      $numpages Number of pages in the current post.
     * @global string   $pagenow  Current Admin page
     * @global \WP_Post $post     Global post object.
     *
     * @param  int     $mark    The original footnote number.
     * @param  int     $id      The post ID.
     * @param  string  $note    The footnote.
     * @param  string  $type    The current object type.
     * @return int The adjusted number.
     */
    public function maybe_paginate_footnotes( $number, $id, $note, $type )
    {
        global $numpages, $pagenow, $post;

        // Don't worry about pagination for comments.
        if ( 'comment' === $type ) {
            return $number;
        }

        // If the post isn't paginated, just return the footnote number as is.
        if ( ! isset( $numpages ) || $numpages != 1 ) {
            return $number;
        }

        // If this is the first footnote being processed on this page,
        // figure out the starting # for this page's footnotes.
        if ( !isset( $this->pagination[$id][$pagenow] ) ) {
            $index = $this->get_footnote_absolute_index( $post->post_content, $note );
            $this->pagination[$id][$pagenow] = $index;
        }

        return $this->pagination[$id][$pagenow] + $number;
    }

    /**
     * Hooks a function or method to a specific filter event as if it was an action event.
     *
     * The filtered value is unaffected by the callback.
     *
     * @param  string    $tag             The name of the action to hook the $function_to_add callback to.
     * @param  callable  $function_to_add The callback to be run when the action is applied.
     * @param  int|float $priority        Optional. Used to specify the order in which the functions
     *                                    associated with a particular action are executed. Default 10.
     * @param  int       $accepted_args   Optional. The number of arguments the function accepts. Default 1.
     * @return bool Returns FALSE if the $function_to_add is not callable, otherwise returns TRUE.
     */
    protected function add_action_on_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 )
    {
        $proxy_function = function ( $value ) use ( $function_to_add ) {
            call_user_func_array( $function_to_add, func_get_args() );
            return $value;
        };

        return add_filter( $tag, $proxy_function, $priority, $accepted_args );
    }
}
