<?php

class DisplayComments {

	/**
	 * Callback function for onParserFirstCallInit(),
	 * displays comments.
	 *
	 * @param $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string HTML
	 */
	public static function getParserHandler( $input, $args, $parser ) {
		global $wgOut, $wgCommentsSortDescending;

		$parser->enableOOUI();
		$parser->disableCache();
		// If an unclosed <comments> tag is added to a page, the extension will
		// go to an infinite loop...this protects against that condition.
		$parser->setHook( 'comments', [ __CLASS__, 'nonDisplayComments' ] );

		$title = $parser->getTitle();
		if ( $title->getArticleID() == 0 && $title->getDBkey() == 'CommentListGet' ) {
			return self::nonDisplayComments( $input, $args, $parser );
		}

		// Add required CSS & JS via ResourceLoader
		$wgOut->addModuleStyles( [ 'ext.comments.css', 'ext.comments.form.ooui.styles' ] );
		$wgOut->addModules( [ 'ext.comments.js', 'ext.comments.form.ooui' ] );
		$wgOut->addJsConfigVars( array( 'wgCommentsSortDescending' => $wgCommentsSortDescending ) );

		// Parse arguments
		// The preg_match() lines here are to support the old-style way of
		// adding arguments:
		// <comments>
		// Allow=Foo,Bar
		// Voting=Plus
		// </comments>
		// whereas the normal, standard MediaWiki style, which this extension
		// also supports is: <comments allow="Foo,Bar" voting="Plus" />
		$allow = '';
		if ( preg_match( '/^\s*Allow\s*=\s*(.*)/mi', $input, $matches ) ) {
			$allow = htmlspecialchars( $matches[1] );
		} elseif ( !empty( $args['allow'] ) ) {
			$allow = $args['allow'];
		}

		$voting = '';
		if ( preg_match( '/^\s*Voting\s*=\s*(.*)/mi', $input, $matches ) ) {
			$voting = htmlspecialchars( $matches[1] );
		} elseif (
			!empty( $args['voting'] ) &&
			in_array( strtoupper( $args['voting'] ), array( 'OFF', 'PLUS', 'MINUS' ) )
		) {
			$voting = $args['voting'];
		}

		$commentsPage = new CommentsPage( $title->getArticleID(), $wgOut->getContext() );
		$commentsPage->allow = $allow;
		$commentsPage->setVoting( $voting );

		$output = '<div class="comments-body">';

		if ( $wgCommentsSortDescending ) { // form before comments
			$output .= '<a id="end" rel="nofollow"></a>';
			if ( !wfReadOnly() ) {
				$output .= $commentsPage->displayForm();
			} else {
				$output .= wfMessage( 'comments-db-locked' )->parse();
			}
		}

		$output .= $commentsPage->displayOrderForm();

		$output .= '<div id="allcomments">' . $commentsPage->display() . '</div>';

		// If the database is in read-only mode, display a message informing the
		// user about that, otherwise allow them to comment
		if ( !$wgCommentsSortDescending ) { // form after comments
			if ( !wfReadOnly() ) {
				$output .= $commentsPage->displayForm();
			} else {
				$output .= wfMessage( 'comments-db-locked' )->parse();
			}
			$output .= '<a id="end" rel="nofollow"></a>';
		}

		$output .= '</div>'; // div.comments-body

		return $output;
	}

	public static function nonDisplayComments( $input, $args, $parser ) {
		$attr = array();

		foreach ( $args as $name => $value ) {
			$attr[] = htmlspecialchars( $name ) . '="' . htmlspecialchars( $value ) . '"';
		}

		$output = '&lt;comments';
		if ( count( $attr ) > 0 ) {
			$output .= ' ' . implode( ' ', $attr );
		}

		if ( !is_null( $input ) ) {
			$output .= '&gt;' . htmlspecialchars( $input ) . '&lt;/comments&gt;';
		} else {
			$output .= ' /&gt;';
		}

		return $output;
	}
}