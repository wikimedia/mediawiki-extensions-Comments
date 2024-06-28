<?php

class CommentBlockAPI extends ApiBase {

	public function execute() {
		// Load user_name and user_id for person we want to block from the comment it originated from
		$dbr = Comment::getDBHandle( 'read' );
		$s = $dbr->selectRow(
			'Comments',
			[ 'Comment_actor' ],
			[ 'CommentID' => $this->getMain()->getVal( 'commentID' ) ],
			__METHOD__
		);
		if ( $s !== false ) {
			$blockedUser = User::newFromActorId( $s->comment_actor );

			if ( $blockedUser && $blockedUser instanceof User ) {
				CommentFunctions::blockUser( $this->getUser(), $blockedUser );

				if ( class_exists( 'UserStatsTrack' ) ) {
					$userID = $blockedUser->getId();
					$username = $blockedUser->getName();

					$stats = new UserStatsTrack( $userID, $username );
					$stats->incStatField( 'comment_ignored' );
				}
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
			'commentID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			]
		];
	}
}
