<?php

class CommentSubmitAPI extends ApiBase {

	public function execute() {
		$user = $this->getUser();
		// Blocked users cannot submit new comments, and neither can those users
		// without the necessary privileges.
		if ( $user->getBlock() ) {
			$this->dieBlocked( $user->getBlock() );
		} elseif ( $user->isBlockedGlobally() ) {
			$this->dieBlocked( $user->getGlobalBlock() );
		} elseif ( !$user->isAllowed( 'comment' ) ) {
			$this->dieWithError( 'comments-not-allowed' );
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

		if ( $commentText != '' ) {
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

			// AbuseFilter check (T301083)
			if ( !$user->isAllowed( 'commentadmin' ) && CommentFunctions::isAbusive( $pageID, $user, $commentText ) ) {
				// Could also show a more precise error message by checking the return
				// value of ::isAbusive(), but this'll do...
				$this->dieWithError(
					$this->msg( 'comments-is-filtered' )->plain(),
					'comments-is-filtered'
				);
			}

			$page = new CommentsPage( $pageID, $this->getContext() );

			Comment::add( $commentText, $page, $user, $this->getMain()->getVal( 'parentID' ) );

			if ( class_exists( 'UserStatsTrack' ) ) {
				$stats = new UserStatsTrack( $user->getId(), $user->getName() );
				$stats->incStatField( 'comment' );
			}
		}

		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'ok', 'ok' );
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
			'parentID' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'commentText' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}
}
