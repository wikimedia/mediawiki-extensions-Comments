<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}
/**
 * Template for handling the comment form
 * @ingroup Templates
 */
class CommentFormTemplate extends QuickTemplate {
	public function execute() {
		$user = $this->data['user'];
		if ( $this->data['commentPage']->allow ) {
			$pos = strpos(
				strtoupper( addslashes( $this->data['commentPage']->allow ) ),
				strtoupper( addslashes( $user->getName() ) )
			);
		}

		// Conditional support for SocialProfile
		// which allows to use its avatar functions
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $user->getId(), 'ml' );
			$avatarURL = $avatar->getAvatarURL( [ 'class' => 'avatar' ] );
		} else {
			$avatarURL = '';
		}

		// 'comment' user right is required to add new comments
		if ( !$user->isAllowed( 'comment' ) ) {
			echo $this->getMsg( 'comments-not-allowed' )->parse();
		} else {
			// Blocked users can't add new comments under any conditions...
			// and maybe there's a list of users who should be allowed to post
			// comments
			if (
				$user->isBlocked() == false &&
				( $this->data['commentPage']->allow == '' || $pos !== false )
			) {
				// store our hidden data
				$hiddenInputData = [
					'action' => 'purge',
					'pageId' => $this->data['commentPage']->id,
					'lastCommentId' => $this->data['commentPage']->getLatestCommentID(),
					'commentParentId' => '',
					$this->data['commentPage']->pageQuery => $this->data['commentPage']->getCurrentPagerPage(),
					'token' => $user->getEditToken()
				];

				foreach ( $hiddenInputData as $name => $value ) {
					$commentFormInput['hidden'] = new OOUI\Tag( 'input' );
					$commentFormInput['hidden']->setAttributes( [
						'infusable' => true,
						'type' => 'hidden',
						'name' => $name,
						'value' => $value
					] );
				}

				$commentFormInput['textarea'] = new OOUI\TextInputWidget( [
					'infusable' => true,
					'multiline' => true,
					'placeholder' => wfMessage( 'comments-textarea-placeholder' ),
					'rows' => 8,
					'id' => 'comment',
					'name' => 'commentText'
				] );

				$commentForm = new OOUI\FormLayout( [
					'infusable' => true,
					'method' => 'post',
					'action' => '',
					'id' => 'comment-form',
					'name' => 'commentForm',
					'items' => [
						$commentFormInput['textarea'],
						$commentFormInput['hidden']
					]
				] );

				echo $commentForm;
			} // if user is not blocked etc.
		} // if user is allowed to comment at all
	} // execute()
} // class
