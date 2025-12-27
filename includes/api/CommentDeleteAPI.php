<?php

use Wikimedia\ParamValidator\ParamValidator;

class CommentDeleteAPI extends MediaWiki\Api\ApiBase {

	public function execute() {
		$user = $this->getUser();

		$comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );

		$userCheck = (
			$user->isAllowed( 'commentadmin' ) ||
			( $user->isAllowed( 'comment-delete-own' ) && $comment->isOwner( $user ) )
		);

		// Blocked users cannot delete comments, and neither can unprivileged ones.
		if ( $user->getBlock() && !$userCheck ) {
			$this->dieBlocked( $user->getBlock() );
		}

		$comment->delete();

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
