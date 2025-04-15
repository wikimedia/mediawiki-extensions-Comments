<?php

use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class CommentBlockAPI extends MediaWiki\Api\ApiBase {
	private UserFactory $userFactory;

	public function __construct(
		MediaWiki\Api\ApiMain $main,
		string $action,
		UserFactory $userFactory
	) {
		parent::__construct( $main, $action );
		$this->userFactory = $userFactory;
	}

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
			$blockedUser = $this->userFactory->newFromActorId( $s->comment_actor );

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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			]
		];
	}
}
