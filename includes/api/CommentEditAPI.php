<?php

class CommentEditAPI extends ApiBase {

	public function execute() {
		$user = $this->getUser();

		// Blocked users cannot edit comments, and neither can those users
		// without the necessary privileges.
		if (
			$user->getBlock() ||
			!$user->isAllowed( 'comment' )
		) {
			return true;
		}

		// Check title existence early on so that we don't throw fatals in Comment#log
		// when we're trying to write a log action pertaining to a now-deleted page
		// or somesuch. (T258275)
		$pageID = $this->getMain()->getVal( 'pageID' );
		$title = Title::newFromId( $pageID );
		if ( !$title instanceof Title ) {
			$this->dieWithError(
				[ 'nosuchpageid', $pageID ],
				'comments-missing-page'
			);
		}

		$commentText = $this->getMain()->getVal( 'commentText' );
		if ( $commentText == '' ) {
			$this->dieWithError(
				[ 'missingparam', 'commentText' ],
				'comments-missing-text'
			);
		}

		// To protect against spam, it's necessary to check the supplied text
		// against spam filters (but comment admins are allowed to bypass the
		// spam filters)
		if ( !$user->isAllowed( 'commentadmin' ) && CommentFunctions::isSpam( $commentText ) ) {
			$this->dieWithError(
				$this->msg( 'comments-is-spam' )->plain(),
				'comments-is-spam'
			);
		}

		// If the comment contains links but the user isn't allowed to post
		// links, reject the submission
		if ( !$user->isAllowed( 'commentlinks' ) && CommentFunctions::haveLinks( $commentText ) ) {
			$this->dieWithError(
				$this->msg( 'comments-links-are-forbidden' )->plain(),
				'comments-links-are-forbidden'
			);
		}

		$comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );
		if ( !$comment ) {
			// @TODO: create new error message
			$this->dieWithError(
				[ 'invalidtitle', $pageID ],
				'comments-missing-comment'
			);
		}

		// Do not allow the edit action if the user is not the comment owner (or have no edit rights)
		// and also not a comments admin
		if (
			!( $comment->isOwner( $user ) && $user->isAllowed( 'comment-edit-own' ) ) &&
			!$user->isAllowed( 'commentadmin' )
		) {
			// @TODO: create new error message
			$this->dieWithError(
				[ 'permissiondenied', $pageID ],
				'comments-edit-permissiondenied'
			);
		}

		$comment->edit( $commentText, $user );

		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'ok', $comment->getText() );
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'pageID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'commentID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'commentText' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

}
