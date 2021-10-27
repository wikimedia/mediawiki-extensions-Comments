<?php

class CommentListAPI extends ApiBase {

	public function execute() {
		global $wgCommentsSortDescending;

		// Determines if that's an initialization call or a pagination request
		$isFirstLoad = $this->getMain()->getVal( 'showForm' );
		// Users specifically allows to comment
		$allow = $this->getMain()->getVal( 'allow' );
		// Voting feature flag
		$voting = $this->getMain()->getVal( 'voting' );

		$commentsPage = new CommentsPage( $this->getMain()->getVal( 'pageID' ), RequestContext::getMain() );
		if ( !$isFirstLoad ) {
			$commentsPage->orderBy = $this->getMain()->getVal( 'order' );
			$commentsPage->currentPagerPage = $this->getMain()->getVal( 'pagerPage' );
		}
		$commentsPage->allow = $allow;
		$commentsPage->setVoting( $voting );

		$output = $form = $anchor = '';

		// For the first call we add extra markup like anchors, form and comments block wrapper
		if ( $isFirstLoad ) {
			$anchor .= '<a id="end" rel="nofollow"></a>';
			if ( !wfReadOnly() ) {
				$form .= $commentsPage->displayOrderForm();
				$form .= $commentsPage->displayForm();
			} else {
				$form = wfMessage( 'comments-db-locked' )->parse();
			}
			$output .= '<div id="allcomments">';
		}

		$output .= $commentsPage->display();

		if ( $isFirstLoad ) {
			$output .= '</div>'; // allcomments
		}

		// It's only necessary to setup the order for the first call
		if ( $isFirstLoad && $wgCommentsSortDescending ) {
			$output = $anchor . $form . $output;
		} else {
			$output = $output . $form . $anchor;
		}

		$result = $this->getResult();
		$result->addValue( $this->getModuleName(), 'html', $output );
		return true;
	}

	public function getAllowedParams() {
		return [
			'pageID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'order' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'boolean'
			],
			'pagerPage' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'showForm' => [
				ApiBase::PARAM_REQUIRED => false,
				ApiBase::PARAM_TYPE => 'integer'
			]
		];
	}
}
