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
	 * @var CommentsPage: page of the page the <comments /> tag is in
	 */
	public $page = null;

	/**
	 * @var Integer: total amount of comments by distinct commenters that the
	 *               current page has
	 */
	public $commentTotal = 0;

	/**
	 * @var String: text of the current comment
	 */
	public $text = null;

	public $date = null; // @todo FIXME/CHECKME: unused, remove this?

	/**
	 * @var Integer: internal ID number (Comments.CommentID DB field) of the
	 *               current comment that we're dealing with
	 */
	public $id = 0;

	/**
	 * @var Integer: ID of the parent comment, if this is a child comment
	 */
	public $parentID = 0;

	/**
	 * @TODO document
	 *
	 * @var int
	 */
	public $commentVote = 0;

	/**
	 * @var Integer: comment score (SUM() of all votes) of the current comment
	 */
	public $score = 0;

	/**
	 * @TODO document
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * @TODO document
	 *
	 * @var string
	 */
	public $ip = '';

	/**
	 * @TODO document
	 *
	 * @var int
	 */
	public $userID = 0;

	/**
	 * @TODO document
	 *
	 * @var int
	 */
	public $userPoints = 0;

	/**
	 * @TODO document
	 *
	 * @var int
	 */
	public $votePlus = 0;

	/**
	 * @TODO document
	 *
	 * @var int
	 */
	public $voteMinus = 0;

	/**
	 * @TODO document
	 * Used for sorting
	 *
	 * @var null
	 */
	public $thread = null;

	/**
	 * @TODO document
	 * Used for sorting
	 *
	 * @var null
	 */
	public $timestamp = null;

	/**
	 * Constructor - set the page ID
	 *
	 * @param $page CommentsPage: ID number of the current page
	 * @param IContextSource $context
	 * @param $data: straight from the DB about the comment
	 */
	public function __construct( CommentsPage $page, $context = null, $data ) {
		$this->page = $page;

		$this->setContext( $context );

		$this->username = $data['Comment_Username'];
		$this->ip = $data['Comment_IP'];
		$this->text = $data['Comment_Text'];
		$this->date = $data['Comment_Date'];
		$this->userID = $data['Comment_user_id'];
		$this->userPoints = $data['Comment_user_points'];
		$this->id = $data['CommentID'];
		$this->score = $data['Comment_Score'];
		$this->votePlus = $data['CommentVotePlus'];
		$this->commentVoteMinus = $data['CommentVoteMinus'];
		//$this->commentVote; // @TODO why 3 vars?
		$this->parentID = $data['Comment_Parent_ID'];
		$this->thread = $data['thread'];
		$this->timestamp = $data['timestamp'];
	}

	/**
	 * @TODO document
	 *
	 * @param $text
	 */
	function setText( $text ) {
		$this->CommentText = $text;
	}

	/**
	 * @TODO document
	 *
	 * @param $comment_text
	 * @return mixed|string
	 * @throws MWException
	 */
	function getText() {
		global $wgParser;

		$commentText = trim( str_replace( '&quot;', "'", $this->text ) );
		$comment_text_parts = explode( "\n", $commentText );
		$comment_text_fix = '';
		foreach ( $comment_text_parts as $part ) {
			$comment_text_fix .= ( ( $comment_text_fix ) ? "\n" : '' ) . trim( $part );
		}

		if ( $this->getTitle()->getArticleID() > 0 ) {
			$commentText = $wgParser->recursiveTagParse( $comment_text_fix );
		} else {
			$commentText = $this->getOutput()->parse( $comment_text_fix );
		}

		// really bad hack because we want to parse=firstline, but don't want wrapping <p> tags
		if ( substr( $commentText, 0 , 3 ) == '<p>' ) {
			$commentText = substr( $commentText, 3 );
		}

		if ( substr( $commentText, strlen( $commentText ) -4 , 4 ) == '</p>' ) {
			$commentText = substr( $commentText, 0, strlen( $commentText ) -4 );
		}

		// make sure link text is not too long (will overflow)
		// this function changes too long links to <a href=#>http://www.abc....xyz.html</a>
		$commentText = preg_replace_callback(
			"/(<a[^>]*>)(.*?)(<\/a>)/i",
			array( 'CommentFunctions', 'cutCommentLinkText' ),
			$commentText
		);

		return $commentText;
	}

	/**
	 * Set comment ID to $commentID.
	 *
	 * @param int $id Comment ID
	 */
	function setId( $id ) {
		$this->id = intval( $id );
	}

	/**
	 * @param int $parentID Parent ID number
	 */
	function setParentID( $parentID ) {
		if ( $parentID ) {
			$this->parentID = intval( $parentID );
		} else {
			$this->parentID = 0;
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

		$text = $this->text;
		wfSuppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		$dbw->insert(
			'Comments',
			array(
				'Comment_Page_ID' => $this->page->id,
				'Comment_Username' => $this->getUser()->getName(),
				'Comment_user_id' => $this->getUser()->getId(),
				'Comment_Text' => $text,
				'Comment_Date' => $commentDate,
				'Comment_Parent_ID' => $this->parentID,
				'Comment_IP' => $_SERVER['REMOTE_ADDR']
			),
			__METHOD__
		);
		$commentId = $dbw->insertId();
		$dbw->commit(); // misza: added this
		$this->id = $commentId;
		$this->clearCommentListCache();

		// Add a log entry.
		$pageTitle = Title::newFromID( $this->page->id );

		$logEntry = new ManualLogEntry( 'comments', 'add' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $pageTitle );
		$logEntry->setComment( $text );
		$logEntry->setParameters( array(
			'4::commentid' => $commentId
		) );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );

		wfRunHooks( 'Comment::add', array( $this, $commentId, $this->page->id ) );
	}

	/**
	 * Gets the score for this comment from the database table Comments_Vote
	 *
	 * @return int
	 */
	function getScore() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_Vote',
			array( 'SUM(Comment_Vote_Score) AS CommentScore' ),
			array( 'Comment_Vote_ID' => $this->id ),
			__METHOD__
		);
		if ( $s !== false ) {
			$this->score = $s->commentScore;
		}
		return $this->score;
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
				'Comment_Vote_ID' => $this->id,
				'Comment_Vote_Score' => $vote
			),
			__METHOD__
		);
		$voteCount = '';
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
		if ( $this->userAlreadyVoted() == false ) {
			wfSuppressWarnings();
			$commentDate = date( 'Y-m-d H:i:s' );
			wfRestoreWarnings();
			$dbw->insert(
				'Comments_Vote',
				array(
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
					'Comment_Vote_Score' => $this->commentVote,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				),
				__METHOD__
			);
			$dbw->commit();

			// update cache voted list
			$key = wfMemcKey( 'comment', 'voted', $this->page->id, 'user_id', $this->getUser()->getID() );
			$voted = $wgMemc->get( $key );
			$voted[] = $this->id;
			$wgMemc->set( $key, $voted );

			// update cache for comment list
			// should perform better than deleting cache completely since Votes happen more frequently
			$key = wfMemcKey( 'comment', 'list', $this->page->id );
			$comments = $wgMemc->get( $key );
			if ( $comments ) {
				foreach ( $comments as &$comment ) {
					if ( $comment['CommentID'] == $this->id ) {
						$comment['Comment_Score'] = $comment['Comment_Score'] + $this->commentVote;
						if ( $this->commentVote == 1 ) {
							$comment['CommentVotePlus'] = $comment['CommentVotePlus'] + 1;
						}
						if ( $this->commentVote == -1 ) {
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
			array(
				'Comment_Plus_Count' => $this->getCommentVoteCount( 1 ),
				'Comment_Minus_Count' => $this->getCommentVoteCount( -1 )
			),
			array( 'CommentID' => $this->id ),
			__METHOD__
		);
		$dbw->commit();
	}

	/**
	 * Checks if the user has already voted this comment.
	 *
	 * @return bool True if user has voted, otherwise false
	 */
	function userAlreadyVoted() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_Vote',
			array( 'Comment_Vote_ID' ),
			array(
				'Comment_Vote_ID' => $this->id,
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
		$this->commentVote = $vote;
	}

	/**
	 * @TODO document
	 *
	 * @param $pagerPage
	 */
	function setCurrentPagerPage( $pagerPage ) {
		$this->currentPagerPage = intval( $pagerPage );
	}

	/**
	 * Purge caches (memcached, parser cache and Squid cache)
	 */
	function clearCommentListCache() {
		global $wgMemc;
		$wgMemc->delete( wfMemcKey( 'comment', 'list', $this->page->id ) );

		$pageTitle = Title::newFromID( $this->page->id );
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
			array( 'CommentID' => $this->id ),
			__METHOD__
		);
		$dbw->delete(
			'Comments_Vote',
			array( 'Comment_Vote_ID' => $this->id ),
			__METHOD__
		);
		$dbw->commit();

		// Log the deletion to Special:Log/comments.
		global $wgCommentsInRecentChanges;
		$logEntry = new ManualLogEntry( 'comments', 'delete' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( Title::newFromId( $this->page->id ) );
		$logEntry->setParameters( array(
			'4::commentid' => $this->id
		) );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );

		// Clear memcache & Squid cache
		$this->clearCommentListCache();

		// Ping other extensions that may have hooked into this point (i.e. LinkFilter)
		wfRunHooks( 'Comment::delete', array( $this, $this->id, $this->page->id ) );
	}

	/**
	 * @TODO document and config var/hook/smth
	 *
	 * @param $x
	 * @param $y
	 * @return int
	 */
	public static function sortCommentList( $x, $y ) {
		if ( $x->thread == $y->thread ) {
			if ( $x->timestamp == $y->timestamp ) {
				return 0;
			} elseif ( $x->timestamp < $y->timestamp ) {
				return -1;
			} else {
				return 1;
			}
		} elseif ( $x->thread < $y->thread ) {
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
	function getVoteLink( $voteType ) {
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
				$this->id . '" data-vote-type="' . $voteType .
				'" data-voting="' . $this->page->voting . '" href="javascript:void(0);">';
		} else {
			$login = SpecialPage::getTitleFor( 'Userlogin' ); // Anonymous users need to log in before they can vote
			$returnTo = $this->page->title->getPrefixedDBkey(); // Determine a sane returnto URL parameter

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

	/**
	 * Show the HTML for this comment
	 *
	 * @param $blockList: @TODO document
	 *
	 * @return string: html
	 */
	function display( $blockList, $anonList ) {
		global $wgUserLevels, $wgExtensionAssetsPath;

		$output = '';

		$CommentPosterLevel = '';

		if ( $this->userID != 0 ) {
			$title = Title::makeTitle( NS_USER, $this->username );

			$CommentPoster = '<a href="' . htmlspecialchars( $title->getFullURL() ) .
				'" rel="nofollow">' . $this->username . '</a>';

			$CommentReplyTo = $this->username;

			if ( $wgUserLevels && class_exists( 'UserLevel' ) ) {
				$user_level = new UserLevel( $this->userPoints );
				$CommentPosterLevel = "{$user_level->getLevelName()}";
			}

			$user = User::newFromId( $this->userID );
			$CommentReplyToGender = $user->getOption( 'gender', 'unknown' );
		} else {
			$anonMsg = $this->msg( 'comments-anon-name' )->inContentLanguage()->plain();
			$CommentPoster = $anonMsg . ' #' . $anonList[$this->username];
			$CommentReplyTo = $anonMsg;
			$CommentReplyToGender = 'unknown'; // Undisclosed gender as anon user
		}

		// Comment delete button for privileged users
		$dlt = '';

		if ( $this->getUser()->isAllowed( 'commentadmin' ) ) {
			// $dlt = " | <span class=\"c-delete\"><a href=\"javascript:document.commentform.commentid.value={$comment['CommentID']};document.commentform.submit();\">" .
			$dlt = ' | <span class="c-delete">' .
				'<a href="javascript:void(0);" rel="nofollow" class="comment-delete-link" data-comment-id="' .
				$this->id . '">' .
				$this->msg( 'comments-delete-link' )->plain() . '</a></span>';
		}

		// Reply Link (does not appear on child comments)
		$replyRow = '';
		if ( $this->getUser()->isAllowed( 'comment' ) ) {
			if ( $this->parentID == 0 ) {
				if ( $replyRow ) {
					$replyRow .= wfMessage( 'pipe-separator' )->plain();
				}
				$replyRow .= " | <a href=\"#end\" rel=\"nofollow\" class=\"comments-reply-to\" data-comment-id=\"{$this->id}\" data-comments-safe-username=\"" .
					htmlspecialchars( $CommentReplyTo, ENT_QUOTES ) . "\" data-comments-user-gender=\"" .
					htmlspecialchars( $CommentReplyToGender ) . '">' .
					wfMessage( 'comments-reply' )->plain() . '</a>';
			}
		}

		if ( $this->parentID == 0 ) {
			$container_class = 'full';
			$comment_class = 'f-message';
		} else {
			$container_class = 'reply';
			$comment_class = 'r-message';
		}

		// Display Block icon for logged in users for comments of users
		// that are already not in your block list
		$block_link = '';

		if (
			$this->getUser()->getID() != 0 && $this->getUser()->getID() != $this->userID &&
			!( in_array( $this->userID, $blockList ) )
		) {
			$block_link = '<a href="javascript:void(0);" rel="nofollow" class="comments-block-user" data-comments-safe-username="' .
				htmlspecialchars( $this->username, ENT_QUOTES ) .
				'" data-comments-comment-id="' . $this->id . '" data-comments-user-id="' .
				$this->userID . "\">
					<img src=\"{$wgExtensionAssetsPath}/Comments/images/block.svg\" border=\"0\" alt=\"\"/>
				</a>";
		}

		// If you are ignoring the author of the comment, display message in comment box,
		// along with a link to show the individual comment
		$hide_comment_style = '';

		if ( in_array( $this->username, $blockList ) ) {
			$hide_comment_style = 'display:none;';

			$blockListTitle = SpecialPage::getTitleFor( 'CommentIgnoreList' );

			$output .= "<div id=\"ignore-{$this->id}\" class=\"c-ignored {$container_class}\">\n";
			$output .= wfMessage( 'comments-ignore-message' )->parse();
			$output .= '<div class="c-ignored-links">' . "\n";
			$output .= "<a href=\"javascript:void(0);\" data-comment-id=\"{$this->id}\">" .
				wfMessage( 'comments-show-comment-link' )->plain() . '</a> | ';
			$output .= '<a href="' . htmlspecialchars( $blockListTitle->getFullURL() ) . '">' .
				wfMessage( 'comments-manage-blocklist-link' )->plain() . '</a>';
			$output .= '</div>' . "\n";
			$output .= '</div>' . "\n";
		}

		// Default avatar image, if SocialProfile extension isn't
		// enabled
		global $wgCommentsDefaultAvatar;
		$avatar_img = '<img src="' . $wgCommentsDefaultAvatar . '" alt="" border="0" />';
		// If SocialProfile *is* enabled, then use its wAvatar class
		// to get the avatars for each commenter
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $this->userID, 'ml' );
			$avatar_img = $avatar->getAvatarURL() . "\n";
		}

		$output .= "<div id=\"comment-{$this->id}\" class=\"c-item {$container_class}\" style=\"{$hide_comment_style}\">" . "\n";
		$output .= "<div class=\"c-avatar\">{$avatar_img}</div>" . "\n";
		$output .= '<div class="c-container">' . "\n";

		$output .= '<div class="c-user">' . "\n";

		$output .= "{$CommentPoster}";
		$output .= "<span class=\"c-user-level\">{$CommentPosterLevel}</span> {$block_link}" . "\n";

		wfSuppressWarnings(); // E_STRICT bitches about strtotime()
		$output .= '<div class="c-time">' .
			wfMessage(
				'comments-time-ago',
				CommentFunctions::getTimeAgo( strtotime( $this->date ) )
			)->parse() . '</div>' . "\n";
		wfRestoreWarnings();

		$output .= '<div class="c-score">' . "\n";

		if ( $this->page->allowMinus == true || $this->page->allowPlus == true ) {
			$output .= '<span class="c-score-title">' .
				wfMessage( 'comments-score-text' )->plain() .
				" <span id=\"Comment{$this->id}\">{$this->score}</span></span>";

			// Voting is possible only when database is unlocked
			if ( !wfReadOnly() ) {
				if ( !$this->userAlreadyVoted() ) {
					// You can only vote for other people's comments,
					// not for your own
					if ( $this->getUser()->getName() != $this->username ) {
						$output .= "<span id=\"CommentBtn{$this->id}\">";
						if ( $this->page->allowPlus == true ) {
							$output .= $this->getVoteLink( 1 );
						}

						if ( $this->page->allowMinus == true ) {
							$output .= $this->getVoteLink( -1 );
						}
						$output .= '</span>';
					} else {
						$output .= wfMessage( 'word-separator' )->plain() . wfMessage( 'comments-you' )->plain();
					}
				} else {
					// Already voted?
					$output .= '<img src="' . $wgExtensionAssetsPath . '/Comments/images/voted.svg" border="0" alt="" />' .
						wfMessage( 'comments-voted-label' )->plain();
				}
			}
		}

		$output .= '</div>' . "\n";

		$output .= '</div>' . "\n";
		$output .= "<div class=\"c-comment {$comment_class}\">" . "\n";
		$output .= $this->getText();
		$output .= '</div>' . "\n";
		$output .= '<div class="c-actions">' . "\n";
		$output .= '<a href="' . htmlspecialchars( $this->page->title->getFullURL() ) . "#comment-{$this->id}\" rel=\"nofollow\">" .
			$this->msg( 'comments-permalink' )->plain() . '</a> ';
		if ( $replyRow || $dlt ) {
			$output .= "{$replyRow} {$dlt}" . "\n";
		}
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";
		$output .= '<div class="cleared"></div>' . "\n";
		$output .= '</div>' . "\n";

		return $output;
	}
}
