<?php

use MediaWiki\Context\ContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Class for Comments methods that are not specific to one comments,
 * but specific to one comment-using page
 */
class CommentsPage extends ContextSource {

	/**
	 * @var int page ID (page.page_id) of this page.
	 */
	public $id = 0;

	/**
	 * @var int if this is _not_ 0, then the comments are ordered by their
	 *			   Comment_Score in descending order
	 */
	public $orderBy = 0;

	/**
	 * @var int maximum amount of threads of comments shown per page before pagination is enabled;
	 */
	public $limit = 50;

	/**
	 * @todo document
	 *
	 * @var int
	 */
	public $pagerLimit = 9;

	/**
	 * The current page of comments we are paged to
	 *
	 * @var int
	 */
	public $currentPagerPage = 0;

	/**
	 * List of users allowed to comment. Empty string - any user can comment
	 *
	 * @var string
	 */
	public $allow = '';

	/**
	 * What voting to disallow - disallow PLUS, MINUS, or BOTH
	 *
	 * @var string
	 */
	public $voting = '';

	/**
	 * @var bool allow positive (plus) votes?
	 */
	public $allowPlus = true;

	/**
	 * @var bool allow negative (minus) votes?
	 */
	public $allowMinus = true;

	/**
	 * @todo document
	 *
	 * @var string
	 */
	public $pageQuery = 'cpage';

	/**
	 * @var Title title object for this page
	 */
	public $title = null;

	/**
	 * List of lists of comments on this page.
	 * Each list is a separate 'thread' of comments, with the parent comment first, and any replies following
	 * Not populated until display() is called
	 *
	 * @var array
	 */
	public $comments = [];

	/**
	 * Constructor
	 *
	 * @param int $pageID current page ID
	 * @param IContextSource $context
	 */
	public function __construct( $pageID, $context ) {
		$this->id = $pageID;
		$this->setContext( $context );
		$this->title = Title::newFromID( $pageID );
	}

	/**
	 * Set voting either totally off, or disallow "thumbs down" or disallow
	 * "thumbs up".
	 *
	 * @param string $voting 'OFF', 'PLUS' or 'MINUS' (will be strtoupper()ed)
	 */
	public function setVoting( $voting ) {
		$this->voting = $voting;
		$voting = strtoupper( $voting );

		if ( $voting == 'OFF' ) {
			$this->allowMinus = false;
			$this->allowPlus = false;
		}
		if ( $voting == 'PLUS' ) {
			$this->allowMinus = false;
		}
		if ( $voting == 'MINUS' ) {
			$this->allowPlus = false;
		}
	}

	/**
	 * Gets the total amount of comments on this page
	 *
	 * @return int
	 */
	public function countTotal() {
		$dbr = Comment::getDBHandle( 'read' );
		$count = 0;
		$s = $dbr->selectRow(
			'Comments',
			[ 'COUNT(*) AS CommentCount' ],
			[ 'Comment_Page_ID' => $this->id ],
			__METHOD__
		);
		if ( $s !== false ) {
			$count = $s->CommentCount;
		}
		return $count;
	}

	/**
	 * Gets the ID number of the latest comment for the current page.
	 *
	 * @return int
	 */
	public function getLatestCommentID() {
		$latestCommentID = 0;
		$dbr = Comment::getDBHandle( 'read' );
		$s = $dbr->selectRow(
			'Comments',
			[ 'CommentID' ],
			[ 'Comment_Page_ID' => $this->id ],
			__METHOD__,
			[ 'ORDER BY' => 'Comment_Date DESC', 'LIMIT' => 1 ]
		);
		if ( $s !== false ) {
			$latestCommentID = $s->CommentID;
		}
		return $latestCommentID;
	}

	/**
	 * Fetches all comments, called by display().
	 *
	 * @return array Array containing every possible bit of information about
	 * 				a comment, including score, timestamp and more
	 */
	public function getCommentsThreads() {
		$commentThreads = [];
		$commentsData = [];
		$comments = [];

		// Try to fetch page comments from cache first
		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getInstance( CACHE_ANYTHING );
		$cacheKey = $cache->makeKey(
			'comments',
			$this->id,
			$this->getCurrentPagerPage(),
			$this->getSort()
		);
		$cachedValue = $cache->get( $cacheKey );
		if ( !$cachedValue ) {
			// Fetch comments data from database if cache was not hit
			$commentsData = $this->getCommentsDataFromDatabase();
			$cache->set( $cacheKey, $commentsData );
		} else {
			// Get comments data from cache if it was hit
			$commentsData = $cachedValue;
		}

		// Build Comment's from data array
		foreach ( $commentsData as $data ) {
			$comments[] = new Comment( $this, $this->getContext(), $data );
		}

		// Build top comments array
		foreach ( $comments as $comment ) {
			if ( $comment->parentID == 0 ) {
				$commentThreads[$comment->id][] = $comment;
			}
		}

		// Build threads array
		foreach ( $comments as $comment ) {
			if ( $comment->parentID != 0 ) {
				$commentThreads[$comment->parentID][] = $comment;
			}
		}

		// Sort replies, always descending
		foreach ( $commentThreads as &$thread ) {
			if ( count( $thread ) ) {
				usort( $thread, [ 'CommentFunctions', 'sortDescComments' ] );
			}
		}

		return $commentThreads;
	}

	/**
	 * @return int The page we are currently paged to
	 * not used for any API calls
	 */
	public function getCurrentPagerPage() {
		if ( $this->currentPagerPage == 0 ) {
			$this->currentPagerPage = $this->getRequest()->getInt( $this->pageQuery, 1 );

			if ( $this->currentPagerPage < 1 ) {
				$this->currentPagerPage = 1;
			}
		}

		return $this->currentPagerPage;
	}

	/**
	 * Display pager for the current page.
	 *
	 * @param int $pagerCurrent Page we are currently paged to
	 * @param int $pagesCount The maximum page number
	 *
	 * @return string the links for paging through pages of comments
	 */
	public function displayPager( $pagerCurrent, $pagesCount ) {
		// Middle is used to "center" pages around the current page.
		$pager_middle = ceil( $this->pagerLimit / 2 );
		// first is the first page listed by this pager piece (re quantity)
		$pagerFirst = $pagerCurrent - $pager_middle + 1;
		// last is the last page listed by this pager piece (re quantity)
		$pagerLast = $pagerCurrent + $this->pagerLimit - $pager_middle;

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
			$output .= '<ul class="c-pager">';
			$pagerEllipsis = '<li class="c-pager-item c-pager-ellipsis"><span>...</span></li>';

			// Whether to display the "Previous page" link
			if ( $pagerCurrent > 1 ) {
				$output .= '<li class="c-pager-item c-pager-previous">' .
					Html::rawElement(
						'a',
						[
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->pageQuery => ( $pagerCurrent - 1 ),
						],
						'&lt;'
					) .
					'</li>';
			}

			// Whether to display the "First page" link
			if ( $i > 1 ) {
				$output .= '<li class="c-pager-item c-pager-first">' .
					Html::rawElement(
						'a',
						[
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->pageQuery => 1,
						],
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
								[
									'rel' => 'nofollow',
									'class' => 'c-pager-link',
									'href' => '#cfirst',
									'data-' . $this->pageQuery => $i,
								],
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
						[
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->pageQuery => $pagesCount,
						],
						$pagesCount
					) .
					'</li>';
			}

			// Whether to display the "Next page" link
			if ( $pagerCurrent < $pagesCount ) {
				$output .= '<li class="c-pager-item c-pager-next">' .
					Html::rawElement(
						'a',
						[
							'rel' => 'nofollow',
							'class' => 'c-pager-link',
							'href' => '#cfirst',
							'data-' . $this->pageQuery => ( $pagerCurrent + 1 ),
						],
						'&gt;'
					) .
					'</li>';
			}

			$output .= '</ul>';
		}

		return $output;
	}

	/**
	 * Get this list of anon commenters in the given list of comments,
	 * and return a mapped array of IP adressess to the number anon poster
	 * (so anon posters can be called Anon#1, Anon#2, etc
	 *
	 * @return array
	 */
	public function getAnonList() {
		$counter = 1;
		$bucket = [];

		$commentThreads = $this->comments;

		$comments = []; // convert 2nd threads array to a simple list of comments
		foreach ( $commentThreads as $thread ) {
			$comments = array_merge( $comments, $thread );
		}
		usort( $comments, [ 'CommentFunctions', 'sortTime' ] );

		foreach ( $comments as $comment ) {
			if (
				!array_key_exists( $comment->user->getName(), $bucket ) &&
				$comment->user->isAnon()
			) {
				$bucket[$comment->username] = $counter;
				$counter++;
			}
		}

		return $bucket;
	}

	/**
	 * Display all the comments for the current page.
	 * CSS and JS is loaded in CommentsHooks.php
	 * @return string
	 */
	public function display() {
		$output = '';

		$commentThreads = $this->getCommentsThreads();
		$this->comments = $commentThreads;

		$currentPageNum = $this->getCurrentPagerPage();
		$numPages = (int)ceil( $this->countTotal() / $this->limit );

		// Load complete blocked list for logged in user so they don't see their comments
		$blockList = [];
		if ( $this->getUser()->isRegistered() ) {
			$blockList = CommentFunctions::getBlockList( $this->getUser() );
		}

		$pager = $this->displayPager( $currentPageNum, $numPages );
		$output .= $pager;
		$output .= '<a id="cfirst" name="cfirst" rel="nofollow"></a>';

		$anonList = $this->getAnonList();

		foreach ( $commentThreads as $thread ) {
			foreach ( $thread as $comment ) {
				$output .= $comment->display( $blockList, $anonList );
			}
		}
		$output .= $pager;

		return $output;
	}

	/**
	 * Displays the "Sort by X" form and a link to auto-refresh comments
	 *
	 * @return string HTML
	 */
	public function displayOrderForm() {
		$output = '<div class="c-order">
			<div class="c-order-select">
				<form name="ChangeOrder" action="">
					<select name="TheOrder">
						<option value="0">' .
			wfMessage( 'comments-sort-by-date' )->escaped() .
			'</option>
						<option value="1">' .
			wfMessage( 'comments-sort-by-score' )->escaped() .
			'</option>
					</select>
				</form>
			</div>
			<div id="spy" class="c-spy">
				<a href="javascript:void(0)">' .
			wfMessage( 'comments-auto-refresher-enable' )->escaped() .
			'</a>
			</div>
			<div class="visualClear"></div>
		</div>
		<br />' . "\n";

		return $output;
	}

	/**
	 * Displays the form for adding new comments
	 *
	 * @return string HTML output
	 */
	public function displayForm() {
		$output = '<form action="" method="post" name="commentForm">' . "\n";

		$pos = false;
		if ( $this->allow ) {
			$pos = strpos(
				strtoupper( addslashes( $this->allow ) ),
				strtoupper( addslashes( $this->getUser()->getName() ) )
			);
		}

		$user = $this->getUser();

		// Use these for the block/global block check below
		$context = RequestContext::getMain();
		$userContext = $context->getUser();
		$language = $context->getLanguage();
		$ip = $context->getRequest()->getIP();
		$errorFormatter = MediaWikiServices::getInstance()->getBlockErrorFormatter();

		// Check users block status
		if ( $user->getBlock() ) {
			$output .= $errorFormatter
				->getMessage( $user->getBlock(), $userContext, $language, $ip )
				->parse();
		} elseif ( !$this->getUser()->isAllowed( 'comment' ) ) {
			// 'comment' user right is required to add new comments
			$output .= wfMessage( 'comments-not-allowed' )->parse();
			$output .= '<input type="hidden" name="lastCommentId" value="' . $this->getLatestCommentID() . '" />' . "\n";
		} else {
			$output .= '<div class="c-form-title">' . wfMessage( 'comments-submit' )->escaped() . '</div>' . "\n";
			$output .= '<div id="replyto" class="c-form-reply-to"></div>' . "\n";
			// Show a message to anons, prompting them to register or log in
			if ( !$user->isRegistered() ) {
				$output .= '<div class="c-form-message">' .
					wfMessage( 'comments-anon-message' )->parse() . '</div>' . "\n";
			}

			$output .= '<textarea name="commentText" id="comment" rows="5" cols="64"></textarea>' . "\n";
			$output .= '<div class="comment-preview"></div>';
			$output .= '<div class="c-form-button">';
			$output .= '<input type="button" value="' . wfMessage( 'comments-post' )->escaped() .
				'" class="site-button" name="wpSubmitComment" />' . "\n";
			$output .= '<input type="button" value="' . wfMessage( 'showpreview' )->escaped() .
				'" class="site-button" name="wpPreview" />';
			$output .= '</div>' . "\n";
		}
		$output .= '<input type="hidden" name="action" value="purge" />' . "\n";
		$output .= '<input type="hidden" name="pageId" value="' . $this->id . '" />' . "\n";
		$output .= '<input type="hidden" name="commentid" />' . "\n";
		$output .= '<input type="hidden" name="lastCommentId" value="' . $this->getLatestCommentID() . '" />' . "\n";
		$output .= '<input type="hidden" name="commentParentId" />' . "\n";
		$output .= '<input type="hidden" name="' . $this->pageQuery . '" value="' . $this->getCurrentPagerPage() . '" />' . "\n";
		$output .= Html::hidden( 'token', $this->getUser()->getEditToken() );
		$output .= '</form>' . "\n";
		return $output;
	}

	/**
	 * Purge caches (parser cache and Squid cache)
	 */
	public function clearCommentListCache() {
		wfDebug( "Clearing comments for page {$this->id} from cache\n" );
		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getInstance( CACHE_ANYTHING );
		// Delete all possible keys for the page
		// @TODO: this duplicates values returned by getSort()
		$sorts = [ 'comment_score DESC', 'timestamp DESC', 'timestamp ASC' ];
		$pages = (int)ceil( $this->countTotal() / $this->limit );
		$keys = [];
		foreach ( $sorts as $sort ) {
			for ( $page = 0; $page <= $pages; $page++ ) {
				$keys[] = $cache->makeKey(
					'comments',
					$this->id,
					$page,
					$sort
				);
			}
		}
		$cache->deleteMulti( $keys );
	}

	/**
	 * Builds sort part of query depending on options set
	 * @return string Sort ordering to use as the ORDER BY statement in an SQL query
	 */
	private function getSort() {
		global $wgCommentsSortDescending;

		if ( $this->orderBy ) {
			return 'comment_score DESC';
		} elseif ( $wgCommentsSortDescending ) {
			return 'timestamp DESC';
		} else {
			return 'timestamp ASC';
		}
	}

	/**
	 * Fetches comments data from database
	 * @return array
	 */
	private function getCommentsDataFromDatabase() {
		$dbr = Comment::getDBHandle( 'read' );
		// Defaults (for non-social wikis)
		$tables = [
			'Comments',
			'vote1' => 'Comments_Vote',
			'vote2' => 'Comments_Vote',
		];
		$fields = [
			'Comment_IP', 'Comment_Text', 'Comment_actor',
			'Comment_Date', 'Comment_Date AS timestamp',
			'CommentID', 'Comment_Parent_ID',
			// @todo FIXME: this and the stats_total_points are buggy on PostgreSQL
			// Skizzerz says that the whole query is bugged in general but MySQL "helpfully"
			// ignores the bugginess and returns potentially incorrect results
			// I just lazily slapped current_vote (and stats_total_points in the "if SP is installed" loop)
			// to the GROUP BY condition to try to remedy this. --ashley, 17 January 2020
			'vote1.Comment_Vote_Score AS current_vote',
			'SUM(vote2.Comment_Vote_Score) AS comment_score'
		];
		$joinConds = [
			// For current user's vote
			'vote1' => [
				'LEFT JOIN',
				[
					'vote1.Comment_Vote_ID = CommentID',
					'vote1.Comment_Vote_actor' => $this->getUser()->getActorId()
				]
			],
			// For total vote count
			'vote2' => [ 'LEFT JOIN', 'vote2.Comment_Vote_ID = CommentID' ]
		];
		$params = [ 'GROUP BY' => 'CommentID, current_vote' ];

		// If SocialProfile is installed, query the user_stats table too.
		if (
			class_exists( 'UserProfile' ) &&
			$dbr->tableExists( 'user_stats', __METHOD__ )
		) {
			$tables[] = 'user_stats';
			$fields[] = 'stats_total_points';
			$joinConds['Comments'] = [
				'LEFT JOIN', 'Comment_actor = stats_actor'
			];
			$params['GROUP BY'] .= ', stats_total_points';
		}

		// Sort
		$params['ORDER BY'] = $this->getSort();

		// Limit amount of comments being queried based on
		// per page limit and current page offset
		$params['LIMIT'] = $this->limit;
		$params['OFFSET'] = ( $this->currentPagerPage - 1 ) * $this->limit;

		// Perform the query
		$res = $dbr->select(
			$tables,
			$fields,
			[ 'Comment_Page_ID' => $this->id ],
			__METHOD__,
			$params,
			$joinConds
		);

		$commentsData = [];

		foreach ( $res as $row ) {
			if ( $row->Comment_Parent_ID == 0 ) {
				$thread = $row->CommentID;
			} else {
				$thread = $row->Comment_Parent_ID;
			}
			$data = [
				'Comment_actor' => $row->Comment_actor,
				'Comment_IP' => $row->Comment_IP,
				'Comment_Text' => $row->Comment_Text,
				'Comment_Date' => $row->Comment_Date,
				'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
				'CommentID' => $row->CommentID,
				'Comment_Parent_ID' => $row->Comment_Parent_ID,
				'thread' => $thread,
				'timestamp' => wfTimestamp( TS_UNIX, $row->timestamp ),
				'current_vote' => ( isset( $row->current_vote ) ? $row->current_vote : false ),
				'total_vote' => ( isset( $row->comment_score ) ? $row->comment_score : 0 ),
			];

			$commentsData[] = $data;
		}

		return $commentsData;
	}

}
