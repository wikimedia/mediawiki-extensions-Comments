<?php
/**
 * Logging formatter for Comments' log entries.
 *
 * @file
 * @date 28 July 2013
 */
class CommentsLogFormatter extends LogFormatter {
	/**
	 * Gets the log action, including username.
	 *
	 * This is a copy of LogFormatter::getActionText() with one "escaped"
	 * swapped to parse; no other changes here!
	 *
	 * @return string HTML
	 */
	public function getActionText() {
		if ( $this->canView( LogPage::DELETED_ACTION ) ) {
			$element = $this->getActionMessage();
			if ( $element instanceof Message ) {
				$element = $this->plaintext ? $element->text() : $element->parse(); // <-- here's the change!
			}
			if ( $this->entry->isDeleted( LogPage::DELETED_ACTION ) ) {
				$element = $this->styleRestricedElement( $element );
			}
		} else {
			$performer = $this->getPerformerElement() . $this->msg( 'word-separator' )->text();
			$element = $performer . $this->getRestrictedElement( 'rev-deleted-event' );
		}

		return $element;
	}

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
		$params[1] = $this->canView( LogPage::DELETED_USER ) ? $entry->getPerformer()->getName() : '';
		$title = $entry->getTarget();
		if ( $title instanceof Title ) { // healthy paranoia
			$title->setFragment( '#comment-' . $commentId );
		}
		$params[2] = Message::rawParam( $this->makePageLink( $title ) );

		// Bad things happens if the numbers are not in correct order
		ksort( $params );
		return $this->parsedParameters = $params;
	}
}