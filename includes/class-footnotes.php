<?php

namespace McAskill\WordPress\Footnotes;

/**
 * Simple Footnotes for WordPress
 */
class Footnotes implements \Countable, \IteratorAggregate
{
    public const SHORTCODE_TAG         = 'ref';
    public const AJAX_GET_GLOBAL_NOTES = 'simple_footnotes_get_global_notes';

    /**
     * @var object[] $global_notes_list A list of global notes.
     */
    protected static $global_notes_list;

    /**
     * @var array<string, object> $global_notes_map A map of global notes.
     */
    protected static $global_notes_map;

    /**
     * @var int $global_notes_count The number of available global notes.
     */
    protected static $global_notes_count = 0;

    /**
     * @var bool $has_any_global_notes Whether any global notes are available.
     */
    protected static $has_any_global_notes = false;

    /**
     * @var bool $has_many_global_notes Whether many global notes are available.
     */
    protected static $has_many_global_notes = false;

    /**
     * Store the footnotes and references to render.
     *
     * @var array<string, array<int, array<string, object>>>
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
    protected $placement = 'the_content';

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
    protected $db_version = 2;

    /**
     * Bootstraps the plugin.
     *
     * @return void
     */
    public function boot()
    {
        add_action( 'init',       [ $this, 'init' ] );
        add_action( 'admin_init', [ $this, 'admin_init' ] );
    }

    /**
     * Initializes the plugin's global functionality.
     *
     * @listens WP#action:init
     *
     * @return void
     */
    public function init()
    {
        $this->load_settings();

        $this->add_shortcodes();

        switch ( $this->placement ) {
            case 'page_links':
                // add_filter( 'footnote_reference_index', [ $this, 'maybe_paginate_footnotes' ], 10, 4 );
                add_filter( 'wp_link_pages_args', [ $this, 'append_to_link_pages_args' ], 12 );
                $this->add_action_on_filter( 'wp_link_pages_args', [ $this, 'clear' ], 13 );
                break;

            case 'the_content':
                // add_filter( 'footnote_reference_index', [ $this, 'maybe_paginate_footnotes' ], 10, 4 );
                add_filter( 'the_content', [ $this, 'append_to_post_content' ], 12 );
                $this->add_action_on_filter( 'the_content', [ $this, 'clear' ], 13 );
                break;
        }

        // Allow logged in users to add footnotes to comments
        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            add_filter( 'comment_text', [ $this, 'do_shortcode' ], 31 );
            add_filter( 'comment_text', [ $this, 'append_to_comment_text' ], 32 );
        }

        add_action( 'wp_ajax_' . static::AJAX_GET_GLOBAL_NOTES,        [ $this, 'wp_ajax_get_global_notes' ] );
        add_action( 'wp_ajax_nopriv_' . static::AJAX_GET_GLOBAL_NOTES, [ $this, 'wp_ajax_get_global_notes' ] );
    }

    /**
     * Initializes the plugin's admin functionality.
     *
     * @listens WP#action:admin_init
     *
     * @return void
     */
    public function admin_init()
    {
        $this->upgrade();

        load_plugin_textdomain( 'simple_footnotes' );

        add_settings_field(
            'simple_footnotes_placement',
            __( 'Footnotes placement', 'simple_footnotes' ),
            [ $this, 'display_settings_field' ],
            'reading'
        );
        register_setting( 'reading', $this->option_name, [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
            'default'           => $this->get_default_settings(),
        ] );

        if ( current_user_can( 'edit_posts' ) && 'true' === get_user_option( 'rich_editing' ) ) {
            add_filter( 'teeny_mce_buttons',      [ $this, 'mce_buttons' ] );
            add_filter( 'mce_buttons',            [ $this, 'mce_buttons' ] );
            add_filter( 'mce_external_plugins',   [ $this, 'mce_external_plugins' ] );
            add_filter( 'mce_external_languages', [ $this, 'mce_external_languages' ] );
        }
    }

    /**
     * Handles AJAX requests for global notes.
     *
     * @listens WP#action:wp_ajax_{$action}
     * @listens WP#action:wp_ajax_nopriv_{$action}
     *
     * @return void
     */
    public function wp_ajax_get_global_notes() : void
    {
        wp_send_json_success( [
            'notes' => static::get_global_notes_list(),
        ] );
    }

    /**
     * Retrieves the default settings.
     *
     * @listens WP#filter:default_option_{$option}
     *
     * @return array
     */
    public function get_default_settings()
    {
        return [
            'db_version' => $this->db_version,
            'placement'  => 'the_content',
        ];
    }

    /**
     * Parses the given placement.
     *
     * @param  mixed $placement The placement to parse.
     * @return ?string
     */
    public function parse_placement( $placement )
    {
        if ( in_array( $placement, $this->get_valid_placements() ) ) {
            if ( 'page_links' === $placement && is_feed() ) {
                return 'the_content';
            }

            return $placement;
        }

        return null;
    }

    /**
     * Retrieves the available placement locations.
     *
     * The "custom" placement indicates that the reflist
     * must be explicitely invoked either from the `[reflist]`
     * shortcode or programmatically in the template file.
     *
     * @return array The placement options.
     */
    public function get_valid_placements()
    {
        return [
            'the_content',
            'page_links',
            'custom',
        ];
    }

    /**
     * Sanitizes the settings before saving.
     *
     * @listens WP#filter:sanitize_option_{$option}
     *
     * @param  mixed $input The user options to process.
     * @return array The processed settings.
     */
    public function sanitize_settings( $input )
    {
        $settings = $this->get_default_settings();

        $input = wp_parse_args( $input, $settings );
        $input['placement'] = $this->parse_placement( $input['placement'] );

        if ( ! empty( $input['placement'] ) ) {
            $settings['placement'] = $input['placement'];
        }

        return $settings;
    }

    /**
     * Renders the settings field.
     *
     * @listens WP#callback:do_settings_fields()
     *
     * @param  array $args Field output arguments.
     * @return void
     */
    public function display_settings_field( array $args = [] )
    {
        $fields = [
            'the_content' => __( 'Below content',    'simple_footnotes' ),
            'page_links'  => __( 'Below page links', 'simple_footnotes' ),
            'custom'      => __( 'Custom', 'simple_footnotes' ),
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
     * Adds the footnotes button to the first-row list of TinyMCE buttons.
     *
     * @listens WP#filter:mce_buttons
     *
     * @param  array $buttons First-row list of buttons.
     * @return array
     */
    public function mce_buttons( $buttons )
    {
        $key = array_search( 'blockquote', $buttons );

        if ( false !== $key ) {
            array_splice( $buttons, ( $key + 1 ), 0, [ $this->option_name ] );
        } else {
            array_push( $buttons, $this->option_name );
        }

        return $buttons;
    }

    /**
     * Registers the footnotes plugin to the list of TinyMCE external plugins.
     *
     * @listens WP#filter:mce_external_plugins
     *
     * @param  array $plugins An array of external TinyMCE plugins.
     * @return array
     */
    public function mce_external_plugins( $plugins )
    {
        $plugins[$this->option_name] = plugins_url( '/resources/tinymce/plugin.js', __DIR__ );

        return $plugins;
    }

    /**
     * Registers the footnotes plugin to the list of TinyMCE plugin localizations.
     *
     * @listens WP#filter:mce_external_languages
     *
     * @param  array $translations Translations for external TinyMCE plugins.
     * @return array
     */
    public function mce_external_languages( $translations )
    {
        $translations[$this->option_name] = plugin_dir_path( __DIR__ ) . 'resources/tinymce/translations.php';

        return $translations;
    }

    /**
     * Registers the shortcodes.
     *
     * @return void
     */
    public function add_shortcodes()
    {
        add_shortcode( static::SHORTCODE_TAG, [ $this, 'render_ref_shortcode' ] );
    }

    /**
     * Processes any `[ref]` shortcodes.
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
    public function do_shortcode( $content )
    {
        global $shortcode_tags;

        // Back up current registered shortcodes and clear them all out
        $orig_shortcode_tags = $shortcode_tags;
        remove_all_shortcodes();

        $this->add_shortcodes();

        // Do the shortcode (only the [embed] one is registered)
        $content = do_shortcode( $content, true );

        // Put the original shortcodes back
        $shortcode_tags = $orig_shortcode_tags;

        return $content;
    }

    /**
     * Renders the `[ref]` shortcode.
     *
     * @listens WP#callback:do_shortcode()
     *
     * @fires filter:footnote_note_index
     * @fires filter:footnote_note_mark
     * @fires filter:footnote_reference_mark
     * @fires filter:footnote_reference_text
     * @fires filter:footnote_reference_link_html
     * @fires filter:footnote_reference_html
     *
     * @param  array       $shortcode_attrs Shortcode attributes.
     * @param  string|null $shortcode_text  The content within the shortcode tags.
     * @return ?string
     */
    public function render_ref_shortcode( $shortcode_attrs, $shortcode_text = null )
    {
        if ( $shortcode_text === '' ) {
            $shortcode_text = null;
        }

        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id ) ) {
            return $shortcode_text;
        }

        $shortcode_attrs = wp_parse_args( $shortcode_attrs, [
            'id'   => null,
            'note' => null,
            //'mark' => null,
        ] );

        $note = null;

        if ( isset( $shortcode_attrs['id'] ) ) {
            $note = static::get_global_note( $shortcode_attrs['id'] );
        } elseif ( isset( $shortcode_attrs['note'] ) ) {
            $note = (object) [
                'id'   => crc32( $shortcode_attrs['note'] ),
                'text' => $shortcode_attrs['note'],
            ];
        }

        if ( empty( $note ) ) {
            return $shortcode_text;
        }

        // Store the footnote in the array
        if ( isset( $this->footnotes[$type][$id][$note->id] ) ) {
            $note = $this->footnotes[$type][$id][$note->id];
        } else {
            if ( ! isset( $this->footnotes[$type][$id] ) ) {
                $this->footnotes[$type][$id] = [];
            }

            $this->footnotes[$type][$id][$note->id] = $note;

            $note->refs = [];

            $note->index = count( $this->footnotes[$type][$id] );

            /**
             * Filters the footnote #.
             *
             * @event filter:footnote_note_index
             *
             * @param  int         $index The reference number. Defaults to the next sequential number.
             * @param  string      $type  The current object type.
             * @param  int         $id    The post or comment ID.
             * @param  object      $note  The footnote instance.
             * @param  string|null $text  The related reference text.
             * @return int A number or mark.
             */
            $note->index = apply_filters( 'footnote_note_index', $note->index, $type, $id, $note, $shortcode_text );

            /**
             * Filters the footnote marker based on the #.
             *
             * @event filter:footnote_note_mark
             *
             * @param  int|string  $mark The reference number or symbol. Defaults to the next sequential number.
             * @param  string      $type The current object type.
             * @param  int         $id   The post or comment ID.
             * @param  object      $note The footnote instance.
             * @param  string|null $text The related reference text.
             * @return int|string A number or symbol.
             */
            $note->mark = apply_filters( 'footnote_note_mark', $note->index, $type, $id, $note, $shortcode_text );
        }

        if ( is_null( $shortcode_text ) ) {
            /**
             * Filters the footnote marker based on the #.
             *
             * @event filter:footnote_reference_mark
             *
             * @param  int|string  $mark The reference number or symbol. Defaults to the next sequential number.
             * @param  string      $type The current object type.
             * @param  int         $id   The post or comment ID.
             * @param  object      $note The footnote instance.
             * @param  string|null $text The related reference text.
             * @return int|string A number or symbol.
             */
            $ref_mark = apply_filters( 'footnote_reference_mark', $note->mark, $type, $id, $note, $shortcode_text );
        } else {
            /**
             * Filters the footnote reference text.
             *
             * @event filter:footnote_reference_text
             *
             * @param  int|string  $text The reference text. Defaults to the shortcode's enclosed content.
             * @param  string      $type The current object type.
             * @param  int         $id   The post or comment ID.
             * @param  object      $note The footnote instance.
             * @param  string|null $text The related reference text.
             * @return int|string
             */
            $ref_mark = apply_filters( 'footnote_reference_text', $shortcode_text, $type, $id, $note, $shortcode_text );
        }

        $note->refs[] = $ref_mark;

        $note_id = "{$type}-{$id}-cite_note-{$note->id}";
        $ref_id  = "{$type}-{$id}-cite_ref-{$note->id}-" . count( $note->refs );

        $_ref_args = [
            '{html}'       => ( $shortcode_text ? 'span' : 'sup' ),
            '{ref_id}'     => esc_attr( $ref_id ),
            '{note_id}'    => esc_attr( $note_id ),
            '{note_index}' => $note->index,
            '{note_mark}'  => $note->mark,
            '{ref_text}'   => $ref_mark,
        ];

        /**
         * Filters the HTML anchor tag template of a footnote.
         *
         * @event filter:footnote_reference_link_html
         *
         * @param  string      $link_html The HTML anchor tag of a footnote.
         * @param  string      $type      The current object type.
         * @param  int         $id        The post or comment ID.
         * @param  object      $note      The footnote instance.
         * @param  string|null $text      The related reference text.
         * @return string The filtered HTML link to a footnote.
         */
        $_link_html = apply_filters(
            'footnote_reference_link_html',
            '<a href="#{note_id}">{ref_text}</a>',
            $type,
            $id,
            $note,
            $shortcode_text
        );

        $_ref_args['{link_html}'] = strtr( $_link_html, $_ref_args );

        $_ref_class = [ 'simple-footnote-reference' ];

        if ( in_array( 'preview', $shortcode_attrs ) ) {
            $_ref_class[] = 'simple-footnote-preview';
        }

        /**
         * Filters the HTML marker tag of a footnote.
         *
         * @event filter:footnote_reference_html
         *
         * @param  string      $ref_html The HTML marker tag of a footnote.
         * @param  string      $type     The current object type.
         * @param  int         $id       The post or comment ID.
         * @param  object      $note     The footnote instance.
         * @param  string|null $text     The related reference text.
         * @return string The filtered HTML link to a footnote.
         */
        $_ref_html = apply_filters(
            'footnote_reference_html',
            '<{html} id="{ref_id}" class="' . implode( ' ', $_ref_class ) . '">{link_html}</{html}>',
            $type,
            $id,
            $note,
            $shortcode_text
        );

        return strtr( $_ref_html, $_ref_args );
    }

    /**
     * Appends the footnotes to the comment text.
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
     * Appends the footnotes to the post content.
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

        if ( 'the_content' === $this->placement || ! $multipage ) {
            return $this->append( $content );
        }

        return $content;
    }

    /**
     * Appends the footnotes after the page links for paginated posts.
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
    public function append_to_link_pages_args( $args )
    {
        if ( 'page_links' === $this->placement ) {
            $args['after'] = $this->append( $args['after'] );
        }

        return $args;
    }

    /**
     * Resolve the absolute numbering for the given footnote.
     *
     * @param  string $content The haystack of `[ref]` tags.
     * @param  object $note    The footnote instance.
     * @return int The zero-based indexed for $footnote.
     */
    public function get_footnote_absolute_index( $content, $note )
    {
        $pattern = '/\[ref .*?(?:(?:id|note)="(.*?)").*?\]/s';
        preg_match_all( $pattern, $content, $matches );

        if ( isset( $note->id ) ) {
            $index = array_search( $note->id, $matches[1] );
            if ( false !== $index ) {
                return $index;
            }
        }

        if ( isset( $note->text ) ) {
            $index = array_search( $note->text, $matches[1] );
            if ( false !== $index ) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * If post is paginated adjust the footnote numbering to remain consistent across pages.
     *
     * @listens filter:footnote_reference_index
     *
     * @global int      $numpages Number of pages in the current post.
     * @global string   $pagenow  Current Admin page.
     * @global \WP_Post $post     Global post object.
     *
     * @param  int    $index The reference number.
     * @param  string $type  The current object type.
     * @param  int    $id    The post or comment ID.
     * @param  object $note  The footnote instance.
     * @return int The adjusted number.
     */
    public function maybe_paginate_footnotes( $index, $type, $id, $note )
    {
        global $numpages, $pagenow, $post;

        // Don't worry about pagination for comments.
        if ( 'comment' === $type ) {
            return $index;
        }

        // If the post isn't paginated, just return the footnote number as is.
        if ( ! isset( $numpages ) || $numpages != 1 ) {
            return $index;
        }

        // If this is the first footnote being processed on this page,
        // figure out the starting # for this page's footnotes.
        if ( is_object( $note ) && ! isset( $this->pagination[$id][$pagenow] ) ) {
            $index = $this->get_footnote_absolute_index( $post->post_content, $note );
            $this->pagination[$id][$pagenow] = $index;
        }

        if ( isset( $this->pagination[$id][$pagenow] ) ) {
            return $this->pagination[$id][$pagenow] + $index;
        }

        return $index;
    }

    /**
     * Removes all collected footnotes.
     *
     * @return void
     */
    public function clear()
    {
        $this->footnotes = [];
    }

    /**
     * Appends the collected footnotes to the given content.
     *
     * @param  string $content The content to process.
     * @return string The content with HTML footnotes.
     */
    public function append( $content )
    {
        $extra = $this->render();
        if ( empty( $extra ) ) {
            return $content;
        }

        return $content . "\n\n" . $extra;
    }

    /**
     * Renders the collected footnotes as an HTML list.
     *
     * @fires filter:footnote_reference_item_html
     * @fires filter:footnote_reference_backlinks_html
     * @fires filter:footnote_reference_note_html
     * @fires filter:footnote_reference_list_html
     *
     * @global int    $numpages Number of pages in the current post.
     * @global string $pagenow  Current Admin page.
     *
     * @return ?string The HTML footnotes.
     */
    public function render_items()
    {
        global $numpages, $pagenow;

        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id ) ) {
            return null;
        }

        if ( empty( $this->footnotes[$type][$id] ) ) {
            return null;
        }

        $is_comment  = ( 'comment' === $type );

        $note_prefix = "{$type}-{$id}-cite_note-";
        $ref_prefix  = "{$type}-{$id}-cite_ref-";

        // If post is paginated, make sure footnotes stay consistent.
        if ( ! $is_comment && $numpages > 1 ) {
            $start = $this->pagination[$id][$pagenow];
        } else {
            $start = 0;
        }

        /**
         * Filters the HTML list item template of a footnote.
         *
         * @event filter:footnote_reference_item_html
         *
         * @param  string $item_html The HTML list item of a footnote.
         * @param  string $type      The current object type.
         * @param  int    $id        The post or comment ID.
         * @return string The filtered HTML link to a footnote.
         */
        $_item_html = apply_filters(
            'footnote_reference_item_html',
            '<li id="{note_id}" value="{note_mark}">{back_html} {note_html}</li>',
            $type,
            $id
        );

        /**
         * Filters the HTML backlinks template of a footnote.
         *
         * @event filter:footnote_reference_backlinks_html
         *
         * @param  string $back_html The HTML backlists list of a footnote.
         * @param  string $type      The current object type.
         * @param  int    $id        The post or comment ID.
         * @return string The filtered HTML link to a footnote.
         */
        $_back_html = apply_filters(
            'footnote_reference_backlinks_html',
            '<span class="simple-footnote-backlink"><a href="#{ref_id}">{back_mark}</a></span>',
            $type,
            $id
        );

        /**
         * Filters the HTML text template of a footnote.
         *
         * @event filter:footnote_reference_note_html
         *
         * @param  string $note_html The HTML text of a footnote.
         * @param  string $type      The current object type.
         * @param  int    $id        The post or comment ID.
         * @return string The filtered HTML link to a footnote.
         */
        $_note_html = apply_filters(
            'footnote_reference_note_html',
            '<span class="simple-footnote-reference-text">{note_text}</span>',
            $type,
            $id
        );

        $items = [];
        foreach ( array_filter( $this->footnotes[$type][$id] ) as $note ) {
            $note_id = $note_prefix . $note->id;
            $_ref_id = $ref_prefix  . $note->id . '-';

            $args = [
                '{note_id}'    => esc_attr( $note_id ),
                '{note_index}' => $note->index,
                '{note_mark}'  => $note->mark,
                '{back_mark}'  => '&#8617;',
                '{note_text}'  => do_shortcode( $note->text ),
            ];

            $args['{note_html}'] = strtr( $_note_html, $args );
            $args['{back_html}'] = [];

            foreach ( $note->refs as $i => $_ref ) {
                $args['{back_html}'][] = strtr( $_back_html, $args + [
                    '{ref_id}' => esc_attr( $_ref_id . ( $i + 1 ) ),
                ] );
            }

            $args['{back_html}'] = implode( ' ', $args['{back_html}'] );

            $items[] = strtr( $_item_html, $args );
        }

        if ( empty( $items ) ) {
            return null;
        }

        /**
         * Filters the HTML list template of a footnote.
         *
         * @event filter:footnote_reference_list_html
         *
         * @param  string $list_html The HTML list of a footnote.
         * @param  string $type      The current object type.
         * @param  int    $id        The post or comment ID.
         * @return string The filtered HTML link to a footnote.
         */
        $_list_html = apply_filters(
            'footnote_reference_list_html',
            '<ol start="{start}">{items_html}</ol>',
            $type,
            $id
        );

        return strtr( $_list_html, [
            '{start}'      => ( $start + 1 ),
            '{items_html}' => implode( '', $items ),
        ] );
    }

    /**
     * Renders the collected footnotes as an HTML section.
     *
     * @return ?string The HTML footnotes.
     */
    public function render()
    {
        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id ) ) {
            return null;
        }

        $list_html = $this->render_items();

        if ( empty( $list_html ) ) {
            return null;
        }

        $is_comment = ( 'comment' === $type );

        $html = '<aside class="simple-footnotes-references">';

        if ( ! $is_comment ) {
            load_plugin_textdomain( 'simple_footnotes' );
            $html .= '<p class="simple-footnotes-references-title">' . __( 'Notes:', 'simple_footnotes' ) . '</p>';
        }

        $html .= $list_html;
        $html .= '</aside>';

        return $html;
    }

    /**
     * Retrieves the collected footnotes.
     *
     * @global int    $numpages Number of pages in the current post.
     * @global string $pagenow  Current Admin page.
     *
     * @return array Array of collected footnotes.
     */
    public function all()
    {
        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id ) ) {
            return [];
        }

        if ( empty( $this->footnotes[$type][$id] ) ) {
            return [];
        }

        return $this->footnotes[$type][$id];
    }

    /**
     * Determines whether footnotes are empty.
     *
     * @return bool Returns FALSE if any footnotes have been collected.
     */
    public function is_empty()
    {
        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id ) ) {
            return false;
        }

        return empty( $this->footnotes[$type][$id] );
    }

    /**
     * Counts the number of collected footnotes.
     *
     * @return int
     */
    public function count()
    {
        list( $type, $id ) = $this->get_context();

        if ( ! isset( $type, $id, $this->footnotes[$type][$id] ) ) {
            return 0;
        }

        return count( $this->footnotes[$type][$id] );
    }

    /**
     * Get the blocks iterator.
     *
     * @return Traversable
     */
    public function getIterator()
    {
        return new ArrayIterator( $this->all() );
    }

    /**
     * Retrieves the list of global notes.
     *
     * @return object[]
     */
    public static function get_global_notes_list() : array
    {
        if ( is_null( static::$global_notes_list ) ) {
            static::load_global_notes();
        }

        return static::$global_notes_list;
    }

    /**
     * Retrieves the map of global notes.
     *
     * @return array<string, object>
     */
    public static function get_global_notes_map() : array
    {
        if ( is_null( static::$global_notes_map ) ) {
            static::load_global_notes();
        }

        return static::$global_notes_map;
    }

    /**
     * Retrieves a global note by ID.
     *
     * @param  string $id The note ID.
     * @return ?object
     */
    public static function get_global_note( string $id ) : ?object
    {
        $notes = static::get_global_notes_map();

        if ( ! isset( $notes[$id] ) ) {
            return null;
        }

        return $notes[$id];
    }

    /**
     * Counts the number of available global notes.
     *
     * @return int
     */
    public static function count_global_notes() : int
    {
        static::get_global_notes_list();

        return static::$global_notes_count;
    }

    /**
     * Determines if there are any available global notes.
     *
     * @return bool
     */
    public static function has_any_global_notes() : bool
    {
        static::get_global_notes_list();

        return static::$has_any_global_notes;
    }

    /**
     * Determines if there are more than one available global notes.
     *
     * @return bool
     */
    public static function has_many_global_notes() : bool
    {
        static::get_global_notes_list();

        return static::$has_many_global_notes;
    }

    /**
     * Retrieves the context type and object ID.
     *
     * @global \WP_Comment $comment Global comment object.
     * @global \WP_Post    $post    Global post object.
     *
     * @return array
     */
    protected function get_context()
    {
        global $comment, $post;

        $type = null;
        $id   = null;

        $is_comment = ( 'comment_text' === current_filter() );
        if ( $is_comment ) {
            if ( null !== $comment ) {
                $type = 'comment';
                $id   = $comment->comment_ID;
            }
        } else {
            if ( null !== $post ) {
                $type = 'post';
                $id   = $post->ID;
            }
        }

        return [
            $type,
            $id,
        ];
    }

    /**
     * Upgrades the database settings.
     *
     * @return void
     */
    protected function upgrade()
    {
        // Check if DB needs to be upgraded
        if (
            empty( $this->options['db_version'] ) ||
            $this->options['db_version'] < $this->db_version
        ) {
            // Initialize options array
            if ( ! is_array( $this->options ) ) {
                $this->options = [];
            }

            // Establish the DB version
            $current_db_version = isset( $this->options['db_version'] ) ? $this->options['db_version'] : 0;

            // Run upgrade and store new version #
            if ( $current_db_version < 2 ) {
                $this->options['placement'] = 'the_content';
            }

            $this->options['db_version'] = $this->db_version;
            update_option( $this->option_name, $this->options );
        }
    }

    /**
     * Loads the current settings.
     *
     * @return void
     */
    protected function load_settings()
    {
        $options = get_option( $this->option_name );

        if ( ! empty( $options['placement'] ) ) {
            $options['placement'] = $this->parse_placement( $options['placement'] );
        }

        $options = wp_parse_args( $options, $this->get_default_settings() );

        $this->placement = $options['placement'];
        $this->options   = $options;
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

    /**
     * Loads the global notes.
     *
     * @fires filter:footnote_global_notes
     *
     * @return void
     */
    protected static function load_global_notes() : void
    {
        static::$global_notes_list = [];
        static::$global_notes_map  = [];

        /**
         * Aggregates and filters the global notes.
         *
         * @event filter:footnote_global_notes
         *
         * @param  object[] $notes Zero or more global notes.
         * @return object[]
         */
        $notes = (array) apply_filters( 'footnote_global_notes', [] );

        foreach ( $notes as $note ) {
            if ( is_array( $note ) ) {
                $note = (object) $note;
            }

            if ( isset( $note->id, $note->text ) ) {
                static::$global_notes_list[]         = $note;
                static::$global_notes_map[$note->id] = $note;
            }
        }

        static::$global_notes_count    = count( static::$global_notes_list );
        static::$has_any_global_notes  = ( static::$global_notes_count > 0 );
        static::$has_many_global_notes = ( static::$global_notes_count > 1 );
    }
}
