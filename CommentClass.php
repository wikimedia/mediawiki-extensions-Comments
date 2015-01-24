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

	/**
	 * Date when the comment was posted
	 *
	 * @var null
	 */
	public $date = null;

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
	 * The current vote from this user on this comment
	 *
	 * @var int|boolean: false if no vote, otherwise -1, 0, or 1
	 */
	public $currentVote = false;

	/**
	 * @var string: comment score (SUM() of all votes) of the current comment
	 */
	public $currentScore = '0';

	/**
	 * Username of the user who posted the comment
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * IP of the comment poster
	 *
	 * @var string
	 */
	public $ip = '';

	/**
	 * ID of the user who posted the comment
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
	 * Comment ID of the thread this comment is in
	 * this is the ID of the parent comment if there is one,
	 * or this comment if there is not
	 * Used for sorting
	 *
	 * @var null
	 */
	public $thread = null;

	/**
	 * Unix timestamp when the comment was posted
	 * Used for sorting
	 * Processed from $date
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
		$this->parentID = $data['Comment_Parent_ID'];
		$this->thread = $data['thread'];
		$this->timestamp = $data['timestamp'];


		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'Comments_Vote',
			array( 'Comment_Vote_Score' ),
			array(
				'Comment_Vote_ID' => $this->id,
				'Comment_Vote_Username' => $this->getUser()->getName()
			),
			__METHOD__
		);
		if ( $row !== false ) {
			$vote = $row->Comment_Vote_Score;
		} else {
			$vote = false;
		}

		$this->currentVote = $vote;

		$this->currentScore = $this->getScore();
	}

	public static function newFromID( $id ) {
		$context = RequestContext::getMain();
		$dbr = wfGetDB( DB_SLAVE );

		if ( !is_numeric( $id ) || $id == 0 ) {
			return null;
		}

		$tables = array();
		$params = array();
		$joinConds = array();

		// Defaults (for non-social wikis)
		$tables[] = 'Comments';
		$fields = array(
			'Comment_Username', 'Comment_IP', 'Comment_Text',
			'Comment_Date', 'UNIX_TIMESTAMP(Comment_Date) AS timestamp',
			'Comment_user_id', 'CommentID', 'Comment_Parent_ID',
			'CommentID', 'Comment_Page_ID'
		);

		// If SocialProfile is installed, query the user_stats table too.
		if (
			$dbr->tableExists( 'user_stats' ) &&
			class_exists( 'UserProfile' )
		) {
			$tables[] = 'user_stats';
			$fields[] = 'stats_total_points';
			$joinConds = array(
				'Comments' => array(
					'LEFT JOIN', 'Comment_user_id = stats_user_id'
				)
			);
		}

		// Perform the query
		$res = $dbr->select(
			$tables,
			$fields,
			array( 'CommentID' => $id ),
			__METHOD__,
			$params,
			$joinConds
		);

		$row = $res->fetchObject();

		if ( $row->Comment_Parent_ID == 0 ) {
			$thread = $row->CommentID;
		} else {
			$thread = $row->Comment_Parent_ID;
		}
		$data = array(
			'Comment_Username' => $row->Comment_Username,
			'Comment_IP' => $row->Comment_IP,
			'Comment_Text' => $row->Comment_Text,
			'Comment_Date' => $row->Comment_Date,
			'Comment_user_id' => $row->Comment_user_id,
			'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
			'CommentID' => $row->CommentID,
			'Comment_Parent_ID' => $row->Comment_Parent_ID,
			'thread' => $thread,
			'timestamp' => $row->timestamp
		);

		$page = new CommentsPage( $row->Comment_Page_ID, $context );

		return new Comment( $page, $context, $data );
	}

	/**
	 * Parse and return the text for this comment
	 *
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
	 * Adds the comment and all necessary info into the Comments table in the
	 * database.
	 *
	 * @param string $text: text of the comment
	 * @param CommentsPage $page: container page
	 * @param User $user: user commenting
	 * @param int $parentID: ID of parent comment, if this is a reply
	 *
	 * @return Comment: the added comment
	 */
	static function add( $text, CommentsPage $page, User $user, $parentID ) {
		global $wgCommentsInRecentChanges;
		$dbw = wfGetDB( DB_MASTER );
		$context = RequestContext::getMain();

		wfSuppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		$dbw->insert(
			'Comments',
			array(
				'Comment_Page_ID' => $page->id,
				'Comment_Username' => $user->getName(),
				'Comment_user_id' => $user->getId(),
				'Comment_Text' => $text,
				'Comment_Date' => $commentDate,
				'Comment_Parent_ID' => $parentID,
				'Comment_IP' => $_SERVER['REMOTE_ADDR']
			),
			__METHOD__
		);
		$commentId = $dbw->insertId();
		$dbw->commit(); // misza: added this
		$id = $commentId;

		$page->clearCommentListCache();

		// Add a log entry.
		$pageTitle = Title::newFromID( $page->id );

		$logEntry = new ManualLogEntry( 'comments', 'add' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $pageTitle );
		$logEntry->setComment( $text );
		$logEntry->setParameters( array(
			'4::commentid' => $commentId
		) );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );

		$dbr = wfGetDB( DB_SLAVE );
		if (
			$dbr->tableExists( 'user_stats' ) &&
			class_exists( 'UserProfile' )
		) {
			$res = $dbr->select( // need this data for seeding a Comment object
				'user_stats',
				'stats_total_points',
				array( 'stats_user_id' => $user->getId() ),
				__METHOD__
			);

			$row = $res->fetchObject();
			$userPoints = number_format( $row->stats_total_points );
		} else {
			$userPoints = 0;
		}

		if ( $parentID == 0 ) {
			$thread = $id;
		} else {
			$thread = $parentID;
		}
		$data = array(
			'Comment_Username' => $user->getName(),
			'Comment_IP' => $context->getRequest()->getIP(),
			'Comment_Text' => $text,
			'Comment_Date' => $commentDate,
			'Comment_user_id' => $user->getID(),
			'Comment_user_points' => $userPoints,
			'CommentID' => $id,
			'Comment_Parent_ID' => $parentID,
			'thread' => $thread,
			'timestamp' => strtotime( $commentDate )
		);

		$page = new CommentsPage( $page->id, $context );
		$comment = new Comment( $page, $context, $data );

		wfRunHooks( 'Comment::add', array( $comment, $commentId, $comment->page->id ) );

		return $comment;
	}

	/**
	 * Gets the score for this comment from the database table Comments_Vote
	 *
	 * @return string
	 */
	function getScore() {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow(
			'Comments_Vote',
			array( 'SUM(Comment_Vote_Score) AS CommentScore' ),
			array( 'Comment_Vote_ID' => $this->id ),
			__METHOD__
		);
		$score = '0';
		if ( $row !== false && $row->CommentScore ) {
			$score = $row->CommentScore;
		}
		return $score;
	}

	/**
	 * Adds a vote for a comment if the user hasn't voted for said comment yet.
	 *
	 * @param $value int: upvote or downvote (1 or -1)
	 */
	function vote( $value ) {
		global $wgMemc;
		$dbw = wfGetDB( DB_MASTER );

		if ( $value < -1 ) { // limit to range -1 -> 0 -> 1
			$value = -1;
		} elseif ( $value > 1 ) {
			$value = 1;
		}

		if ( $value == $this->currentVote ) { // user toggling off a preexisting vote
			$value = 0;
		}

		wfSuppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();

		if ( $this->currentVote === false ) { // no vote, insert
			$dbw->insert(
				'Comments_Vote',
				array(
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				),
				__METHOD__
			);
		} else { // already a vote, update
			$dbw->update(
				'Comments_Vote',
				array(
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				),
				array(
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
				),
				__METHOD__
			);
		}
		$dbw->commit();

		// update cache for comment list
		// should perform better than deleting cache completely since Votes happen more frequently
		$key = wfMemcKey( 'comment', 'pagelist', $this->page->id );
		$comments = $wgMemc->get( $key );
		if ( $comments ) {
			foreach ( $comments as &$comment ) {
				if ( $comment->id == $this->id ) {
					$comment->currentScore = $this->currentScore;
				}
			}
			$wgMemc->set( $key, $comments );
		}

		$score = $this->getScore();

		$this->currentVote = $value;
		$this->currentScore = $score;
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
		$this->page->clearCommentListCache();

		// Ping other extensions that may have hooked into this point (i.e. LinkFilter)
		wfRunHooks( 'Comment::delete', array( $this, $this->id, $this->page->id ) );
	}

	/**
	 * Return the HTML for the comment vote links
	 *
	 * @param int $voteType up (+1) vote or down (-1) vote
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
			if ( $this->currentVote == 1 ) {
				$voteLink .= "<img src=\"{$imagePath}/up-voted.png\" border=\"0\" alt=\"+\" /></a>";
			} else {
				$voteLink .= "<img src=\"{$imagePath}/up-unvoted.png\" border=\"0\" alt=\"+\" /></a>";
			}
		} else {
			if ( $this->currentVote == -1 ) {
				$voteLink .= "<img src=\"{$imagePath}/down-voted.png\" border=\"0\" alt=\"+\" /></a>";
			} else {
				$voteLink .= "<img src=\"{$imagePath}/down-unvoted.png\" border=\"0\" alt=\"+\" /></a>";
			}
		}

		return $voteLink;
	}

	/**
	 * Show the HTML for this comment and ignore section
	 *
	 * @param array $blockList list of users the current user has blocked
	 * @param array $anonList map of ip addresses to names like anon#1, anon#2
	 * @return string html
	 */
	function display( $blockList, $anonList ) {
		if ( $this->parentID == 0 ) {
			$container_class = 'full';
		} else {
			$container_class = 'reply';
		}

		$output = '';

		if ( in_array( $this->username, $blockList ) ) {
			$output .= $this->showIgnore( false, $container_class );
			$output .= $this->showComment( true, $container_class, $blockList, $anonList );
		} else {
			$output .= $this->showIgnore( true, $container_class );
			$output .= $this->showComment( false, $container_class, $blockList, $anonList );
		}

		return $output;
	}

	/**
	 * Show the box for if this comment has been ignored
	 *
	 * @param bool $hide
	 * @param $containerClass
	 * @return string
	 */
	function showIgnore( $hide = false, $containerClass ) {
		$blockListTitle = SpecialPage::getTitleFor( 'CommentIgnoreList' );

		$style = '';
		if ( $hide ) {
			$style = " style='display:none;'";
		}

		$output = "<div id='ignore-{$this->id}' class='c-ignored {$containerClass}'{$style}>\n";
		$output .= wfMessage( 'comments-ignore-message' )->parse();
		$output .= '<div class="c-ignored-links">' . "\n";
		$output .= "<a href=\"javascript:void(0);\" data-comment-id=\"{$this->id}\">" .
			$this->msg( 'comments-show-comment-link' )->plain() . '</a> | ';
		$output .= '<a href="' . htmlspecialchars( $blockListTitle->getFullURL() ) . '">' .
			$this->msg( 'comments-manage-blocklist-link' )->plain() . '</a>';
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";

		return $output;
	}


	/**
	 * Show the comment
	 *
	 * @param bool $hide: if true, comment is returned but hidden (display:none)
	 * @param $containerClass
	 * @param $blockList
	 * @param $anonList
	 * @return string
	 */
	function showComment( $hide = false, $containerClass, $blockList, $anonList ) {
		global $wgUserLevels, $wgExtensionAssetsPath;

		$style = '';
		if ( $hide ) {
			$style = " style='display:none;'";
		}

		$commentPosterLevel = '';

		if ( $this->userID != 0 ) {
			$title = Title::makeTitle( NS_USER, $this->username );

			$commentPoster = '<a href="' . htmlspecialchars( $title->getFullURL() ) .
				'" rel="nofollow">' . $this->username . '</a>';

			$CommentReplyTo = $this->username;

			if ( $wgUserLevels && class_exists( 'UserLevel' ) ) {
				$user_level = new UserLevel( $this->userPoints );
				$commentPosterLevel = "{$user_level->getLevelName()}";
			}

			$user = User::newFromId( $this->userID );
			$CommentReplyToGender = $user->getOption( 'gender', 'unknown' );
		} else {
			$anonMsg = $this->msg( 'comments-anon-name' )->inContentLanguage()->plain();
			$commentPoster = $anonMsg . ' #' . $anonList[$this->username];
			$CommentReplyTo = $anonMsg;
			$CommentReplyToGender = 'unknown'; // Undisclosed gender as anon user
		}

		// Comment delete button for privileged users
		$dlt = '';

		if ( $this->getUser()->isAllowed( 'commentadmin' ) ) {
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
			$comment_class = 'f-message';
		} else {
			$comment_class = 'r-message';
		}

		// Display Block icon for logged in users for comments of users
		// that are already not in your block list
		$blockLink = '';

		if (
			$this->getUser()->getID() != 0 && $this->getUser()->getID() != $this->userID &&
			!( in_array( $this->userID, $blockList ) )
		) {
			$blockLink = '<a href="javascript:void(0);" rel="nofollow" class="comments-block-user" data-comments-safe-username="' .
				htmlspecialchars( $this->username, ENT_QUOTES ) .
				'" data-comments-comment-id="' . $this->id . '" data-comments-user-id="' .
				$this->userID . "\">
					<img src=\"{$wgExtensionAssetsPath}/Comments/images/block.svg\" border=\"0\" alt=\"\"/>
				</a>";
		}

		// Default avatar image, if SocialProfile extension isn't enabled
		global $wgCommentsDefaultAvatar;
		$avatarImg = '<img src="' . $wgCommentsDefaultAvatar . '" alt="" border="0" />';
		// If SocialProfile *is* enabled, then use its wAvatar class to get the avatars for each commenter
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $this->userID, 'ml' );
			$avatarImg = $avatar->getAvatarURL() . "\n";
		}

		$output = "<div id='comment-{$this->id}' class='c-item {$containerClass}'{$style}>" . "\n";
		$output .= "<div class=\"c-avatar\">{$avatarImg}</div>" . "\n";
		$output .= '<div class="c-container">' . "\n";
		$output .= '<div class="c-user">' . "\n";
		$output .= "{$commentPoster}";
		$output .= "<span class=\"c-user-level\">{$commentPosterLevel}</span> {$blockLink}" . "\n";

		wfSuppressWarnings(); // E_STRICT bitches about strtotime()
		$output .= '<div class="c-time">' .
			wfMessage(
				'comments-time-ago',
				CommentFunctions::getTimeAgo( strtotime( $this->date ) )
			)->parse() . '</div>' . "\n";
		wfRestoreWarnings();

		$output .= '<div class="c-score">' . "\n";
		$output .= $this->getScoreHTML();
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

	/**
	 * Get the HTML for the comment score section of the comment
	 *
	 * @return string
	 */
	function getScoreHTML() {
		$output = '';

		if ( $this->page->allowMinus == true || $this->page->allowPlus == true ) {
			$output .= '<span class="c-score-title">' .
				wfMessage( 'comments-score-text' )->plain() .
				" <span id=\"Comment{$this->id}\">{$this->currentScore}</span></span>";

			// Voting is possible only when database is unlocked
			if ( !wfReadOnly() ) {
				// You can only vote for other people's comments, not for your own
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
			}
		}

		return $output;
	}
}
