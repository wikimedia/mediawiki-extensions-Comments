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

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
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
		if ( $user->getID() == 0 && $user_name == '' ) {
			$loginPage = SpecialPage::getTitleFor( 'Userlogin' );
			$out->redirect( $loginPage->getLocalURL( 'returnto=Special:CommentIgnoreList' ) );
			return false;
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
				$user_id = User::idFromName( $user_name );
				// Anons can be comment-blocked, but idFromName returns nothing
				// for an anon, so...
				if ( !$user_id ) {
					$user_id = 0;
				}
				$c = new Comment( 0, $this->getContext() );
				$c->deleteBlock( $user->getID(), $user_id );
				if ( $user_id && class_exists( 'UserStatsTrack' ) ) {
					$stats = new UserStatsTrack( $user_id, $user_name );
					$stats->decStatField( 'comment_ignored' );
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
	 * @return HTML
	 */
	function displayCommentBlockList() {
		$lang = $this->getLanguage();
		$title = $this->getTitle();

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'Comments_block',
			array( 'cb_user_name_blocked', 'cb_date' ),
			array( 'cb_user_id' => $this->getUser()->getID() ),
			__METHOD__,
			array( 'ORDER BY' => 'cb_user_name' )
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			$out = '<ul>';
			foreach ( $res as $row ) {
				$user_title = Title::makeTitle( NS_USER, $row->cb_user_name_blocked );
				$out .= '<li>' . $this->msg(
					'comments-ignore-item',
					$user_title->escapeFullURL(),
					$user_title->getText(),
					$lang->timeanddate( $row->cb_date ),
					$title->escapeFullURL( 'user=' . $user_title->getText() )
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
	 * @return HTML
	 */
	function confirmCommentBlockDelete() {
		$user_name = $this->getRequest()->getVal( 'user' );

		$out = '<div class="comment_blocked_user">' .
				$this->msg( 'comments-ignore-remove-message', $user_name )->parse() .
			'</div>
			<div>
				<form action="" method="post" name="comment_block">' .
					Html::hidden( 'user', $user_name ) . "\n" .
					Html::hidden( 'token', $this->getUser()->getEditToken() ) . "\n" .
					'<input type="button" class="site-button" value="' . $this->msg( 'comments-ignore-unblock' )->text() . '" onclick="document.comment_block.submit()" />
					<input type="button" class="site-button" value="' . $this->msg( 'comments-ignore-cancel' )->text() . '" onclick="history.go(-1)" />
				</form>
			</div>';
		return $out;
	}
}