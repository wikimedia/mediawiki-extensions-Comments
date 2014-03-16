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
	var $PageID = 0;

	/**
	 * @var Integer: total amount of comments by distinct commenters that the
	 *               current page has
	 */
	var $CommentTotal = 0;

	/**
	 * @var String: text of the current comment
	 */
	var $CommentText = null;

	var $CommentDate = null; // @todo FIXME/CHECKME: unused, remove this?

	/**
	 * @var Integer: internal ID number (Comments.CommentID DB field) of the
	 *               current comment that we're dealing with
	 */
	var $CommentID = 0;

	/**
	 * @var Integer: ID of the parent comment, if this is a child comment
	 */
	var $CommentParentID = 0;

	var $CommentVote = 0;

	/**
	 * @var Integer: comment score (SUM() of all votes) of the current page
	 */
	var $CommentScore = 0;

	/**
	 * @var Integer: if this is _not_ 0, then the comments are ordered by their
	 *               Comment_Score in descending order
	 */
	var $OrderBy = 0;

	/**
	 * @var Integer: maximum amount of comments shown per page before pagination
	 *               is enabled; also used as the LIMIT for the SQL query
	 */
	var $Limit = 100;

	var $PagerLimit = 9;
	var $CurrentPagerPage = 0;
	var $Allow = '';
	var $Voting = '';

	/**
	 * @var Boolean: allow positive (plus) votes?
	 */
	var $AllowPlus = true;

	/**
	 * @var Boolean: allow negative (minus) votes?
	 */
	var $AllowMinus = true;

	var $PAGE_QUERY = 'cpage';

	/**
	 * The following four functions are borrowed
	 * from includes/wikia/GlobalFunctionsNY.php
	 */
	static function dateDiff( $date1, $date2 ) {
		$dtDiff = $date1 - $date2;

		$totalDays = intval( $dtDiff / ( 24 * 60 * 60 ) );
		$totalSecs = $dtDiff - ( $totalDays * 24 * 60 * 60 );
		$dif['mo'] = intval( $totalDays / 30 );
		$dif['d'] = $totalDays;
		$dif['h'] = $h = intval( $totalSecs / ( 60 * 60 ) );
		$dif['m'] = $m = intval( ( $totalSecs - ( $h * 60 * 60 ) ) / 60 );
		$dif['s'] = $totalSecs - ( $h * 60 * 60 ) - ( $m * 60 );

		return $dif;
	}

	static function getTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = ''; // misza: initialize variables, DUMB FUCKS!
		if( $time[$timeabrv] > 0 ) {
			// Give grep a chance to find the usages:
			// comments-time-days, comments-time-hours, comments-time-minutes, comments-time-seconds, comments-time-months
			$timeStr = wfMessage( "comments-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	static function getTimeAgo( $time ) {
		$timeArray = self::dateDiff( time(), $time );
		$timeStr = '';
		$timeStrMo = self::getTimeOffset( $timeArray, 'mo', 'months' );
		$timeStrD = self::getTimeOffset( $timeArray, 'd', 'days' );
		$timeStrH = self::getTimeOffset( $timeArray, 'h', 'hours' );
		$timeStrM = self::getTimeOffset( $timeArray, 'm', 'minutes' );
		$timeStrS = self::getTimeOffset( $timeArray, 's', 'seconds' );

		if ( $timeStrMo ) {
			$timeStr = $timeStrMo;
		} else {
			$timeStr = $timeStrD;
			if( $timeStr < 2 ) {
				$timeStr .= $timeStrH;
				$timeStr .= $timeStrM;
				if( !$timeStr ) {
					$timeStr .= $timeStrS;
				}
			}
		}
		if( !$timeStr ) {
			$timeStr = wfMessage( 'comments-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}

	/**
	 * Makes sure that link text is not too long by changing too long links to
	 * <a href=#>http://www.abc....xyz.html</a>
	 *
	 * @param $matches Array
	 * @return String: shortened URL
	 */
	public static function cutCommentLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];

		$image = preg_match( "/<img src=/i", $linkText );
		$isURL = ( preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $linkText ) ? true : false );

		if( $isURL && !$image && strlen( $linkText ) > 30 ) {
			$start = substr( $linkText, 0, ( 30 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 30 / 2 ) + 3, ( 30 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMsg( 'ellipsis' ) . trim( $end );
		}
		return $tagOpen . $linkText . $tagClose;
	}

	/**
	 * Constructor - set the page ID
	 *
	 * @param int $pageID Integer: ID number of the current page
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

	function setCommentText( $commentText ) {
		$this->CommentText = $commentText;
	}

	function getCommentText( $comment_text ) {
		global $wgParser;

		$comment_text = trim( str_replace( '&quot;', "'", $comment_text ) );
		$comment_text_parts = explode( "\n", $comment_text );
		$comment_text_fix = '';
		foreach( $comment_text_parts as $part ) {
			$comment_text_fix .= ( ( $comment_text_fix ) ? "\n" : '' ) . trim( $part );
		}

		if( $this->getTitle()->getArticleID() > 0 ) {
			$comment_text = $wgParser->recursiveTagParse( $comment_text_fix );
		} else {
			$comment_text = $this->getOutput()->parse( $comment_text_fix );
		}

		// really bad hack because we want to parse=firstline, but don't want wrapping <p> tags
		if( substr( $comment_text, 0 , 3 ) == '<p>' ) {
			$comment_text = substr( $comment_text, 3 );
		}

		if( substr( $comment_text, strlen( $comment_text ) -4 , 4 ) == '</p>' ) {
			$comment_text = substr( $comment_text, 0, strlen( $comment_text ) -4 );
		}

		// make sure link text is not too long (will overflow)
		// this function changes too long links to <a href=#>http://www.abc....xyz.html</a>
		$comment_text = preg_replace_callback(
			"/(<a[^>]*>)(.*?)(<\/a>)/i",
			array( 'Comment', 'cutCommentLinkText' ),
			$comment_text
		);

		return $comment_text;
	}

	/**
	 * Set comment ID to $commentID.
	 *
	 * @param $commentID Integer: comment ID
	 */
	function setCommentID( $commentID ) {
		$this->CommentID = intval( $commentID );
	}

	/**
	 * Set voting either totally off, or disallow "thumbs down" or disallow
	 * "thumbs up".
	 *
	 * @param $voting String: 'OFF', 'PLUS' or 'MINUS' (will be strtoupper()ed)
	 */
	function setVoting( $voting ) {
		$this->Voting = $voting;
		$voting = strtoupper( $voting );

		if( $voting == 'OFF' ) {
			$this->AllowMinus = false;
			$this->AllowPlus = false;
		}
		if( $voting == 'PLUS' ) {
			$this->AllowMinus = false;
		}
		if( $voting == 'MINUS' ) {
			$this->AllowPlus = false;
		}
	}

	/**
	 * @param $parentID Integer: parent ID number
	 */
	function setCommentParentID( $parentID ) {
		if( $parentID ) {
			$this->CommentParentID = intval( $parentID );
		} else {
			$this->CommentParentID = 0;
		}
	}

	/**
	 * Sets the list of users who are allowed to comment.
	 *
	 * @param $allow String: list of users allowed to comment
	 */
	function setAllow( $allow ) {
		$this->Allow = $allow;
	}

	/**
	 * Sets the value of $name to boolean true/false.
	 *
	 * @param $name String: variable name
	 * @param $value String: 'YES', 1 or 'NO' or 0
	 */
	function setBool( $name, $value ) {
		if( $value ) {
			if( strtoupper( $value ) == 'YES' || strtoupper( $value ) == 1 ) {
				$this->$name = 1;
			} else {
				$this->$name = 0;
			}
		}
	}

	/**
	 * Counts the amount of comments the current page has.
	 *
	 * @return Integer: amount of comments
	 */
	function count() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments',
			array( 'COUNT(DISTINCT(comment_username)) AS CommentCount' ),
			array( 'Comment_Page_ID' => $this->PageID ),
			__METHOD__
		);
		if ( $s !== false ) {
			$this->CommentTotal = $s->CommentCount;
		}
		return $this->CommentTotal;
	}

	/**
	 * Gets the total amount of comments
	 *
	 * @return Integer
	 */
	function countTotal() {
		$dbr = wfGetDB( DB_SLAVE );
		$count = 0;
		$s = $dbr->selectRow(
			'Comments',
			array( 'COUNT(*) AS CommentCount' ),
			array( 'Comment_Page_ID' => $this->PageID ),
			__METHOD__
		);
		if ( $s !== false ) {
			$count = $s->CommentCount;
		}
		return $count;
	}

	/**
	 * Simple spam check -- checks the supplied text against MediaWiki's
	 * built-in regex-based spam filters
	 *
	 * @param $text String: text to check for spam patterns
	 * @return Boolean: true if it contains spam, otherwise false
	 */
	public static function isSpam( $text ) {
		global $wgSpamRegex, $wgSummarySpamRegex;

		$retVal = false;
		// Allow to hook other anti-spam extensions so that sites that use,
		// for example, AbuseFilter, Phalanx or SpamBlacklist can add additional
		// checks
		wfRunHooks( 'Comments::isSpam', array( &$text, &$retVal ) );
		if ( $retVal ) {
			// Should only be true here...
			return $retVal;
		}

		// Run text through $wgSpamRegex (and $wgSummarySpamRegex if it has been specified)
		if ( $wgSpamRegex && preg_match( $wgSpamRegex, $text ) ) {
			return true;
		}

		if ( $wgSummarySpamRegex && is_array( $wgSummarySpamRegex ) ) {
			foreach ( $wgSummarySpamRegex as $spamRegex ) {
				if ( preg_match( $spamRegex, $text ) ) {
					return true;
				}
			}
		}

		return $retVal;
	}

	/**
	 * Checks the supplied text for links
	 *
	 * @param $text String: text to check
	 * @return Boolean: true if it contains links, otherwise false
	 */
	public static function haveLinks( $text ) {
		$linkPatterns = array(
			'/(https?)|(ftp):\/\//',
			'/=\\s*[\'"]?\\s*mailto:/',
		);
		foreach ( $linkPatterns as $linkPattern ) {
			if ( preg_match( $linkPattern, $text ) ) {
				return true;
			}
		}

		return false;
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
	 * Gets the score for the comments from the database table Comments_Vote
	 *
	 * @return Integer
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
	 * Gets the vote count for the comments from the database table Comments_Vote
	 *
	 * @param $vote Integer: 1 for positive votes, -1 for negative votes
	 * @return Integer
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
	 * Gets the ID number of the latest comment for the current page.
	 *
	 * @return Integer
	 */
	function getLatestCommentID() {
		$LatestCommentID = 0; // Added by misza to fix this retarded function
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments',
			array( 'CommentID' ),
			array( 'Comment_Page_ID' => $this->PageID ),
			__METHOD__,
			array( 'ORDER BY' => 'Comment_Date DESC', 'LIMIT' => 1 )
		);
		if ( $s !== false ) {
			$LatestCommentID = $s->CommentID;
		}
		return $LatestCommentID;
	}

	/**
	 * Adds a vote for a comment if the user hasn't voted for said comment yet.
	 */
	function addVote() {
		global $wgMemc;
		$dbw = wfGetDB( DB_MASTER );
		if( $this->UserAlreadyVoted() == false ) {
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
			if( $comments ) {
				foreach( $comments as &$comment ) {
					if( $comment['CommentID'] == $this->CommentID ) {
						$comment['Comment_Score'] = $comment['Comment_Score'] + $this->CommentVote;
						if( $this->CommentVote == 1 ) {
							$comment['CommentVotePlus'] = $comment['CommentVotePlus'] + 1;
						}
						if( $this->CommentVote == -1 ) {
							$comment['CommentVoteMinus'] = $comment['CommentVoteMinus'] + 1;
						}
					}
				}
				$wgMemc->set( $key, $comments );
			}

			$this->updateCommentVoteStats();
		}
	}

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
	 * @return Boolean: true if user has voted, otherwise false
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

	function setCommentVote( $vote ) {
		if( $vote < 0 ) {
			$vote = -1;
		} else {
			$vote = 1;
		}
		$this->CommentVote = $vote;
	}

	function setOrderBy( $order ) {
		if( is_numeric( $order ) ) {
			if( $order == 0 ) {
				$order = 0;
			} else {
				$order = 1;
			}
			$this->OrderBy = $order;
		}
	}

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
		if( is_object( $pageTitle ) ) {
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

	public static function sortCommentList( $x, $y ) {
		if( $x['thread'] == $y['thread'] ) {
			if( $x['timestamp'] == $y['timestamp'] ) {
				return 0;
			} elseif( $x['timestamp'] < $y['timestamp'] ) {
				return -1;
			} else {
				return 1;
			}
		} elseif( $x['thread'] < $y['thread'] ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Check what pages the current user has voted.
	 *
	 * @return Array: array of comment ID numbers
	 */
	public function getCommentVotedList() {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			array( 'Comments_Vote', 'Comments' ),
			'CommentID',
			array(
				'Comment_Page_ID' => $this->PageID,
				'Comment_Vote_user_id' => $this->getUser()->getID()
			),
			__METHOD__,
			array(),
			array( 'Comments' => array( 'LEFT JOIN', 'Comment_Vote_ID = CommentID' ) )
		);

		$voted = array();
		foreach( $res as $row ) {
			$voted[] = $row->CommentID;
		}

		return $voted;
	}

	/**
	 * Fetches all comments, called by display().
	 *
	 * @return Array: array containing every possible bit of information about
	 *                a comment, including score, timestamp and more
	 */
	public function getCommentList( $page ) {
		$dbr = wfGetDB( DB_SLAVE );

		$tables = array();
		$fields = array();
		$params = array();
		$joinConds = array();

		// Defaults (for non-social wikis)
		$tables[] = 'Comments';
		$fields = array(
			'Comment_Username', 'Comment_IP', 'Comment_Text',
			'Comment_Date', 'UNIX_TIMESTAMP(Comment_Date) AS timestamp',
			'Comment_user_id', 'CommentID',
			'IFNULL(Comment_Plus_Count - Comment_Minus_Count,0) AS Comment_Score',
			'Comment_Plus_Count AS CommentVotePlus',
			'Comment_Minus_Count AS CommentVoteMinus', 'Comment_Parent_ID',
			'CommentID'
		);
		$params['LIMIT'] = $this->Limit;
		$params['OFFSET'] = ( $page > 0 ) ? ( ( $page - 1 ) * $this->Limit ) : 0;
		if( $this->OrderBy != 0 ) {
			$params['ORDER BY'] = 'Comment_Score DESC';
		}

		// If SocialProfile is installed, query the user_stats table too.
		if(
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
			array( 'Comment_Page_ID' => $this->PageID ),
			__METHOD__,
			$params,
			$joinConds
		);

		$comments = array();

		foreach( $res as $row ) {
			if( $row->Comment_Parent_ID == 0 ) {
				$thread = $row->CommentID;
			} else {
				$thread = $row->Comment_Parent_ID;
			}
			$comments[] = array(
				'Comment_Username' => $row->Comment_Username,
				'Comment_IP' => $row->Comment_IP,
				'Comment_Text' => $row->Comment_Text,
				'Comment_Date' => $row->Comment_Date,
				'Comment_user_id' => $row->Comment_user_id,
				'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
				'CommentID' => $row->CommentID,
				'Comment_Score' => $row->Comment_Score,
				'CommentVotePlus' => $row->CommentVotePlus,
				'CommentVoteMinus' => $row->CommentVoteMinus,
				#'AlreadyVoted' => $row->AlreadyVoted, // misza: turned off - no such crap
				'Comment_Parent_ID' => $row->Comment_Parent_ID,
				'thread' => $thread,
				'timestamp' => $row->timestamp
			);
		}

		if( $this->OrderBy == 0 ) {
			usort( $comments, array( 'Comment', 'sortCommentList' ) );
		}

		return $comments;
	}

	/**
	 * Displays the "Sort by X" form and a link to auto-refresh comments
	 *
	 * @return HTML
	 */
	function displayOrderForm() {
		$output = '<div class="c-order">
			<div class="c-order-select">
				<form name="ChangeOrder" action="">
					<select name="TheOrder">
						<option value="0">' .
							wfMessage( 'comments-sort-by-date' )->plain() .
						'</option>
						<option value="1">' .
							wfMessage( 'comments-sort-by-score' )->plain() .
						'</option>
					</select>
				</form>
			</div>
			<div id="spy" class="c-spy">
				<a href="javascript:void(0)">' .
					wfMessage( 'comments-auto-refresher-enable' )->plain() .
				'</a>
			</div>
			<div class="cleared"></div>
		</div>
		<br />' . "\n";

		return $output;
	}

	function getVoteLink( $commentID, $voteType ) {
		global $wgExtensionAssetsPath;

		// Blocked users cannot vote, obviously
		if( $this->getUser()->isBlocked() ) {
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
			// Fun fact: the escapeLocalURL() call below used to use
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
		if( $voteType == 1 ) {
			$voteLink .= "<img src=\"{$imagePath}/thumbs-up.gif\" border=\"0\" alt=\"+\" /></a>";
		} else {
			$voteLink .= "<img src=\"{$imagePath}/thumbs-down.gif\" border=\"0\" alt=\"-\" /></a>";
		}

		return $voteLink;
	}

	/**
	 * Display pager for the current page.
	 *
	 * @param $pagerCurrent Integer: page we are currently paged to
	 * @param $pagesCount Integer: the maximum page number
	 */
	function displayPager( $pagerCurrent, $pagesCount ) {
		// Middle is used to "center" pages around the current page.
		$pager_middle = ceil( $this->PagerLimit / 2 );
		// first is the first page listed by this pager piece (re quantity)
		$pagerFirst = $pagerCurrent - $pager_middle + 1;
		// last is the last page listed by this pager piece (re quantity)
		$pagerLast = $pagerCurrent + $this->PagerLimit - $pager_middle;

		// Prepare for generation loop.
		$i = $pagerFirst;
		if ( $pagerLast > $pagesCount ) {
			// Adjust "center" if at end of query.
			$i = $i + ( $pagesCount - $pagerLast );
			$pagerLast = $pagesCount;
		}
		if ( $i <= 0 ) {
			// Adjust "center" if at start of query.
			$pagerLast = $pagerLast + ( 1 - $i );
			$i = 1;
		}

		$output = '';
		if ( $pagesCount > 1 ) {
			$title = Title::newFromID( $this->PageID );
			$output .= '<ul class="c-pager">';
			$pagerEllipsis = '<li class="c-pager-item c-pager-ellipsis"><span>...</span></li>';

			// Whether to display the "Previous page" link
			if ( $pagerCurrent > 1 ) {
				$output .= '<li class="c-pager-item c-pager-previous">' .
					Html::rawElement(
						'a',
						array(
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->PAGE_QUERY => ( $pagerCurrent - 1 ),
						),
						'&lt;'
					) .
					'</li>';
			}

			// Whether to display the "First page" link
			if ( $i > 1 ) {
				$output .= '<li class="c-pager-item c-pager-first">' .
					Html::rawElement(
						'a',
						array(
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->PAGE_QUERY => 1,
						),
						1
					) .
					'</li>';
			}

			// When there is more than one page, create the pager list.
			if ( $i != $pagesCount ) {
				if ( $i > 2 ) {
					$output .= $pagerEllipsis;
				}

				// Now generate the actual pager piece.
				for ( ; $i <= $pagerLast && $i <= $pagesCount; $i++ ) {
					if ( $i == $pagerCurrent ) {
						$output .= '<li class="c-pager-item c-pager-current"><span>' .
							$i . '</span></li>';
					} else {
						$output .= '<li class="c-pager-item">' .
							Html::rawElement(
								'a',
								array(
									'rel' => 'nofollow',
									'class' => 'c-pager-link',
									'href' => '#cfirst',
									'data-' . $this->PAGE_QUERY => $i,
								),
								$i
							) .
							'</li>';
					}
				}

				if ( $i < $pagesCount ) {
					$output .= $pagerEllipsis;
				}
			}

			// Whether to display the "Last page" link
			if ( $pagesCount > ( $i - 1 ) ) {
				$output .= '<li class="c-pager-item c-pager-last">' .
					Html::rawElement(
						'a',
						array(
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->PAGE_QUERY => $pagesCount,
						),
						$pagesCount
					) .
					'</li>';
			}

			// Whether to display the "Next page" link
			if ( $pagerCurrent < $pagesCount ) {
				$output .= '<li class="c-pager-item c-pager-next">' .
					Html::rawElement(
						'a',
						array(
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->PAGE_QUERY => ( $pagerCurrent + 1 ),
						),
						'&gt;'
					) .
					'</li>';
			}

			$output .= '</ul>';
		}

		return $output;
	}

	/**
	 * @return Integer: the page we are currently paged to
	 */
	function getCurrentPagerPage() {
		if ( $this->CurrentPagerPage == 0 ) {
			$this->CurrentPagerPage = $this->getRequest()->getInt( $this->PAGE_QUERY, 1 );

			if ( $this->CurrentPagerPage < 1 ) {
				$this->CurrentPagerPage = 1;
			}
		}

		return $this->CurrentPagerPage;
	}

	/**
	 * Display all the comments for the current page.
	 * CSS and JS is loaded in Comment.php, function displayComments.
	 */
	function display() {
		global $wgScriptPath, $wgExtensionAssetsPath, $wgMemc, $wgUserLevels;

		$output = '';

		// TODO: Try cache
		wfDebug( "Loading comments count for page {$this->PageID} from DB\n" );
		$commentsCount = $this->countTotal();
		$pagesCount = ( ( $commentsCount % $this->Limit ) > 0 )
			? ( 1 +  floor( $commentsCount / $this->Limit ) ) :  floor( $commentsCount / $this->Limit );
		$pagerCurrent = $this->getCurrentPagerPage( $pagesCount );

		if ( $pagerCurrent > $pagesCount ) {
			$pagerCurrent = $pagesCount;
		}

		// Try cache
		$key = wfMemcKey( 'comment', 'list', $this->PageID );
		$data = $wgMemc->get( $key );

		if( !$data ) {
			wfDebug( "Loading comments for page {$this->PageID} from DB\n" );
			$comments = $this->getCommentList( $pagerCurrent );
			$wgMemc->set( $key, $comments );
		} else {
			wfDebug( "Loading comments for page {$this->PageID} from cache\n" );
			$comments = $data;
		}

		// Try cache for voted list for this user
		$voted = array();
		if( $this->getUser()->isLoggedIn() ) {
			$key = wfMemcKey( 'comment', 'voted', $this->PageID, 'user_id', $this->getUser()->getID() );
			$data = $wgMemc->get( $key );

			if( !$data ) {
				$voted = $this->getCommentVotedList();
				$wgMemc->set( $key, $voted );
			} else {
				wfDebug( "Loading comment voted for page {$this->PageID} for user {$this->getUser()->getID()} from cache\n" );
				$voted = $data;
			}
		}

		// Load complete blocked list for logged in user so they don't see their comments
		$block_list = array();
		if( $this->getUser()->getID() != 0 ) {
			$block_list = $this->getBlockList( $this->getUser()->getId() );
		}

		$AFCounter = 1;
		$AFBucket = array();
		if( $comments ) {
			$pager = $this->displayPager( $pagerCurrent, $pagesCount );
			$output .= $pager;
			$output .= '<a id="cfirst" name="cfirst" rel="nofollow"></a>';
			foreach( $comments as $comment ) {
				$CommentScore = $comment['Comment_Score'];

				$CommentPosterLevel = '';

				if( $comment['Comment_user_id'] != 0 ) {
					$title = Title::makeTitle( NS_USER, $comment['Comment_Username'] );

					$CommentPoster = '<a href="' . $title->escapeFullURL() .
						'" rel="nofollow">' . $comment['Comment_Username'] . '</a>';

					$CommentReplyTo = $comment['Comment_Username'];

					if( $wgUserLevels && class_exists( 'UserLevel' ) ) {
						$user_level = new UserLevel( $comment['Comment_user_points'] );
						$CommentPosterLevel = "{$user_level->getLevelName()}";
					}

					$user = User::newFromId( $comment['Comment_user_id'] );
					$CommentReplyToGender = $user->getOption( 'gender', 'unknown' );
				} else {
					if( !array_key_exists( $comment['Comment_Username'], $AFBucket ) ) {
						$AFBucket[$comment['Comment_Username']] = $AFCounter;
						$AFCounter++;
					}

					$anonMsg = wfMessage( 'comments-anon-name' )->inContentLanguage()->plain();
					$CommentPoster = $anonMsg . ' #' . $AFBucket[$comment['Comment_Username']];
					$CommentReplyTo = $anonMsg;
					$CommentReplyToGender = 'unknown'; // Undisclosed gender as anon user
				}

				// Comment delete button for privileged users
				$dlt = '';

				if( $this->getUser()->isAllowed( 'commentadmin' ) ) {
					//$dlt = " | <span class=\"c-delete\"><a href=\"javascript:document.commentform.commentid.value={$comment['CommentID']};document.commentform.submit();\">" .
					$dlt = ' | <span class="c-delete">' .
						'<a href="javascript:void(0);" rel="nofollow" class="comment-delete-link" data-comment-id="' .
							$comment['CommentID'] . '">' .
							wfMessage( 'comments-delete-link' )->plain() . '</a></span>';
				}

				// Reply Link (does not appear on child comments)
				$replyRow = '';
				if ( $this->getUser()->isAllowed( 'comment' ) ) {
					if( $comment['Comment_Parent_ID'] == 0 ) {
						if( $replyRow ) {
							$replyRow .= wfMessage( 'pipe-separator' )->plain();
						}
						$replyRow .= " | <a href=\"#end\" rel=\"nofollow\" class=\"comments-reply-to\" data-comment-id=\"{$comment['CommentID']}\" data-comments-safe-username=\"" .
							htmlspecialchars( $CommentReplyTo, ENT_QUOTES ) . "\" data-comments-user-gender=\"" .
							htmlspecialchars( $CommentReplyToGender ) . '">' .
							wfMessage( 'comments-reply' )->plain() . '</a>';
					}
				}

				if( $comment['Comment_Parent_ID'] == 0 ) {
					$container_class = 'full';
					$comment_class = 'f-message';
				} else {
					$container_class = 'reply';
					$comment_class = 'r-message';
				}

				// Display Block icon for logged in users for comments of users
				// that are already not in your block list
				$block_link = '';

				if(
					$this->getUser()->getID() != 0 && $this->getUser()->getID() != $comment['Comment_user_id'] &&
					!( in_array( $comment['Comment_Username'], $block_list ) )
				) {
					$block_link = '<a href="javascript:void(0);" rel="nofollow" class="comments-block-user" data-comments-safe-username="' .
						htmlspecialchars( $comment['Comment_Username'], ENT_QUOTES ) .
						'" data-comments-comment-id="' . $comment['CommentID'] . '" data-comments-user-id="' .
						$comment['Comment_user_id'] . "\">
					<img src=\"{$wgExtensionAssetsPath}/Comments/images/block.svg\" border=\"0\" alt=\"\"/>
				</a>";
				}

				// If you are ignoring the author of the comment, display message in comment box,
				// along with a link to show the individual comment
				$hide_comment_style = '';

				if( in_array( $comment['Comment_Username'], $block_list ) ) {
					$hide_comment_style = 'display:none;';

					$blockListTitle = SpecialPage::getTitleFor( 'CommentIgnoreList' );

					$output .= "<div id=\"ignore-{$comment['CommentID']}\" class=\"c-ignored {$container_class}\">\n";
					$output .= wfMessage( 'comments-ignore-message' )->parse();
					$output .= '<div class="c-ignored-links">' . "\n";
					$output .= "<a href=\"javascript:void(0);\" data-comment-id=\"{$comment['CommentID']}\">" .
						wfMessage( 'comments-show-comment-link' )->plain() . '</a> | ';
					$output .= "<a href=\"{$blockListTitle->escapeFullURL()}\">" .
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
				if( class_exists( 'wAvatar' ) ) {
					$avatar = new wAvatar( $comment['Comment_user_id'], 'ml' );
					$avatar_img = $avatar->getAvatarURL() . "\n";
				}

				$output .= "<div id=\"comment-{$comment['CommentID']}\" class=\"c-item {$container_class}\" style=\"{$hide_comment_style}\">" . "\n";
				$output .= "<div class=\"c-avatar\">{$avatar_img}</div>" . "\n";
				$output .= '<div class="c-container">' . "\n";

				$output .= '<div class="c-user">' . "\n";

				$output .= "{$CommentPoster}";
				$output .= "<span class=\"c-user-level\">{$CommentPosterLevel}</span> {$block_link}" . "\n";

				wfSuppressWarnings(); // E_STRICT bitches about strtotime()
				$output .= '<div class="c-time">' .
					wfMessage(
						'comments-time-ago',
						self::getTimeAgo( strtotime( $comment['Comment_Date'] ) )
					)->parse() . '</div>' . "\n";
				wfRestoreWarnings();

				$output .= '<div class="c-score">' . "\n";

				if( $this->AllowMinus == true || $this->AllowPlus == true ) {
					$output .= '<span class="c-score-title">' .
						wfMessage( 'comments-score-text' )->plain() .
						" <span id=\"Comment{$comment['CommentID']}\">{$CommentScore}</span></span>";

					// Voting is possible only when database is unlocked
					if( !wfReadOnly() ) {
						if( !in_array( $comment['CommentID'], $voted ) ) {
							// You can only vote for other people's comments,
							// not for your own
							if( $this->getUser()->getName() != $comment['Comment_Username'] ) {
								$output .= "<span id=\"CommentBtn{$comment['CommentID']}\">";
								if( $this->AllowPlus == true ) {
									$output .= $this->getVoteLink( $comment['CommentID'], 1 );
								}

								if( $this->AllowMinus == true ) {
									$output .= $this->getVoteLink( $comment['CommentID'], -1 );
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

				// The current title points to Special:CommentListGet...and that
				// special page shouldn't even exist, so we certainly don't want
				// to advertise it...let's point the permalink to the current
				// page instead :)
				$title = Title::newFromID( $this->PageID );

				$output .= '</div>' . "\n";
				$output .= "<div class=\"c-comment {$comment_class}\">" . "\n";
				$output .= $this->getCommentText( $comment['Comment_Text'] );
				$output .= '</div>' . "\n";
				$output .= '<div class="c-actions">' . "\n";
				$output .= '<a href="' . $title->escapeFullURL() . "#comment-{$comment['CommentID']}\" rel=\"nofollow\">" .
					wfMessage( 'comments-permalink' )->plain() . '</a> ';
				if( $replyRow || $dlt ) {
					$output .= "{$replyRow} {$dlt}" . "\n";
				}
				$output .= '</div>' . "\n";
				$output .= '</div>' . "\n";
				$output .= '<div class="cleared"></div>' . "\n";
				$output .= '</div>' . "\n";
			}
			$output .= $pager;
		}
		$output .= '<a id="end" name="end" rel="nofollow"></a>';
		return $output;
	}

	/**
	 * Displays the form for adding new comments
	 *
	 * @return $output Mixed: HTML output
	 */
	function displayForm() {
		$output = '<form action="" method="post" name="commentform">' . "\n";

		if( $this->Allow ) {
			$pos = strpos(
				strtoupper( addslashes( $this->Allow ) ),
				strtoupper( addslashes( $this->getUser()->getName() ) )
			);
		}

		// 'comment' user right is required to add new comments
		if( !$this->getUser()->isAllowed( 'comment' ) ) {
			$output .= wfMessage( 'comments-not-allowed' )->parse();
		} else {
			// Blocked users can't add new comments under any conditions...
			// and maybe there's a list of users who should be allowed to post
			// comments
			if( $this->getUser()->isBlocked() == false && ( $this->Allow == '' || $pos !== false ) ) {
				$output .= '<div class="c-form-title">' .
					wfMessage( 'comments-submit' )->plain() . '</div>' . "\n";
				$output .= '<div id="replyto" class="c-form-reply-to"></div>' . "\n";
				// Show a message to anons, prompting them to register or log in
				if ( !$this->getUser()->isLoggedIn() ) {
					$login_title = SpecialPage::getTitleFor( 'Userlogin' );
					$register_title = SpecialPage::getTitleFor( 'Userlogin', 'signup' );
					$output .= '<div class="c-form-message">' . wfMessage(
						'comments-anon-message',
						$register_title->escapeFullURL(),
						$login_title->escapeFullURL()
					)->text() . '</div>' . "\n";
				}

				$output .= '<textarea name="comment_text" id="comment" rows="5" cols="64"></textarea>' . "\n";
				$output .= '<div class="c-form-button"><input type="button" value="' .
					wfMessage( 'comments-post' )->plain() . '" class="site-button" /></div>' . "\n";
			}
			$output .= '<input type="hidden" name="action" value="purge" />' . "\n";
			$output .= '<input type="hidden" name="pid" value="' . $this->PageID . '" />' . "\n";
			$output .= '<input type="hidden" name="commentid" />' . "\n";
			$output .= '<input type="hidden" name="lastcommentid" value="' . $this->getLatestCommentID() . '" />' . "\n";
			$output .= '<input type="hidden" name="comment_parent_id" />' . "\n";
			$output .= '<input type="hidden" name="' . $this->PAGE_QUERY . '" value="' . $this->getCurrentPagerPage() . '" />' . "\n";
			$output .= Html::hidden( 'token', $this->getUser()->getEditToken() );
		}
		$output .= '</form>' . "\n";
		return $output;
	}

	/**
	 * Blocks comments from a user
	 *
	 * @param $userId Integer: user ID of the guy whose comments we want to block
	 * @param $userName Mixed: user name of the same guy
	 */
	public function blockUser( $userId, $userName ) {
		$dbw = wfGetDB( DB_MASTER );

		wfSuppressWarnings(); // E_STRICT bitching
		$date = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		$dbw->insert(
			'Comments_block',
			array(
				'cb_user_id' => $this->getUser()->getId(),
				'cb_user_name' => $this->getUser()->getName(),
				'cb_user_id_blocked' => $userId,
				'cb_user_name_blocked' => $userName,
				'cb_date' => $date
			),
			__METHOD__
		);
		$dbw->commit();
	}

	/**
	 * Fetches the list of blocked users from the database
	 *
	 * @param $userId Integer: user ID for whom we're getting the blocks(?)
	 * @return Array: list of comment-blocked users
	 */
	static function getBlockList( $userId ) {
		$blockList = array();
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'Comments_block',
			'cb_user_name_blocked',
			array( 'cb_user_id' => $userId ),
			__METHOD__
		);
		foreach( $res as $row ) {
			$blockList[] = $row->cb_user_name_blocked;
		}
		return $blockList;
	}

	static function isUserCommentBlocked( $userId, $userIdBlocked ) {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Comments_block',
			array( 'cb_id' ),
			array(
				'cb_user_id' => $userId,
				'cb_user_id_blocked' => $userIdBlocked
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
	 * Deletes a user from your personal comment-block list.
	 *
	 * @param $userId Integer: your user ID
	 * @param $userIdBlocked Integer: user ID of the blocked user
	 */
	public function deleteBlock( $userId, $userIdBlocked ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'Comments_block',
			array(
				'cb_user_id' => $userId,
				'cb_user_id_blocked' => $userIdBlocked
			),
			__METHOD__
		);
		$dbw->commit();
	}

}
