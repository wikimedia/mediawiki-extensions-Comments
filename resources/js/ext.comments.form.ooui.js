/**
 * Handles all functions related to the comment form
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
	 * Builds the tab panel for writing a comment
	 *
	 * @return TabPanelLayout
	 */
	getTabWrite: function() {
		var write = new OO.ui.TabPanelLayout( 'write', {
			label: mw.msg( 'comments-tab-write' )
		} );

		return write;
	},

	/**
	 * Builds the tab panel for previewing your comment
	 *
	 * @return TabPanelLayout
	 */
	getTabPreview: function() {
		var preview = new OO.ui.TabPanelLayout( 'preview', {
			label: mw.msg( 'comments-tab-preview' )
		} );

		return preview;
	},

	/**
	 * Initalizes the index layout for tab panels
	 *
	 * @return IndexLayout
	 */
	initIndexLayout: function () {
		var index = new OO.ui.IndexLayout();

		return index;
	},

	/**
	 * Initalizes the panel layout to wrap around the index
	 *
	 * @return PanelLayout
	 */
	initPanelLayout: function() {
		var panel = new OO.ui.PanelLayout( {
			framed: true
		} );

		return panel;
	},

	enforceByteLimit: function () {
		// work in progress
	},

	showForm: function() {
		var index = CommentsForm.initIndexLayout();
		var panel = CommentsForm.initPanelLayout();

		var tabs = {
			write: CommentsForm.getTabWrite(),
			preview: CommentsForm.getTabPreview()
		};

		index.addTabPanels( [ tabs.write, tabs.preview ] );

		$( panel.$element ).append( index.$element );
		$( CommentsForm.form.$element ).prepend( panel.$element );
	}
};

// show everything!
CommentsForm.showForm();

}( jQuery, mediaWiki ) );