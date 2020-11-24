<?php

class CommentBlockAPI extends ApiBase {

	public function execute() {
		CommentIgnoreList::block( $this->getMain()->getVal( 'commentID' ) );
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
