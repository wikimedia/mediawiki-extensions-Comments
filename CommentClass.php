<?php
/**
 * Comment class
 * Functions for managing comments and everything related to them, including:
 * -blocking comments from a given user
 * -counting the total amount of comments in the database
 * -displaying the form for adding a new comment
 * -getting all comments for a given page
 *
 * @file
 * @ingroup Extensions
 */
class Comment extends ContextSource {
	/**
	 * @var Integer: page ID (page.page_id) of the page where the <comments />
	 *               tag is in
	 */
	public $PageID = 0; // @TODO remove, access parent CommentPage

	/**
	 * @var Integer: total amount of comments by distinct commenters that the
	 *               current page has
	 */
	public $CommentTotal = 0;

	/**
	 * @var String: text of the current comment
	 */
	public $CommentText = null;

	public $CommentDate = null; // @todo FIXME/CHECKME: unused, remove this?

	/**
	 * @var Integer: internal ID number (Comments.CommentID DB field) of the
	 *               current comment that we're dealing with
	 */
	public $CommentID = 0;

	/**
	 * @var Integer: ID of the parent comment, if this is a child comment
	 */
	public $CommentParentID = 0;

	public $CommentVote = 0;

	/**
	 * @var Integer: comment score (SUM() of all votes) of the current comment
	 */
	public $CommentScore = 0;

	/**
	 * Constructor - set the page ID
	 *
	 * @param int $pageID ID number of the current page
	 * @param IContextSource $context
	 */
	public function __construct( $pageID, $context = null ) {
		$this->PageID = intval( $pageID );
		if ( $context ) {
			// Automatically falls back to
			// RequestContext::getMain() if not provided
			// @todo This should be made non-optional in the future
			$this->setContext( $context );
		}
	}

	/**
	 * @TODO document
	 *
	 * @param $commentText
	 */
	function setCommentText( $commentText ) {
		$this->CommentText = $commentText;
	}

	/**
	 * @TODO document
	 *
	 * @param $comment_text
	 * @return mixed|string
	 * @throws MWException
	 */
	function getCommentText( $comment_text ) {
		global $wgParser;

		$comment_text = trim( str_replace( '&quot;', "'", $comment_text ) );
		$comment_text_parts = explode( "\n", $comment_text );
		$comment_text_fix = '';
		foreach ( $comment_text_parts as $part ) {
			$comment_text_fix .= ( ( $comment_text_fix ) ? "\n" : '' ) . trim( $part );
		}

		if ( $this->getTitle()->getArticleID() > 0 ) {
			$comment_text = $wgParser->recursiveTagParse( $comment_text_fix );
		} else {
			$comment_text = $this->getOutput()->parse( $comment_text_fix );
		}

		// really bad hack because we want to parse=firstline, but don't want wrapping <p> tags
		if ( substr( $comment_text, 0 , 3 ) == '<p>' ) {
			$comment_text = substr( $comment_text, 3 );
		}

		if ( substr( $comment_text, strlen( $comment_text ) -4 , 4 ) == '</p>' ) {
			$comment_text = substr( $comment_text, 0, strlen( $comment_text ) -4 );
		}

		// make sure link text is not too long (will overflow)
		// this function changes too long links to <a href=#>http://www.abc....xyz.html</a>
		$comment_text = preg_replace_callback(
			"/(<a[^>]*>)(.*?)(<\/a>)/i",
			array( 'CommentFunctions', 'cutCommentLinkText' ),
			$comment_text
		);

		return $comment_text;
	}

	/**
	 * Set comment ID to $commentID.
	 *
	 * @param int $commentID Comment ID
	 */
	function setCommentID( $commentID ) {
		$this->CommentID = intval( $commentID );
	}

	/**
	 * @param int $parentID Parent ID number
	 */
	function setCommentParentID( $parentID ) {
		if ( $parentID ) {
			$this->CommentParentID = intval( $parentID );
		} else {
			$this->CommentParentID = 0;
		}
	}

	/**
	 * Sets the value of $name to boolean true/false.
	 *
	 * @param string $name Variable name
	 * @param string $value 'YES', 1 or 'NO' or 0
	 */
	function setBool( $name, $value ) {
		if ( $value ) {
			if ( strtoupper( $value ) == 'YES' || strtoupper( $value ) == 1 ) {
				$this->$name = 1;
			} else {
				$this->$name = 0;
			}
		}
	}

	/**
	 * Adds the comment and all necessary info into the Comments table in the
	 * database.
	 */
	function add() {
		global $wgCommentsInRecentChanges;
		$dbw = wfGetDB( DB_MASTER );

		$text = $this->CommentText;
		wfSuppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		$dbw->insert(
			'Comments',
			array(
				'Comment_Page_ID' => $this->PageID,
				'Comment_Username' => $this->getUser()->getName(),
				'Comment_user_id' => $this->getUser()->getId(),
				'Comment_Text' => $text,
				'Comment_Date' => $commentDate,
				'Comment_Parent_ID' => $this->CommentParentID,
				'Comment_IP' => $_SERVER['REMOTE_ADDR']
			),
			__METHOD__
		);
		$commentId = $dbw->insertId();
		$dbw->commit(); // misza: added this
		$this->CommentID = $commentId;
		$this->clearCommentListCache();

		// Add a log entry.
		$pageTitle = Title::newFromID( $this->PageID );

		$logEntry = new ManualLogEntry( 'comments', 'add' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $pageTitle );
		$logEntry->setComment( $text );
		$logEntry->setParameters( array(
			'4::commentid' => $commentId
		) );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );

		wfRunHooks( 'Comment::add', array( $this, $commentId, $this->PageID ) );
	}

	/**
	 * Gets the score for this comment from the database table Comments_Vote
	 *
	 * @return int
	 */
	function getCommentScore() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_Vote',
			array( 'SUM(Comment_Vote_Score) AS CommentScore' ),
			array( 'Comment_Vote_ID' => $this->CommentID ),
			__METHOD__
		);
		if ( $s !== false ) {
			$this->CommentScore = $s->CommentScore;
		}
		return $this->CommentScore;
	}

	/**
	 * Gets the vote count for this comment from the database table Comments_Vote
	 *
	 * @param int $vote 1 for positive votes, -1 for negative votes
	 * @return int
	 */
	function getCommentVoteCount( $vote ) {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_Vote',
			array( 'COUNT(*) AS CommentVoteCount' ),
			array(
				'Comment_Vote_ID' => $this->CommentID,
				'Comment_Vote_Score' => $vote
			),
			__METHOD__
		);
		if ( $s !== false ) {
			$voteCount = $s->CommentVoteCount;
		}
		return $voteCount;
	}

	/**
	 * Adds a vote for a comment if the user hasn't voted for said comment yet.
	 */
	function addVote() {
		global $wgMemc;
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->UserAlreadyVoted() == false ) {
			wfSuppressWarnings();
			$commentDate = date( 'Y-m-d H:i:s' );
			wfRestoreWarnings();
			$dbw->insert(
				'Comments_Vote',
				array(
					'Comment_Vote_id' => $this->CommentID,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
					'Comment_Vote_Score' => $this->CommentVote,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				),
				__METHOD__
			);
			$dbw->commit();

			// update cache voted list
			$voted = array();
			$key = wfMemcKey( 'comment', 'voted', $this->PageID, 'user_id', $this->getUser()->getID() );
			$voted = $wgMemc->get( $key );
			$voted[] = $this->CommentID;
			$wgMemc->set( $key, $voted );

			// update cache for comment list
			// should perform better than deleting cache completely since Votes happen more frequently
			$key = wfMemcKey( 'comment', 'list', $this->PageID );
			$comments = $wgMemc->get( $key );
			if ( $comments ) {
				foreach ( $comments as &$comment ) {
					if ( $comment['CommentID'] == $this->CommentID ) {
						$comment['Comment_Score'] = $comment['Comment_Score'] + $this->CommentVote;
						if ( $this->CommentVote == 1 ) {
							$comment['CommentVotePlus'] = $comment['CommentVotePlus'] + 1;
						}
						if ( $this->CommentVote == -1 ) {
							$comment['CommentVoteMinus'] = $comment['CommentVoteMinus'] + 1;
						}
					}
				}
				$wgMemc->set( $key, $comments );
			}

			$this->updateCommentVoteStats();
		}
	}

	/**
	 * @TODO document
	 *
	 * @throws DBUnexpectedError
	 */
	function updateCommentVoteStats() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'Comments',
			/* SET */array(
				'Comment_Plus_Count' => $this->getCommentVoteCount( 1 ),
				'Comment_Minus_Count' => $this->getCommentVoteCount( -1 )
			),
			/* WHERE */array( 'CommentID' => $this->CommentID ),
			__METHOD__
		);
		$dbw->commit();
	}

	/**
	 * Checks if the user has already voted for a comment.
	 *
	 * @return bool True if user has voted, otherwise false
	 */
	function UserAlreadyVoted() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_Vote',
			array( 'Comment_Vote_ID' ),
			array(
				'Comment_Vote_ID' => $this->CommentID,
				'Comment_Vote_Username' => $this->getUser()->getName()
			),
			__METHOD__
		);
		if ( $s !== false ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @TODO document
	 *
	 * @param $vote
	 */
	function setCommentVote( $vote ) {
		if ( $vote < 0 ) {
			$vote = -1;
		} else {
			$vote = 1;
		}
		$this->CommentVote = $vote;
	}

	/**
	 * @TODO document
	 *
	 * @param $pagerPage
	 */
	function setCurrentPagerPage( $pagerPage ) {
		$this->CurrentPagerPage = intval( $pagerPage );
	}

	/**
	 * Purge caches (memcached, parser cache and Squid cache)
	 */
	function clearCommentListCache() {
		global $wgMemc;
		$wgMemc->delete( wfMemcKey( 'comment', 'list', $this->PageID ) );

		$pageTitle = Title::newFromID( $this->PageID );
		if ( is_object( $pageTitle ) ) {
			$pageTitle->invalidateCache();
			$pageTitle->purgeSquid();
		}
	}

	/**
	 * Deletes entries from Comments and Comments_Vote tables and clears caches
	 */
	function delete() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'Comments',
			array( 'CommentID' => $this->CommentID ),
			__METHOD__
		);
		$dbw->delete(
			'Comments_Vote',
			array( 'Comment_Vote_ID' => $this->CommentID ),
			__METHOD__
		);
		$dbw->commit();

		// Log the deletion to Special:Log/comments.
		global $wgCommentsInRecentChanges;
		$logEntry = new ManualLogEntry( 'comments', 'delete' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::newFromId( $this->PageID ) );
		$logEntry->setParameters( array(
			'4::commentid' => $this->CommentID
		) );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );

		// Clear memcache & Squid cache
		$this->clearCommentListCache();

		// Ping other extensions that may have hooked into this point (i.e. LinkFilter)
		wfRunHooks( 'Comment::delete', array( $this, $this->CommentID, $this->PageID ) );
	}

	/**
	 * @TODO document
	 *
	 * @param $x
	 * @param $y
	 * @return int
	 */
	public static function sortCommentList( $x, $y ) {
		if ( $x['thread'] == $y['thread'] ) {
			if ( $x['timestamp'] == $y['timestamp'] ) {
				return 0;
			} elseif ( $x['timestamp'] < $y['timestamp'] ) {
				return -1;
			} else {
				return 1;
			}
		} elseif ( $x['thread'] < $y['thread'] ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * @TODO document
	 *
	 * @param $commentID
	 * @param $voteType
	 * @return string
	 */
	function getVoteLink( $commentID, $voteType ) {
		global $wgExtensionAssetsPath;

		// Blocked users cannot vote, obviously
		if ( $this->getUser()->isBlocked() ) {
			return '';
		}
		if ( !$this->getUser()->isAllowed( 'comment' ) ) {
			return '';
		}

		$voteLink = '';
		if ( $this->getUser()->isLoggedIn() ) {
			$voteLink .= '<a id="comment-vote-link" data-comment-id="' .
				$commentID . '" data-vote-type="' . $voteType .
				'" data-voting="' . $this->Voting . '" href="javascript:void(0);">';
		} else {
			// Anonymous users need to log in before they can vote
			$login = SpecialPage::getTitleFor( 'Userlogin' );
			// Determine a sane returnto URL parameter, or at least try, and
			// failing that, just take the user to the main page.
			// Fun fact: the getLocalURL() call below used to use
			// $wgOut->getTitle()->getDBkey() but that returns 'GetCommentList'
			// which is so wrong on so many different levels that I don't know
			// where to begin...
			$returnToPageName = Title::newFromId( $this->PageID );
			if ( $returnToPageName instanceof Title ) {
				$returnTo = $returnToPageName->getPrefixedDBkey();
			} else {
				$returnTo = Title::newMainPage()->getPrefixedDBkey();
			}
			$voteLink .=
				"<a href=\"" .
				htmlspecialchars( $login->getLocalURL( array( 'returnto' => $returnTo ) ) ) .
				"\" rel=\"nofollow\">";
		}

		$imagePath = $wgExtensionAssetsPath . '/Comments/images';
		if ( $voteType == 1 ) {
			$voteLink .= "<img src=\"{$imagePath}/thumbs-up.gif\" border=\"0\" alt=\"+\" /></a>";
		} else {
			$voteLink .= "<img src=\"{$imagePath}/thumbs-down.gif\" border=\"0\" alt=\"-\" /></a>";
		}

		return $voteLink;
	}
}
