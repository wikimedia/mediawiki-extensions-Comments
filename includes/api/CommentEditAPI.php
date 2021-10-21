<?php

use Wikimedia\ParamValidator\ParamValidator;

class CommentEditAPI extends CommentSubmitBase {

	public function execute() {
		$main = $this->getMain();
		$user = $this->getUser();

		$pageID = $main->getVal( 'pageID' );
		$commentText = $main->getVal( 'commentText' );

		// throws error on failure to validate
		$this->checkBlocks( $user );
		$this->validateCommentText( $commentText, $user, $pageID );

		$comment = Comment::newFromID( $main->getVal( 'commentID' ) );

		// Do not allow the edit action if the user is not the comment owner (or have no edit rights)
		// and also not a comments admin
		$canEditOwn = $comment->isOwner( $user ) && $user->isAllowed( 'comment-edit-own' );
		if ( !( $canEditOwn || $user->isAllowed( 'commentadmin' ) ) ) {
			$this->dieWithError( 'comments-edit-permissiondenied' );
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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'commentID' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'commentText' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

}
