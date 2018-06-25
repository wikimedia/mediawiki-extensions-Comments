<?php

class CommentLatestIdAPI extends ApiBase {

	public function execute() {
		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		$pageID = $this->getMain()->getVal( 'pageID' );

		$commentsPage = new CommentsPage( $pageID, RequestContext::getMain() );

		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'id', $commentsPage->getLatestCommentID() );
	}

	public function getAllowedParams() {
		return [
			'pageID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			]
		];
	}
}
