<?php

use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * CommentSubmitBase class
 * Superclass for CommentSubmitApi and CommentEditApi
 *
 * @file
 * @ingroup Extensions
 */
class CommentSubmitBase extends MediaWiki\Api\ApiBase {

	public function execute() {
		return true;
	}

	public function checkBlocks( User $user ): void {
		// Blocked users cannot submit new comments, and neither can those users
		// without the necessary privileges.
		if ( $user->getBlock() ) {
			$this->dieBlocked( $user->getBlock() );
		} elseif ( !$user->isAllowed( 'comment' ) ) {
			$this->dieWithError( 'comments-not-allowed' );
		}
	}

	/**
	 * Determine if the given text is permissable to post for this user,
	 * or if the destination page even exists.
	 */
	public function validateCommentText( string $commentText, User $user, int $pageID ): void {
		// Check title existence early on so that we don't throw fatals in Comment#log
		// when we're trying to write a log action pertaining to a now-deleted page
		// or somesuch. (T258275)
		$title = Title::newFromId( $pageID );
		if ( !$title instanceof Title ) {
			$this->dieWithError(
				[ 'nosuchpageid', $pageID ],
				'comments-missing-page'
			);
		}

		// No empty comments
		if ( $commentText === '' ) {
			$this->dieWithError( 'comments-missing-text' );
		}

		// To protect against spam, it's necessary to check the supplied text
		// against spam filters (but comment admins are allowed to bypass the
		// spam filters)
		if ( !$user->isAllowed( 'commentadmin' ) && CommentFunctions::isSpam( $commentText ) ) {
			$this->dieWithError( 'comments-is-spam' );
		}

		// If the comment contains links but the user isn't allowed to post
		// links, reject the submission
		if ( !$user->isAllowed( 'commentlinks' ) && CommentFunctions::haveLinks( $commentText ) ) {
			$this->dieWithError( 'comments-links-are-forbidden' );
		}

		// AbuseFilter check (T301083)
		$abuseStatus = CommentFunctions::isAbusive( $pageID, $user, $commentText );
		if ( !$user->isAllowed( 'commentadmin' ) && !$abuseStatus->isOK() ) {
			$this->dieStatus( $abuseStatus );
		}
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}
}
