<?php

use MediaWiki\MediaWikiServices;

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
	 * The amount of points the user has; fetched from the user_stats table if
	 * SocialProfile is installed, otherwise this remains 0
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
	 * @param CommentsPage $page ID number of the current page
	 * @param IContextSource|null $context
	 * @param array $data Straight from the DB about the comment
	 */
	public function __construct( CommentsPage $page, $context = null, $data ) {
		$this->page = $page;

		$this->setContext( $context );

		$this->username = $data['Comment_Username'];
		$this->ip = $data['Comment_IP'];
		$this->text = $data['Comment_Text'];
		$this->date = $data['Comment_Date'];
		$this->userID = (int)$data['Comment_user_id'];
		$this->userPoints = $data['Comment_user_points'];
		$this->id = (int)$data['CommentID'];
		$this->parentID = (int)$data['Comment_Parent_ID'];
		$this->thread = $data['thread'];
		$this->timestamp = $data['timestamp'];

		if ( isset( $data['current_vote'] ) ) {
			$vote = $data['current_vote'];
		} else {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'Comments_Vote',
				[ 'Comment_Vote_Score' ],
				[
					'Comment_Vote_ID' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName()
				],
				__METHOD__
			);
			if ( $row !== false ) {
				$vote = $row->Comment_Vote_Score;
			} else {
				$vote = false;
			}
		}

		$this->currentVote = $vote;

		$this->currentScore = isset( $data['total_vote'] )
			? $data['total_vote'] : $this->getScore();
	}

	public static function newFromID( $id ) {
		$context = RequestContext::getMain();
		$dbr = wfGetDB( DB_REPLICA );

		if ( !is_numeric( $id ) || $id == 0 ) {
			return null;
		}

		$tables = [];
		$params = [];
		$joinConds = [];

		// Defaults (for non-social wikis)
		$tables[] = 'Comments';
		$fields = [
			'Comment_Username', 'Comment_IP', 'Comment_Text',
			'Comment_Date', 'Comment_Date AS timestamp',
			'Comment_user_id', 'CommentID', 'Comment_Parent_ID',
			'CommentID', 'Comment_Page_ID'
		];

		// If SocialProfile is installed, query the user_stats table too.
		if (
			class_exists( 'UserProfile' ) &&
			$dbr->tableExists( 'user_stats' )
		) {
			$tables[] = 'user_stats';
			$fields[] = 'stats_total_points';
			$joinConds = [
				'Comments' => [
					'LEFT JOIN', 'Comment_user_id = stats_user_id'
				]
			];
		}

		// Perform the query
		$res = $dbr->select(
			$tables,
			$fields,
			[ 'CommentID' => $id ],
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
		$data = [
			'Comment_Username' => $row->Comment_Username,
			'Comment_IP' => $row->Comment_IP,
			'Comment_Text' => $row->Comment_Text,
			'Comment_Date' => $row->Comment_Date,
			'Comment_user_id' => $row->Comment_user_id,
			'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
			'CommentID' => $row->CommentID,
			'Comment_Parent_ID' => $row->Comment_Parent_ID,
			'thread' => $thread,
			'timestamp' => wfTimestamp( TS_UNIX, $row->timestamp )
		];

		$page = new CommentsPage( $row->Comment_Page_ID, $context );

		return new Comment( $page, $context, $data );
	}

	/**
	 * Is the given User the owner (author) of this comment?
	 *
	 * @param User $user
	 * @return bool
	 */
	public function isOwner( User $user ) {
		return ( $this->username === $user->getName() && $this->userID === $user->getId() );
	}

	/**
	 * Parse and return the text for this comment
	 *
	 * @return mixed|string
	 * @throws MWException
	 */
	function getText() {
		$parser = MediaWikiServices::getInstance()->getParser();

		$commentText = trim( str_replace( '&quot;', "'", $this->text ) );
		$comment_text_parts = explode( "\n", $commentText );
		$comment_text_fix = '';
		foreach ( $comment_text_parts as $part ) {
			$comment_text_fix .= ( ( $comment_text_fix ) ? "\n" : '' ) . trim( $part );
		}

		if ( $this->getTitle()->getArticleID() > 0 ) {
			$commentText = $parser->recursiveTagParse( $comment_text_fix );
		} else {
			$commentText = $this->getOutput()->parse( $comment_text_fix );
		}

		// really bad hack because we want to parse=firstline, but don't want wrapping <p> tags
		if ( substr( $commentText, 0, 3 ) == '<p>' ) {
			$commentText = substr( $commentText, 3 );
		}

		if ( substr( $commentText, strlen( $commentText ) - 4, 4 ) == '</p>' ) {
			$commentText = substr( $commentText, 0, strlen( $commentText ) - 4 );
		}

		// make sure link text is not too long (will overflow)
		// this function changes too long links to <a href=#>http://www.abc....xyz.html</a>
		$commentText = preg_replace_callback(
			"/(<a[^>]*>)(.*?)(<\/a>)/i",
			[ 'CommentFunctions', 'cutCommentLinkText' ],
			$commentText
		);

		return $commentText;
	}

	/**
	 * Adds the comment and all necessary info into the Comments table in the
	 * database.
	 *
	 * @param string $text text of the comment
	 * @param CommentsPage $page container page
	 * @param User $user user commenting
	 * @param int $parentID ID of parent comment, if this is a reply
	 *
	 * @return Comment the added comment
	 */
	static function add( $text, CommentsPage $page, User $user, $parentID ) {
		$dbw = wfGetDB( DB_MASTER );
		$context = RequestContext::getMain();

		MediaWiki\suppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		MediaWiki\restoreWarnings();
		$dbw->insert(
			'Comments',
			[
				'Comment_Page_ID' => $page->id,
				'Comment_Username' => $user->getName(),
				'Comment_user_id' => $user->getId(),
				'Comment_Text' => $text,
				'Comment_Date' => $commentDate,
				'Comment_Parent_ID' => $parentID,
				'Comment_IP' => $_SERVER['REMOTE_ADDR']
			],
			__METHOD__
		);
		$commentId = $dbw->insertId();
		$id = $commentId;

		$page->clearCommentListCache();

		// Add a log entry.
		self::log( 'add', $user, $page->id, $commentId, $text );

		$dbr = wfGetDB( DB_REPLICA );
		if (
			class_exists( 'UserProfile' ) &&
			$dbr->tableExists( 'user_stats' )
		) {
			$res = $dbr->select( // need this data for seeding a Comment object
				'user_stats',
				'stats_total_points',
				[ 'stats_user_id' => $user->getId() ],
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
		$data = [
			'Comment_Username' => $user->getName(),
			'Comment_IP' => $context->getRequest()->getIP(),
			'Comment_Text' => $text,
			'Comment_Date' => $commentDate,
			'Comment_user_id' => $user->getId(),
			'Comment_user_points' => $userPoints,
			'CommentID' => $id,
			'Comment_Parent_ID' => $parentID,
			'thread' => $thread,
			'timestamp' => strtotime( $commentDate )
		];

		$page = new CommentsPage( $page->id, $context );
		$comment = new Comment( $page, $context, $data );

		Hooks::run( 'Comment::add', [ $comment, $commentId, $comment->page->id ] );

		return $comment;
	}

	/**
	 * Gets the score for this comment from the database table Comments_Vote
	 *
	 * @return string
	 */
	function getScore() {
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			'Comments_Vote',
			[ 'SUM(Comment_Vote_Score) AS CommentScore' ],
			[ 'Comment_Vote_ID' => $this->id ],
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
	 * @param int $value Upvote or downvote (1 or -1)
	 */
	function vote( $value ) {
		$dbw = wfGetDB( DB_MASTER );

		if ( $value < -1 ) { // limit to range -1 -> 0 -> 1
			$value = -1;
		} elseif ( $value > 1 ) {
			$value = 1;
		}

		if ( $value == $this->currentVote ) { // user toggling off a preexisting vote
			$value = 0;
		}

		MediaWiki\suppressWarnings();
		$commentDate = date( 'Y-m-d H:i:s' );
		MediaWiki\restoreWarnings();

		if ( $this->currentVote === false ) { // no vote, insert
			$dbw->insert(
				'Comments_Vote',
				[
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				],
				__METHOD__
			);
		} else { // already a vote, update
			$dbw->update(
				'Comments_Vote',
				[
					'Comment_Vote_Score' => $value,
					'Comment_Vote_Date' => $commentDate,
					'Comment_Vote_IP' => $_SERVER['REMOTE_ADDR']
				],
				[
					'Comment_Vote_id' => $this->id,
					'Comment_Vote_Username' => $this->getUser()->getName(),
					'Comment_Vote_user_id' => $this->getUser()->getId(),
				],
				__METHOD__
			);
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
		$dbw->startAtomic( __METHOD__ );
		$dbw->delete(
			'Comments',
			[ 'CommentID' => $this->id ],
			__METHOD__
		);
		$dbw->delete(
			'Comments_Vote',
			[ 'Comment_Vote_ID' => $this->id ],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );

		// Log the deletion to Special:Log/comments.
		self::log( 'delete', $this->getUser(), $this->page->id, $this->id );

		// Clear memcache & Squid cache
		$this->page->clearCommentListCache();

		// Ping other extensions that may have hooked into this point (i.e. LinkFilter)
		Hooks::run( 'Comment::delete', [ $this, $this->id, $this->page->id ] );
	}

	/**
	 * Log an action in the comment log.
	 *
	 * @param string $action Action to log, can be either 'add' or 'delete'
	 * @param User $user User who performed the action
	 * @param int $pageId Page ID of the page that contains the comment thread
	 * @param int $commentId Comment ID of the affected comment
	 * @param string|null $commentText Supplementary log comment, if any
	 */
	static function log( $action, $user, $pageId, $commentId, $commentText = null ) {
		global $wgCommentsInRecentChanges;
		$logEntry = new ManualLogEntry( 'comments', $action );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( Title::newFromId( $pageId ) );
		if ( $commentText !== null ) {
			$logEntry->setComment( $commentText );
		}
		$logEntry->setParameters( [
			'4::commentid' => $commentId
		] );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, ( $wgCommentsInRecentChanges ? 'rcandudp' : 'udp' ) );
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
			$urlParams = [];
			// @todo FIXME: *when* and *why* is this null?
			if ( $this->page->title instanceof Title ) {
				$returnTo = $this->page->title->getPrefixedDBkey(); // Determine a sane returnto URL parameter
				$urlParams = [ 'returnto' => $returnTo ];
			}

			$voteLink .=
				"<a href=\"" .
				htmlspecialchars( $login->getLocalURL( $urlParams ) ) .
				"\" rel=\"nofollow\">";
		}

		$imagePath = $wgExtensionAssetsPath . '/Comments/resources/images';
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
	 * @param array $blockList List of users the current user has blocked
	 * @param array $anonList Map of IP addresses to names like anon#1, anon#2
	 * @return string HTML
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
	 * @param bool $hide If true, comment is returned but hidden (display:none)
	 * @param string $containerClass
	 * @param array $blockList
	 * @param array $anonList
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
		$userObj = $this->getUser();
		$dlt = '';

		if (
			$userObj->isAllowed( 'commentadmin' ) ||
			// Allow users to delete their own comments if that feature is enabled in
			// site configuration
			// @see https://phabricator.wikimedia.org/T147796
			$userObj->isAllowed( 'comment-delete-own' ) && $this->isOwner( $userObj )
		) {
			$dlt = ' | <span class="c-delete">' .
				'<a href="javascript:void(0);" rel="nofollow" class="comment-delete-link" data-comment-id="' .
				$this->id . '">' .
				$this->msg( 'comments-delete-link' )->plain() . '</a></span>';
		}

		// Reply Link (does not appear on child comments)
		$replyRow = '';
		if ( $userObj->isAllowed( 'comment' ) ) {
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
			$userObj->getId() != 0 && $userObj->getId() != $this->userID &&
			!( in_array( $this->userID, $blockList ) )
		) {
			$blockLink = '<a href="javascript:void(0);" rel="nofollow" class="comments-block-user" data-comments-safe-username="' .
				htmlspecialchars( $this->username, ENT_QUOTES ) .
				'" data-comments-comment-id="' . $this->id . '" data-comments-user-id="' .
				$this->userID . "\">
					<img src=\"{$wgExtensionAssetsPath}/Comments/resources/images/block.svg\" border=\"0\" alt=\"\"/>
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

		MediaWiki\suppressWarnings(); // E_STRICT bitches about strtotime()
		$output .= '<div class="c-time">' .
			wfMessage(
				'comments-time-ago',
				CommentFunctions::getTimeAgo( strtotime( $this->date ) )
			)->parse() . '</div>' . "\n";
		MediaWiki\restoreWarnings();

		$output .= '<div class="c-score">' . "\n";
		$output .= $this->getScoreHTML();
		$output .= '</div>' . "\n";

		$output .= '</div>' . "\n";
		$output .= "<div class=\"c-comment {$comment_class}\">" . "\n";
		$output .= $this->getText();
		$output .= '</div>' . "\n";
		$output .= '<div class="c-actions">' . "\n";
		if ( $this->page->title ) { // for some reason doesn't always exist
			$output .= '<a href="' . htmlspecialchars( $this->page->title->getFullURL() ) . "#comment-{$this->id}\" rel=\"nofollow\">" .
			$this->msg( 'comments-permalink' )->plain() . '</a> ';
		}
		if ( $replyRow || $dlt ) {
			$output .= "{$replyRow} {$dlt}" . "\n";
		}
		$output .= '</div>' . "\n";
		$output .= '</div>' . "\n";
		$output .= '<div class="visualClear"></div>' . "\n";
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
