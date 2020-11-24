<?php
/**
 * Basic backend for performing comment-related actions without JavaScript.
 * This page will essentially be never used by users who have JavaScript enabled.
 *
 * If accessed so that the "action" URL parameter's value is 0 _and_ the request
 * was *not* a POST one, the page will display a super generic error message, prompting
 * the user to go back to the Main Page.
 *
 * All valid requests to this page, whether GET or POST, will have the action parameter
 * set and its value will be greater than 0; other parameters depend on the desired action.
 *
 * @file
 * @ingroup Extensions
 * @date 16 November 2020
 */
class CommentAction extends UnlistedSpecialPage {
	// Because nobody likes magic numbers...
	private const ACTION_VOTE = 1;
	private const ACTION_GET_NEW_COMMENT_LIST = 2;
	private const ACTION_ADD_COMMENT = 3;
	private const ACTION_GET_LATEST_COMMENT_ID = 4;
	private const ACTION_DELETE_COMMENT = 5;

	public function __construct() {
		parent::__construct( 'CommentAction' );
	}

	public function execute( $par ) {
		$context = $this->getContext();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		$isLoggedIn = $user->isLoggedIn();
		// SocialProfile is still _not_ a mandatory dependency for Comments!
		if ( class_exists( 'UserStatsTrack' ) && $isLoggedIn ) {
			$stats = new UserStatsTrack( $user );
		} else {
			$stats = false;
		}

		$posted = $request->wasPosted();
		$action = $request->getVal( 'action' );
		// @todo FIXME: if action is 'purge' and the request was POSTed, overwrite it with the correct value
		// Where does action=purge come from, you ask? From CommentsPage#displayForm, of course!
		// Why? I have no idea!
		if ( $action === 'purge' && $posted ) {
			$action = self::ACTION_ADD_COMMENT;
		} else {
			// Cast it to an int because now we know it's not that stupid special case :)
			$action = (int)$action;
		}

		$pageId = $request->getInt( 'pageId' );
		$commentId = $request->getInt( 'commentid' );
		$vote = $request->getInt( 'vt' );
		$antiCSRFTokenOK = $user->matchEditToken( $request->getVal( 'token' ) );

		// Vote for a comment
		// @todo FIXME: ensure that this implementation is on par w/ CommentVoteAPI.php
		// and then centralize & deduplicate the implementations to reduce unnecessary code duplication
		if (
			$action === self::ACTION_VOTE &&
			is_numeric( $commentId ) &&
			$vote &&
			$isLoggedIn &&
			$antiCSRFTokenOK
			// Note: no POST check here. This is intentional.
		) {
			$dbr = wfGetDB( DB_MASTER );
			$row = $dbr->selectRow(
				'Comments',
				[ 'Comment_Page_ID', 'Comment_actor' ],
				[ 'CommentID' => $commentId ],
				__METHOD__
			);
			if ( $row ) {
				$pageID = $row->Comment_Page_ID;
				$comment = new CommentsPage( $pageID, $context );
				$comment->CommentID = $commentId;

				// Do the actual voting
				$c = Comment::newFromID( $commentId );
				if ( $c instanceof Comment ) {
					$c->vote( $vote );
					// $output = $c->getScoreHTML();
				}

				// $comment->setVoting( $_POST['vg'] );
				// $comment->addVote();

				if ( $c && $stats ) {
					// must update stats for user doing the voting
					$stats->incStatField( ( $vote === 1 ? 'comment_give_plus' : 'comment_give_neg' ) );

					// also must update the stats for user receiving the vote
					$stats_comment_owner_user = User::newFromActorId( $row->comment_actor );
					if ( $stats_comment_owner_user && $stats_comment_owner_user instanceof User ) {
						$stats_comment_owner = new UserStatsTrack( $stats_comment_owner_user );
						$stats_comment_owner->incStatField( ( $vote === 1 ? 'comment_plus' : 'comment_neg' ) );
					}
				}

				// echo $output;
				// Redirect back to source page here now that we're all done
				$out->redirect( Title::newFromID( $pageID )->getFullURL() );
				return;
			}
		}

		// Get new comment list
		$showForm = $request->getInt( 'shwform' );
		$orderBy = $request->getVal( 'ord' );
		if ( $action === self::ACTION_GET_NEW_COMMENT_LIST && is_numeric( $pageId ) ) {
			$comment = new CommentsPage( $pageId, $context );
			$comment->setOrderBy( $orderBy );
			$output = ''; // I guess?
			if ( $showForm == 1 ) {
				$output .= $comment->displayOrderForm();
			}
			$output .= $comment->display();
			if ( $showForm == 1 ) {
				$output .= $comment->displayForm();
			}
			echo $output;
		}

		// Add new comment (as a reply to an existing comment)
		// CommentsPage#displayForm POSTs here!
		// @todo FIXME: functionally duplicates CommentSubmitAPI.php; come up w/ a centralized location for the code to reduce duplication!
		$commentText = $request->getVal( 'commentText' );
		$parentID = $request->getInt( 'commentParentId', 0 );
		$title = Title::newFromId( $pageId );
		if (
			$action === self::ACTION_ADD_COMMENT &&
			$commentText != '' &&
			!$user->isBlocked() &&
			$user->isAllowed( 'comment' ) &&
			$title instanceof Title &&
			$antiCSRFTokenOK
		) {
			$comment = new CommentsPage( $pageId, $context );
			if (
				$user->isAllowed( 'commentadmin' ) || !CommentFunctions::isSpam( $commentText ) ||
				$user->isAllowed( 'commentlinks' ) || !CommentFunctions::haveLinks( $commentText )
			) {
				Comment::add( $commentText, $comment, $user, $parentID );
			}

			if ( $stats ) {
				$stats->incStatField( 'comment' );
			}

			// Redirect back to source page here now that we're all done
			$out->redirect( $title->getFullURL() );
			return;
		}

		// Get latest comment ID
		if ( $action === self::ACTION_GET_LATEST_COMMENT_ID && is_numeric( $pageId ) ) {
			$comment = new CommentsPage( $pageId, $context );
			echo $comment->getLatestCommentID();
		}

		// Comment deletion
		if ( $action === self::ACTION_DELETE_COMMENT && !$posted ) {
			// @todo FIXME: yes, I'm reusing a message here and it's kinda dirty
			$out->setPageTitle( $this->msg( 'comments-delete-link' ) );
			// Display confirmation form (anti-CSRF measure)
			$out->addHTML( $this->showConfirmDeleteForm( $commentId ) );
			return;
		} elseif ( $action === self::ACTION_DELETE_COMMENT && $posted ) {
			// Delete the requested comment
			// @todo FIXME: functionally duplicates CommentDeleteAPI.php's internals
			$comment = Comment::newFromID( $commentId );
			if (
				!$user->isBlocked() &&
				(
					$user->isAllowed( 'commentadmin' ) ||
					$user->isAllowed( 'comment-delete-own' ) && $comment->isOwner( $user )
				)
			) {
				$comment->delete();

				// Redirect back to source page here now that we're all done
				$out->redirect( Title::newFromID( $comment->page->id )->getFullURL() );
				return;
			}
		}

		if ( $action === 0 && !$posted ) {
			// Basic error page for users who somehow end up here but probably
			// shouldn't be here...
			$out->setPageTitle( $this->msg( 'error' ) );
			$out->addHTML( $out->addReturnTo( Title::newMainPage() ) );
		} elseif ( $action > 0 ) {
			// This line removes the navigation and everything else from the
			// page, if you don't set it, you get what looks like a regular wiki
			// page, with the body you defined above.
			$out->setArticleBodyOnly( true );
		}
	}

	/**
	 * Show the "do you REALLY want to delete this comment?" form.
	 *
	 * @param int $commentId
	 * @return string HTML
	 */
	private function showConfirmDeleteForm( $commentId ) {
		$form = '';
		$user = $this->getUser();
		$comment = Comment::newFromID( $commentId );

		// Do a quick sanity check before proceeding further...
		if ( $comment === null ) {
			$form .= $this->msg( 'comments-error-no-such-comment', $commentId )->escaped();
			$form .= $this->getOutput()->addReturnTo( Title::newMainPage() );
			return $form;
		}

		// CSS!
		$this->getOutput()->addModuleStyles( 'ext.comments.css' );

		$form .= '<form method="post" name="delete-comment" action="">';
		$form .= $this->msg( 'comments-delete-warning' )->parseAsBlock();
		$form .= '<br />';

		$form .= $comment->showComment(
			/* $hide */false,
			/* $container_class */'full',
			/* $blockList */[],
			/* $anonList */[]
		);
		$form .= '<br />';

		$form .= Html::hidden( 'token', $user->getEditToken() );
		$form .= Html::hidden( 'pageId', $comment->page->id );
		$form .= Html::hidden( 'action', self::ACTION_DELETE_COMMENT );
		$form .= Html::submitButton( $this->msg( 'delete' )->escaped(), [ 'name' => 'wpSubmit', 'class' => 'site-button' ] );
		$form .= '</form>';

		return $form;
	}

}
