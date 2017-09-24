/**
 * Handles all functions related to the comment form
 *
 * @file
 */
( function ( $, mw ) {

var CommentsForm = {

	// infused OOUI widgets + layouts
	form: OO.ui.infuse( 'CommentsFormLayout' ),
	textarea: OO.ui.infuse( 'CommentsTextareaWidget' ),
	//not yet! soon
	//avatar: OO.ui.infuse( 'CommentsAvatarWidget' ),

	// other elements
	container: $( '.comments-body' ),
	replyto: $( '#replyto' ),

	// configuration
	isByteLimitEnabled: mw.config.get( 'wgCommentsEnableByteLimit' ),
	getByteLimitNumber: mw.config.get( 'wgCommentsByteLimitNumber' ),

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
	 * build the buttons that let the user
	 * either submit or cancel their comment.
	 */
	buildButtons: function () {
		var buttons = {
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
		if ( CommentsForm.replyto.text() !== '' ) {
			var group = new OO.ui.ButtonGroupWidget( {
				items: [
					buttons.submit,
					buttons.cancel
				]
			} );
			return group;
		} else {
			return buttons.submit;
		}
	},

	/**
	 * build the write and preview tab needed
	 * for the comment form
	 */
	buildTabs: function () {
		var tabs = {
			write: new OO.ui.TabPanelLayout( 'write', {
				label: mw.msg( 'comments-tab-write' )
			} ),
			preview: new OO.ui.TabPanelLayout( 'preview', {
				label: mw.msg( 'comments-tab-preview' )
			} )
		};

		tabs.write.$element.append(
			CommentsForm.form
		);

		tabs.preview.$element.append(
			CommentsForm.preview(
				CommentsForm.textarea.val(),
				$( this ).attr( 'data-linked-element' )
			)
		);

		var index = new OO.ui.IndexLayout();
		index.addTabPanels( [ tabs.write, tabs.preview ] );

		CommentsForm.container.prepend( index.$element );
	},

	enforceByteLimit: function () {
		// work in progress
	}
};

}( jQuery, mediaWiki ) );