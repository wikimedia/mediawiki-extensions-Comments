<?php

class FormInterface extends QuickTemplate {

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
	 * the OOUI\HiddenInputWidget isn't available
	 * in the oojs-ui package that comes with MediaWiki 1.29,
	 * but available with 1.30.0. For now, let's create 
	 * our own OOUI hidden input widget to handle this, 
	 * and keep everything OOUI-ified.
	 *
	 * @todo: Replace when 1.30.0 becomes the stable version
	 *
	 * @param $name
	 * @param $value
	 * @return $hidden
	 */
	public function getHidden( $name, $value ) {
		$hidden = new OOUI\Tag( 'input' );
		$hidden->setAttributes( [
			'type' => 'hidden',
			'id' => '',
			'name' => $name,
			'value' => $value
		] );
		
		return $hidden;
	}

	public function getForm() {
		self::getPermissions();

		$user = $this->data['user'];

		/**
		 * @todo: No PHP version of a OOUI\MultilineTextInputWidget yet,
		 * so use OOUI\TextInputWidget and 'multiline' => true for now
		 *
		 * @see: https://phabricator.wikimedia.org/T166686
		 */
		$textarea = new OOUI\TextInputWidget( [
			'infusable' => true,
			'multiline' => true,
			'placeholder' => wfMessage( 'comments-textarea-placeholder' ),
			'rows' => 8,
			'id' => 'CommentsTextareaWidget',
			'name' => 'commentText'
		] );
		
		$form = new OOUI\FormLayout( [
			'infusable' => true,
			'method' => 'post',
			'action' => '',
			'id' => 'CommentsFormLayout',
			'name' => 'commentForm',
			'items' => [
				$textarea,
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