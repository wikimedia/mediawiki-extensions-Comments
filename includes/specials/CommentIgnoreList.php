<?php
/**
 * A special page for displaying the list of users whose comments you're
 * ignoring.
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;

class CommentIgnoreList extends SpecialPage {
	private UserFactory $userFactory;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct(
		UserFactory $userFactory
	) {
		parent::__construct( 'CommentIgnoreList' );
		$this->userFactory = $userFactory;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	function getGroupName() {
		return 'users';
	}

	/**
	 * Show this special page on Special:SpecialPages only for registered users
	 *
	 * @return bool
	 */
	function isListed() {
		return (bool)$this->getUser()->isRegistered();
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the page or null
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$user_name = $request->getVal( 'user' );

		/**
		 * Redirect anonymous users to Login Page
		 * It will automatically return them to the CommentIgnoreList page
		 */
		if ( $user->getId() == 0 && $user_name == '' ) {
			$loginPage = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $loginPage->getLocalURL( 'returnto=Special:CommentIgnoreList' ) );
			return;
		}

		$out->setPageTitle( $this->msg( 'comments-ignore-title' )->text() );

		$output = ''; // Prevent E_NOTICE

		if ( $user_name == '' ) {
			$output .= $this->displayCommentBlockList();
		} else {
			if ( $request->wasPosted() ) {
				// Check for cross-site request forgeries (CSRF)
				if ( !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
					$out->addWikiMsg( 'sessionfailure' );
					return;
				}

				$user_name = htmlspecialchars_decode( $user_name );
				$blockedUser = $this->userFactory->newFromName( $user_name );

				if ( $blockedUser instanceof User ) {
					CommentFunctions::deleteBlock( $user, $blockedUser );
					// Update social statistics
					// Anons can be comment-blocked
					if ( $blockedUser->isRegistered() && class_exists( 'UserStatsTrack' ) ) {
						$stats = new UserStatsTrack( $blockedUser->getId(), $user_name );
						$stats->decStatField( 'comment_ignored' );
					}
				}

				$output .= $this->displayCommentBlockList();
			} else {
				$output .= $this->confirmCommentBlockDelete();
			}
		}

		$out->addHTML( $output );
	}

	/**
	 * Displays the list of users whose comments you're ignoring.
	 *
	 * @return string HTML
	 */
	private function displayCommentBlockList() {
		$lang = $this->getLanguage();
		$title = $this->getPageTitle();

		$dbr = Comment::getDBHandle( 'read' );
		$res = $dbr->select(
			[ 'Comments_block', 'actor' ],
			[ 'cb_actor_blocked', 'cb_date' ],
			[ 'cb_actor' => $this->getUser()->getActorId() ],
			__METHOD__,
			[ 'ORDER BY' => 'actor_name' ],
			[ 'actor' => [ 'JOIN', 'actor_id = cb_actor' ] ]
		);

		if ( $res->numRows() > 0 ) {
			$out = '<ul>';
			foreach ( $res as $row ) {
				$user = $this->userFactory->newFromActorId( $row->cb_actor_blocked );
				if ( !$user ) {
					continue;
				}
				$user_title = $user->getUserPage();
				$out .= '<li>' . $this->msg(
					'comments-ignore-item',
					htmlspecialchars( $user_title->getFullURL() ),
					$user_title->getText(),
					$lang->timeanddate( $row->cb_date ),
					htmlspecialchars( $title->getFullURL( 'user=' . $user_title->getText() ) )
				)->text() . '</li>';
			}
			$out .= '</ul>';
		} else {
			$out = '<div class="comment_blocked_user">' .
				$this->msg( 'comments-ignore-no-users' )->escaped() . '</div>';
		}
		return $out;
	}

	/**
	 * Asks for a confirmation when you're about to unblock someone's comments.
	 *
	 * @return string HTML
	 */
	private function confirmCommentBlockDelete() {
		$user_name = $this->getRequest()->getVal( 'user' );

		$out = '<div class="comment_blocked_user">' .
				$this->msg( 'comments-ignore-remove-message', $user_name )->parse() .
			'</div>
			<div>
				<form action="" method="post" name="comment_block">' .
					Html::hidden( 'user', $user_name ) . "\n" .
					Html::hidden( 'token', $this->getUser()->getEditToken() ) . "\n" .
					'<input type="submit" class="site-button" value="' . $this->msg( 'comments-ignore-unblock' )->escaped() . '"  />
					<input type="button" class="site-button" value="' . $this->msg( 'comments-ignore-cancel' )->escaped() . '" onclick="history.go(-1)" />
				</form>
			</div>';
		return $out;
	}
}
