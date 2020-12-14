/**
 * Add Footnote Shortcode
 *
 * @author Andrew Patton
 * @see    http://wordpress.org/extend/plugins/simple-footnotes-editor-button/
 *
 * @package mcaskill/wp-simple-footnotes
 */
( function ( tinymce ) {

    tinymce.PluginManager.add( 'simple_footnotes', function ( editor, url ) {
        editor.addButton( 'simple_footnote', {
            title:   editor.getLang( 'simple-footnote.title' ),
            icon:    'anchor',
            onclick: function () {
                editor.windowManager.open( {
                    width:  480,
                    height: 100,
                    title:  editor.getLang( 'simple-footnote.title' ),
                    body:   [
                        {
                            type:      'textbox',
                            name:      'note',
                            multiline: true
                        }
                    ],
                    onsubmit: function ( event ) {
                        if ( event.data.note && ( typeof event.data.note === 'string' ) ) {
                            event.data.note = event.data.note.trim();
                        }

                        if ( event.data.note ) {
                            editor.insertContent( '[ref]' + event.data.note + '[/ref]' );
                        }
                    }
                } );
            }
        } );
    } );
} )( window.tinymce );
