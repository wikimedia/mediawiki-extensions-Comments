<?php
/**
 * A special page for displaying the list of users whose comments you're
 * ignoring.
 * @file
 * @ingroup Extensions
 */
class CommentIgnoreList extends SpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'CommentIgnoreList' );
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
		return (bool)$this->getUser()->isLoggedIn();
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

		$action = $request->getVal( 'action' );

		if ( $user_name == '' ) {
			$output .= $this->displayCommentBlockList();
		} else {
			if ( $request->wasPosted() ) {
				// Check for cross-site request forgeries (CSRF)
				if ( !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
					$out->addWikiMsg( 'sessionfailure' );
					return;
				}

				if ( $action === 'delete-block' ) {
					self::unblock( $user, $user_name );

					$output .= $this->displayCommentBlockList();
				} elseif ( $action === 'add-block' ) {
					self::block( $user, $request->getInt( 'commentID' ) );

					$output .= $this->displayCommentBlockList();
				}
			} elseif ( $action === 'add-block' ) {
				// No-JS entry point for the "block a user's comments" action
				$output .= $this->confirmCommentBlockAdd();
			} else {
				$output .= $this->confirmCommentBlockDelete();
			}
		}

		$out->addHTML( $output );
	}

	/**
	 * Block comments from a certain user when we have the ID number of the comment
	 * in question.
	 *
	 * @param User $blockingUser User who is blocking another person's comments
	 * @param int $commentID
	 * @return bool Operation status (true on success, false on failure)
	 */
	public static function block( User $blockingUser, int $commentID ) {
		$dbr = wfGetDB( DB_REPLICA );
		$s = $dbr->selectRow(
			'Comments',
			[ 'Comment_actor' ],
			[ 'CommentID' => $commentID ],
			__METHOD__
		);

		$retVal = false;
		if ( $s !== false ) {
			$blockedUser = User::newFromActorId( $s->Comment_actor );

			if ( $blockedUser && $blockedUser instanceof User ) {
				CommentFunctions::blockUser( $blockingUser, $blockedUser );

				if ( class_exists( 'UserStatsTrack' ) ) {
					$userID = $blockedUser->getId();
					$username = $blockedUser->getName();

					$stats = new UserStatsTrack( $userID, $username );
					$stats->incStatField( 'comment_ignored' );
				}

				$retVal = true;
			}
		}

		return $retVal;
	}

	/**
	 * Unblock a person's comments, either via their user name or IP address.
	 *
	 * @param User $unblockingUser User who is unblocking another person's comments
	 * @param string $user_name User name for registered users, IP address for anons
	 * @return bool Operation status (true on success, false on failure)
	 */
	public static function unblock( User $unblockingUser, $user_name ) {
		$retVal = false;
		$user_name = htmlspecialchars_decode( $user_name );
		$user_id = User::idFromName( $user_name );
		// Anons can be comment-blocked, but idFromName returns nothing
		// for an anon, so...
		if ( !$user_id ) {
			$user_id = 0;
		}

		// This is bugged for anons b/c User::newFromName() does NOT return a valid User object when $user_name is an IP address!
		$blockedUser = User::newFromName( $user_name );
		if ( $blockedUser instanceof User ) {
			CommentFunctions::deleteBlock( $unblockingUser, $blockedUser );
			$retVal = true;
		} else {
			// @todo FIXME: duplicates CommentFunctions#deleteBlock's internals to bypass
			// the fact that function wants User objects and we can't seem to construct
			// one for $user_name when $user_name is an IP address...yet anons _do_ have
			// actor (but not user!) IDs; in fact, giving anons actor IDs is the very
			// raison d'être for the actor stuff...
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'Comments_block',
				[
					'cb_actor' => $unblockingUser->getActorId(),
					'cb_actor_blocked' => (int)$dbw->selectField( 'actor', 'actor_id', [ 'actor_name' => $user_name ], __METHOD__ )
				],
				__METHOD__
			);
		}

		// Update social statistics
		if ( $user_id && class_exists( 'UserStatsTrack' ) ) {
			$stats = new UserStatsTrack( $user_id, $user_name );
			$stats->decStatField( 'comment_ignored' );
		}

		return $retVal;
	}

	/**
	 * Displays the list of users whose comments you're ignoring.
	 *
	 * @return string HTML
	 */
	private function displayCommentBlockList() {
		$lang = $this->getLanguage();
		$title = $this->getPageTitle();

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'Comments_block', 'actor' ],
			[ 'cb_actor_blocked', 'cb_date' ],
			[ 'cb_actor' => $this->getUser()->getActorId() ],
			__METHOD__,
			[ 'ORDER BY' => 'actor_name' ],
			[ 'actor' => [ 'JOIN', 'actor_id = cb_actor' ] ]
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			$out = '<ul>';
			foreach ( $res as $row ) {
				$user = User::newFromActorId( $row->cb_actor_blocked );
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
				$this->msg( 'comments-ignore-no-users' )->text() . '</div>';
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
					Html::hidden( 'action', 'delete-block' ) . "\n" .
					Html::hidden( 'token', $this->getUser()->getEditToken() ) . "\n" .
					'<input type="submit" class="site-button" value="' . $this->msg( 'comments-ignore-unblock' )->escaped() . '" onclick="document.comment_block.submit()" />
					<input type="button" class="site-button" value="' . $this->msg( 'comments-ignore-cancel' )->escaped() . '" onclick="history.go(-1)" />
				</form>
			</div>';
		return $out;
	}

	/**
	 * Asks for a confirmation when you're about to block someone's comments.
	 * Unlike the above method, this is only primarily used by no-JS users and the JS
	 * handles this for most users.
	 *
	 * @return string HTML
	 */
	private function confirmCommentBlockAdd() {
		$request = $this->getRequest();
		$user_name = $request->getVal( 'user' );
		$commentID = $request->getInt( 'commentID' );

		if ( !User::isIP( $user_name ) ) {
			$msg = $this->msg( 'comments-block-warning-user', $user_name )->escaped();
		} else {
			$msg = $this->msg( 'comments-block-warning-anon' )->escaped();
		}

		// @todo FIXME: very "smart" to have an onclick attr on what is literally the *no-JS* form...
		$out = '<div class="comment_blocked_user">' .
				$msg .
			'</div>
			<div>
				<form action="" method="post" name="comment_block">' .
					Html::hidden( 'user', $user_name ) . "\n" .
					Html::hidden( 'action', 'add-block' ) . "\n" .
					Html::hidden( 'commentID', $commentID ) . "\n" .
					Html::hidden( 'token', $this->getUser()->getEditToken() ) . "\n" .
					'<input type="submit" class="site-button" value="' . $this->msg( 'comments-ignore-block' )->escaped() . '" />
					<input type="button" class="site-button" value="' . $this->msg( 'comments-ignore-cancel' )->escaped() . '" onclick="history.go(-1)" />
				</form>
			</div>';
		return $out;
	}
}
