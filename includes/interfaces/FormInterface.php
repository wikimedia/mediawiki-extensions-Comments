<?php

class FormInterface extends QuickTemplate {

	/**
	 * Properly handle permissions for cases when:
	 * - a user doesn't have the `comment` right
	 * - a user is blocked or not in the list of allowed people to comments
	 * - a user is not logged in
	 */
	public function getPermissions() {
		$user = $this->data['user'];
		if ( $this->data['commentPage']->allow ) {
			$pos = strpos(
				strtoupper( addslashes( $this->data['commentPage']->allow ) ),
				strtoupper( addslashes( $user->getName() ) )
			);
		}

		// is the user allowed to comment?
		if ( !$user->isAllowed( 'comment' ) ) {
			// nope, show a message that they can't
			echo wfMessage( 'comments-not-allowed' )->parse();
			return false;
		}

		// is the user blocked?
		if (
			$user->isBlocked() === false && (
			$this->data['commentPage']->allow === ''
			|| $pos === false )
		) {
			//echo some custom message for blocking
			return false;
		}

		// is the user not logged in?
		if ( !$this->getUser()->isLoggedIn() ) {
			$title['login'] = SpecialPage::getTitleFor( 'Userlogin' );
			$title['register'] = SpecialPage::getTitleFor( 'Userlogin', 'signup' );
			echo wfMessage(
				'comments-anon-message',
				htmlspecialchars( $title['login']->getFullURL() ),
				htmlspecialchars( $title['register']->getFullURL() )
			)->text();

			return false;
		}
	}

	/**
	 * Retrieves the avatar from SocialProfile,
	 * if SocialProfile is installed.
	 */
	public function getAvatar() {
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $user->getId(), 'ml' );
			$avatarUrl = $avatar->getAvatarURL( [ 'class' => 'avatar comments-form-avatar' ] );
		} else {
			$avatarUrl = '';
		}

		return $avatarUrl;
	}

	/**
	 * Quick shortcut to the PHP OOUI\HiddenInputWidget
	 *
	 * @param $name
	 * @param $value
	 * @return OOUI\HiddenInputWidget
	 */
	public function getHidden( $name, $value ) {
		return new OOUI\HiddenInputWidget( [
			'name' => $name,
			'value' => $value
		] );
	}

	/**
	 * Builds and returns an OOUI textarea
	 *
	 * @return OOUI\TextInputWidget
	 */
	public function getTextarea() {
		return new OOUI\TextInputWidget( [
			'infusable' => true,
			'multiline' => true,
			'placeholder' => wfMessage( 'comments-textarea-placeholder' ),
			'rows' => 8,
			'id' => 'CommentsTextareaWidget',
			'name' => 'commentText'
		] );
	}

	/**
	 * Builds the form which wraps around the textarea
	 *
	 * @return OOUI\FormLayout
	 */
	public function getForm() {
		self::getPermissions();

		$user = $this->data['user'];

		$form = new OOUI\FormLayout( [
			'infusable' => true,
			'method' => 'post',
			'action' => '',
			'id' => 'CommentsFormLayout',
			'name' => 'commentForm',
			'items' => [
				self::getTextarea(),
				// hidden input data
				self::getHidden( 'action', 'purge' ),
				self::getHidden( 'pageId', $this->data['commentPage']->id ),
				self::getHidden( 'commentid', '' ),
				self::getHidden( 'lastCommentId', $this->data['commentPage']->getLatestCommentID() ),
				self::getHidden( 'commentParentId', '' ),
				self::getHidden( $this->data['commentPage']->pageQuery, $this->data['commentPage']->getCurrentPagerPage() ),
				self::getHidden( 'token', $user->getEditToken() )
			]
		] );

		return $form;
	}

	public function execute() {
		echo self::getForm();
	}
}