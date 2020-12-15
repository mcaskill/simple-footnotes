/**
 * Add Footnote Shortcode
 *
 * @author Andrew Patton
 * @see    https://wordpress.org/extend/plugins/simple-footnotes-editor-button/
 *
 * @package mcaskill/wp-simple-footnotes
 */
( function ( tinymce ) {

    tinymce.PluginManager.add( 'simple_footnotes', function ( editor, url ) {
        var textCtrl, previewCtrl;

        textCtrl = {
            name:      'text',
            type:      'textbox',
            multiline: true,
            autofocus: true,
            ariaLabel: editor.getLang( 'simple-footnotes.textLabel' )
        };

        previewCtrl = {
            name:      'preview',
            type:      'checkbox',
            checked:   true,
            text:      editor.getLang( 'simple-footnotes.previewLabel' )
        };

        editor.addButton( 'simple_footnotes', {
            title:   editor.getLang( 'simple-footnotes.dialogTitle' ),
            icon:    'anchor',
            onclick: function () {
                editor.windowManager.open( {
                    title: editor.getLang( 'simple-footnotes.dialogTitle' ),
                    body:  [
                        textCtrl,
                        previewCtrl
                    ],
                    height:   104,
                    width:    480,
                    onsubmit: function ( event ) {
                        var attr = [];

                        if ( event.data.text && ( typeof event.data.text === 'string' ) ) {
                            event.data.text = event.data.text.trim();
                        }

                        if ( event.data.preview && ( typeof event.data.preview === 'boolean' ) ) {
                            attr.push( ' preview' );
                        }

                        if ( event.data.text ) {
                            editor.insertContent( '[ref' + attr.join('') + ']' + event.data.text + '[/ref]' );
                        }
                    }
                } );
            }
        } );
    } );
} )( window.tinymce );
