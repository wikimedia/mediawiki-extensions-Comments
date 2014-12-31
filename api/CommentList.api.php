<?php

class CommentListAPI extends ApiBase {

    public function execute() {
        $commentsPage = new CommentsPage( $this->getMain()->getVal( 'pageID' ), RequestContext::getMain() );
        $commentsPage->orderBy = $this->getMain()->getVal( 'order' );
        $commentsPage->currentPagerPage = $this->getMain()->getVal( 'pagerPage' );

        $output = '';
        if ( $this->getMain()->getVal( 'showForm' ) ) {
            $output .= $commentsPage->displayOrderForm();
        }
        $output .= $commentsPage->display();
        if ( $this->getMain()->getVal( 'showForm' ) ) {
            $output .= $commentsPage->displayForm();
        }

        $result = $this->getResult();
        $result->addValue( $this->getModuleName(), 'html', $output );
        return true;
    }

    public function getAllowedParams() {
        return array(
            'pageID' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            ),
            'order' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'boolean'
            ),
            'pagerPage' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            ),
            'showForm' => array(
                ApiBase::PARAM_REQUIRED => false,
                ApiBase::PARAM_TYPE => 'integer'
            )
        );
    }
}