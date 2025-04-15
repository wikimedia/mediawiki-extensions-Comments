<?php

use Wikimedia\ParamValidator\ParamValidator;

class CommentSubmitAPI extends MediaWiki\Api\ApiBase {

	public function execute() {
		$main = $this->getMain();
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
		$pageID = $main->getVal( 'pageID' );
		$title = Title::newFromId( $pageID );
		if ( !$title instanceof Title ) {
			$this->dieWithError(
				[ 'nosuchpageid', $pageID ],
				'comments-missing-page'
			);
		}

		$commentText = $main->getVal( 'commentText' );

		if ( $commentText != '' ) {
			// To protect against spam, it's necessary to check the supplied text
			// against spam filters (but comment admins are allowed to bypass the
			// spam filters)
			if ( !$user->isAllowed( 'commentadmin' ) && CommentFunctions::isSpam( $commentText ) ) {
				$this->dieWithError(
					$this->msg( 'comments-is-spam' ),
					'comments-is-spam'
				);
			}

			// If the comment contains links but the user isn't allowed to post
			// links, reject the submission
			if ( !$user->isAllowed( 'commentlinks' ) && CommentFunctions::haveLinks( $commentText ) ) {
				$this->dieWithError(
					$this->msg( 'comments-links-are-forbidden' ),
					'comments-links-are-forbidden'
				);
			}

			// AbuseFilter check (T301083)
			$abuseStatus = CommentFunctions::isAbusive( $pageID, $user, $commentText );
			if ( !$user->isAllowed( 'commentadmin' ) && !$abuseStatus->isOK() ) {
				$this->dieStatus( $abuseStatus );
			}

			$page = new CommentsPage( $pageID, $this->getContext() );

			Comment::add( $commentText, $page, $user, $main->getVal( 'parentID' ) );

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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'parentID' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'commentText' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}
}
