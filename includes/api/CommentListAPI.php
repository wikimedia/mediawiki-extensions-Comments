<?php

use MediaWiki\Context\RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

class CommentListAPI extends MediaWiki\Api\ApiBase {

	/** @var ReadOnlyMode */
	private $readOnlyMode;

	/**
	 * @param MediaWiki\Api\ApiMain $mainModule
	 * @param string $moduleName
	 * @param ReadOnlyMode $readOnlyMode
	 */
	public function __construct(
		MediaWiki\Api\ApiMain $mainModule,
		$moduleName,
		ReadOnlyMode $readOnlyMode
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->readOnlyMode = $readOnlyMode;
	}

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
		if ( $voting !== null ) {
			$commentsPage->setVoting( $voting );
		}

		$output = $form = $anchor = '';

		// For the first call we add extra markup like anchors, form and comments block wrapper
		if ( $isFirstLoad ) {
			$anchor .= '<a id="end" rel="nofollow"></a>';
			if ( !$this->readOnlyMode->isReadOnly() ) {
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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'order' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'boolean'
			],
			'pagerPage' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'showForm' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer'
			]
		];
	}
}
