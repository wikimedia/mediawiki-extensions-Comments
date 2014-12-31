<?php

class CommentLatestIdAPI extends ApiBase {

    public function execute() {
        $pageID = $this->getMain()->getVal( 'pageID' );

        $commentsPage = new CommentsPage( $pageID, RequestContext::getMain() );

        $result = $this->getResult();
        $result->addValue( $this->getModuleName(), 'id', $commentsPage->getLatestCommentID() );
    }

    public function getAllowedParams() {
        return array(
            'pageID' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'int'
            )
        );
    }
}