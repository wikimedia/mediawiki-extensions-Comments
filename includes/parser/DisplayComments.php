<?php

class DisplayComments {

	/**
	 * Callback function for onParserFirstCallInit(),
	 * displays comments.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string HTML
	 */
	public static function getParserHandler( $input, $args, $parser ) {
		global $wgCommentsSortDescending;

		$po = $parser->getOutput();
		$po->updateCacheExpiry( 0 );
		// If an unclosed <comments> tag is added to a page, the extension will
		// go to an infinite loop...this protects against that condition.
		$parser->setHook( 'comments', [ __CLASS__, 'nonDisplayComments' ] );

		// Add required CSS & JS via ResourceLoader
		$po->addModuleStyles( 'ext.comments.css' );
		$po->addModules( 'ext.comments.js' );
		$po->addJsConfigVars( [ 'wgCommentsSortDescending' => $wgCommentsSortDescending ] );

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
			in_array( strtoupper( $args['voting'] ), [ 'OFF', 'PLUS', 'MINUS' ] )
		) {
			$voting = $args['voting'];
		}

		$title = $parser->getTitle();
		// Create a new context to execute the CommentsPage
		$context = new RequestContext;
		$context->setTitle( $title );
		$context->setRequest( new FauxRequest() );
		$context->setUser( $parser->getUser() );
		$context->setLanguage( $parser->getTargetLanguage() );

		$commentsPage = new CommentsPage( $title->getArticleID(), $context );
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
		$attr = [];

		foreach ( $args as $name => $value ) {
			$attr[] = htmlspecialchars( $name ) . '="' . htmlspecialchars( $value ) . '"';
		}

		$output = '&lt;comments';
		if ( count( $attr ) > 0 ) {
			$output .= ' ' . implode( ' ', $attr );
		}

		if ( $input !== null ) {
			$output .= '&gt;' . htmlspecialchars( $input ) . '&lt;/comments&gt;';
		} else {
			$output .= ' /&gt;';
		}

		return $output;
	}
}
