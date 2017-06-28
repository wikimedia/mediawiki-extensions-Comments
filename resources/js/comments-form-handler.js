/**
 * Handles all functions related to the comment
 * form, such as previewing and the tabs
 *
 * @file
 */
( function ( $, mw ) {

var commentFormHandler = {
	/**
	 * Preview the contents of the <textarea> (more accurately the
	 * wikitext given to this function) and show the parsed HTML
	 * output in the given element.
	 *
	 * @param text {string} Wikitext to be sent to the API for parsing
	 * @param targetElement {string} ID of the element where
	 * the preview text should be inserted
	 */
	preview: function ( text, targetElement ) {
		( new mw.Api() ).get( {
			action: 'parse',
			text: text,
			contentmodel: 'wikitext',
			disablelimitreport: true // don't need the NewPP report stuff
		} ).done( function ( data ) {
			$( '#' + targetElement ).html( data.parse.text['*'] );
		} ).fail( function () {
			$( '#' + targetElement ).text( mw.msg( 'comments-preview-failed' ) );
		} );
	},

	/**
	 * build the write and preview tab needed
	 * for the comment form
	 */
	buildTabs: function () {
		var formTabs = {
			write: new OO.ui.TabPanelLayout( 'write', {
				label: mw.msg( 'comments-tab-write' ),
			} ),
			preview: new OO.ui.TabPanelLayout( 'preview', {
				label: mw.msg( 'comments-tab-preview' )
			} )
		};

		formTabs.write.$content.append(
			$( '#comment-form' ).$element
		);

		formTabs.preview.$content.append(
			commentFormHandler.preview(
				$( '#comment-form' ).val(),
				$( this ).attr( 'data-linked-element' )
			).$element
		);

		var formIndex = new OO.ui.IndexLayout();
		formIndex.addTabPanels( [ formTabs.write, formTabs.preview ] );
		$( '.comments-body' ).html( formIndex.$element );
	},

	/**
	 * build the buttons that let the user
	 * either submit or cancel their comment.
	 */
	buildButtons: function() {
		var formButtons = {
			submit: new OO.ui.ButtonInputWidget( {
				label: mw.msg( 'comments-post' ),
				icon: 'speechBubbleAdd',
				type: 'submit',
				flags: [ 'primary', 'progressive' ]
			} ),
			cancel: new OO.ui.ButtonInputWidget( {
				label: mw.msg( 'comments-cancel-reply' ),
				frameless: true,
				flags: [ 'destructive' ]
			} )
		};

		// decide what buttons to show
		if ( $( '#replyto' ).text() !== '' ) {
			var buttonGroup = new OO.ui.ButtonGroupWidget( {
				items: [
					formButtons.submit,
					formButtons.cancel
				]
			} );
			return buttonGroup;
		} else {
			return formButtons.submit;
		}
	}
};

}( jQuery, mediaWiki ) );
