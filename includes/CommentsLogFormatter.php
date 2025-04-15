<?php
/**
 * Logging formatter for Comments' log entries.
 *
 * @file
 * @date 28 July 2013
 */

use MediaWiki\Title\Title;

class CommentsLogFormatter extends WikitextLogFormatter {
	/**
	 * Formats parameters intented for action message from
	 * array of all parameters. There are three hardcoded
	 * parameters (array is zero-indexed, this list not):
	 *  - 1: user name with premade link
	 *  - 2: usable for gender magic function
	 *  - 3: target page with premade link
	 * @return array
	 */
	protected function getMessageParameters() {
		if ( isset( $this->parsedParameters ) ) {
			return $this->parsedParameters;
		}

		$entry = $this->entry;
		$params = $this->extractParameters();

		$commentId = $params[3]; // = $4, because array numbering starts from 0

		$params[0] = Message::rawParam( $this->getPerformerElement() );
		$identity = $entry->getPerformerIdentity()->getName();
		$params[1] = $this->canView( LogPage::DELETED_USER ) ? $identity : '';
		$title = $entry->getTarget();
		if ( $title instanceof Title ) { // healthy paranoia
			$title->setFragment( '#comment-' . $commentId );
		}
		$params[2] = Message::rawParam( $this->makePageLink( $title ) );

		// Bad things happens if the numbers are not in correct order
		ksort( $params );
		$this->parsedParameters = $params;
		return $params;
	}
}
