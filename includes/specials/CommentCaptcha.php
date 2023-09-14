<?php
/**
 * Return the HTML for a CAPTCHA. It is used to provide a new CAPTCHA on demand
 * via AJAX when the user requests it.
 *
 * @note Based on the PostComment extension's Special:PostCommentCaptcha special
 *   page, written by various wikiHow developers and licensed under the GPL-2.0-or-later
 *   license.
 *   The only changes here are:
 *   1) renaming the special page (PostCommentCaptcha -> CommentCaptcha)
 *   2) changing the $captcha->getMessage() call to have 'comment' instead of 'edit' as its sole parameter
 *   3) changing the <p> element class name from "postcomment-captcha-help" to "comment-captcha-help"
 * @author wikiHow
 */
class CommentCaptcha extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CommentCaptcha' );
	}

	public function execute( $par ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			$out = $this->getOutput();
			$out->setArticleBodyOnly( true );

			$captcha = ConfirmEditHooks::getInstance();
			$formInformation = $captcha->getFormInformation();
			$formMetainfo = $formInformation;
			unset( $formMetainfo['html'] );
			$captcha->addFormInformationToOutput( $out, $formMetainfo );
			// "To comment, please answer the question that appears below (more info):" text
			// (or equivalent, depending on CAPTCHA type):
			$output = '<p class="comment-captcha-help">' . $captcha->getMessage( 'comment' ) . '</p>';
			// The actual CAPTCHA challenge:
			$output .= $formInformation['html'];

			$out->addHTML( $output );
		}
	}
}
