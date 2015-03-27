<?php

/**
 * Class for Comments methods that are not specific to one comments,
 * but specific to one comment-using page
 */
class CommentsPage extends ContextSource {

    /**
     * @var Integer: page ID (page.page_id) of this page.
     */
    public $id = 0;

    /**
     * @var Integer: if this is _not_ 0, then the comments are ordered by their
     *               Comment_Score in descending order
     */
    public $orderBy = 0;

    /**
     * @var Integer: maximum amount of comments shown per page before pagination
     *               is enabled; also used as the LIMIT for the SQL query
     */
    public $limit = 100;

    /**
     * @TODO document
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
     * @var Boolean: allow positive (plus) votes?
     */
    public $allowPlus = true;

    /**
     * @var Boolean: allow negative (minus) votes?
     */
    public $allowMinus = true;

    /**
     * @TODO document
     *
     * @var string
     */
    public $pageQuery = 'cpage';

    /**
     * @var Title: title object for this page
     */
    public $title = null;

    /**
     * List of comments on this page.
     * Not populated until display() is called
     *
     * @var array
     */
    public $comments = array();

    /**
     * Constructor
     *
     * @param $pageID: current page ID
     */
    function __construct ( $pageID, $context ) {
        $this->id = $pageID;
        $this->setContext( $context );
        $this->title = Title::newFromID( $pageID );
    }

    /**
     * Gets the total amount of comments on this page
     *
     * @return int
     */
    function countTotal() {
        $dbr = wfGetDB( DB_SLAVE );
        $count = 0;
        $s = $dbr->selectRow(
            'Comments',
            array( 'COUNT(*) AS CommentCount' ),
            array( 'Comment_Page_ID' => $this->id ),
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
    function getLatestCommentID() {
        $latestCommentID = 0;
        $dbr = wfGetDB( DB_SLAVE );
        $s = $dbr->selectRow(
            'Comments',
            array( 'CommentID' ),
            array( 'Comment_Page_ID' => $this->id ),
            __METHOD__,
            array( 'ORDER BY' => 'Comment_Date DESC', 'LIMIT' => 1 )
        );
        if ( $s !== false ) {
            $latestCommentID = $s->CommentID;
        }
        return $latestCommentID;
    }

    /**
     * Set voting either totally off, or disallow "thumbs down" or disallow
     * "thumbs up".
     *
     * @param string $voting 'OFF', 'PLUS' or 'MINUS' (will be strtoupper()ed)
     */
    function setVoting( $voting ) {
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
     * Fetches all comments, called by display().
     *
     * @param $page: used for paging through results
     *
     * @return array Array containing every possible bit of information about
     *                a comment, including score, timestamp and more
     */
    public function getCommentList( $page ) {
        global $wgCommentsSortDescending;
        $dbr = wfGetDB( DB_SLAVE );

        $tables = array();
        $params = array();
        $joinConds = array();

        // Defaults (for non-social wikis)
        $tables[] = 'Comments';
        $fields = array(
            'Comment_Username', 'Comment_IP', 'Comment_Text',
            'Comment_Date', 'UNIX_TIMESTAMP(Comment_Date) AS timestamp',
            'Comment_user_id', 'CommentID',
            'IFNULL(Comment_Plus_Count - Comment_Minus_Count,0) AS Comment_Score',
            'Comment_Parent_ID', 'CommentID', 'Comment_Plus_Count AS CommentVotePlus',
			'Comment_Minus_Count AS CommentVoteMinus'
        );
        $params['LIMIT'] = $this->limit;
        $params['OFFSET'] = ( $page > 0 ) ? ( ( $page - 1 ) * $this->limit ) : 0;
        if ( $this->orderBy != 0 ) {
            $params['ORDER BY'] = 'Comment_Score DESC';
        }

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
            array( 'Comment_Page_ID' => $this->id ),
            __METHOD__,
            $params,
            $joinConds
        );

        $comments = array();

        foreach ( $res as $row ) {
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

            $comments[] = new Comment( $this, $this->getContext(), $data );
        }

        if ( $this->orderBy == 0 ) {
            if ( $wgCommentsSortDescending ) {
                usort( $comments, 'CommentFunctions::sortDesc' );
            } else {
                usort( $comments, 'CommentFunctions::sortAsc' );
            }
        }

        return $comments;
    }

    /**
     * @return int The page we are currently paged to
     * not used for any API calls
     */
    function getCurrentPagerPage() {
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
     * @return string: the links for paging through pages of comments
     */
    function displayPager( $pagerCurrent, $pagesCount ) {
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
                        array(
                            'rel' => 'nofollow',
                            'class' => 'c-pager-link',
                            'href' => '#cfirst',
                            'data-' . $this->pageQuery => ( $pagerCurrent - 1 ),
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
                            'data-' . $this->pageQuery => 1,
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
                                    'data-' . $this->pageQuery => $i,
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
                            'data-' . $this->pageQuery => $pagesCount,
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
                            'data-' . $this->pageQuery => ( $pagerCurrent + 1 ),
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
     * Get this list of anon commenters in the given list of comments,
     * and return a mapped array of IP adressess to the the number anon poster
     * (so anon posters can be called Anon#1, Anon#2, etc
     *
     * @return array
     */
    function getAnonList() {
        $counter = 1;
        $bucket = array();

        $comments = $this->comments;
        usort( $comments, array( 'CommentFunctions', 'sortTime' ) );

        foreach ( $comments as $comment ) {
            if (
                !array_key_exists( $comment->username, $bucket ) &&
                $comment->userID == 0
            ) {
                $bucket[$comment->username] = $counter;
                $counter++;
            }
        }

        return $bucket;
    }

    /**
     * Display all the comments for the current page.
     * CSS and JS is loaded in Comment.php
     */
    function display() {
        global $wgMemc;

        $output = '';

        // TODO: Try cache
        wfDebug( "Loading comments count for page {$this->id} from DB\n" );
        $commentsCount = $this->countTotal();
        $pagesCount = ( ( $commentsCount % $this->limit ) > 0 )
            ? ( 1 +  floor( $commentsCount / $this->limit ) ) :  floor( $commentsCount / $this->limit );
        $pagerCurrent = $this->getCurrentPagerPage( $pagesCount );

        if ( $pagerCurrent > $pagesCount ) {
            $pagerCurrent = $pagesCount;
        }

        // Try cache
        $key = wfMemcKey( 'comment', 'pagelist', $this->id );
        $data = $wgMemc->get( $key );

        if ( !$data ) {
            wfDebug( "Loading comments for page {$this->id} from DB\n" );
            $comments = $this->getCommentList( $pagerCurrent );
            $wgMemc->set( $key, $comments );
        } else {
            wfDebug( "Loading comments for page {$this->id} from cache\n" );
            $comments = $data;
        }

        $this->comments = $comments;

        // Load complete blocked list for logged in user so they don't see their comments
        $blockList = array();
        if ( $this->getUser()->getID() != 0 ) {
            $blockList = CommentFunctions::getBlockList( $this->getUser()->getId() );
        }

        if ( $comments ) {
            $pager = $this->displayPager( $pagerCurrent, $pagesCount );
            $output .= $pager;
            $output .= '<a id="cfirst" name="cfirst" rel="nofollow"></a>';

            $anonList = $this->getAnonList();

            foreach ( $comments as $comment ) {
                $output .= $comment->display( $blockList, $anonList );
            }
            $output .= $pager;
        }

        return $output;
    }

    /**
     * Displays the "Sort by X" form and a link to auto-refresh comments
     *
     * @return string HTML
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

    /**
     * Displays the form for adding new comments
     *
     * @return string HTML output
     */
    function displayForm() {
        $output = '<form action="" method="post" name="commentForm">' . "\n";

        if ( $this->allow ) {
            $pos = strpos(
                strtoupper( addslashes( $this->allow ) ),
                strtoupper( addslashes( $this->getUser()->getName() ) )
            );
        }

        // 'comment' user right is required to add new comments
        if ( !$this->getUser()->isAllowed( 'comment' ) ) {
            $output .= wfMessage( 'comments-not-allowed' )->parse();
        } else {
            // Blocked users can't add new comments under any conditions...
            // and maybe there's a list of users who should be allowed to post
            // comments
            if ( $this->getUser()->isBlocked() == false && ( $this->allow == '' || $pos !== false ) ) {
                $output .= '<div class="c-form-title">' . wfMessage( 'comments-submit' )->plain() . '</div>' . "\n";
                $output .= '<div id="replyto" class="c-form-reply-to"></div>' . "\n";
                // Show a message to anons, prompting them to register or log in
                if ( !$this->getUser()->isLoggedIn() ) {
                    $login_title = SpecialPage::getTitleFor( 'Userlogin' );
                    $register_title = SpecialPage::getTitleFor( 'Userlogin', 'signup' );
                    $output .= '<div class="c-form-message">' . wfMessage(
                            'comments-anon-message',
                            htmlspecialchars( $register_title->getFullURL() ),
                            htmlspecialchars( $login_title->getFullURL() )
                        )->text() . '</div>' . "\n";
                }

                $output .= '<textarea name="commentText" id="comment" rows="5" cols="64"></textarea>' . "\n";
                $output .= '<div class="c-form-button"><input type="button" value="' .
                    wfMessage( 'comments-post' )->plain() . '" class="site-button" /></div>' . "\n";
            }
            $output .= '<input type="hidden" name="action" value="purge" />' . "\n";
            $output .= '<input type="hidden" name="pageId" value="' . $this->id . '" />' . "\n";
            $output .= '<input type="hidden" name="commentid" />' . "\n";
            $output .= '<input type="hidden" name="lastCommentId" value="' . $this->getLatestCommentID() . '" />' . "\n";
            $output .= '<input type="hidden" name="commentParentId" />' . "\n";
            $output .= '<input type="hidden" name="' . $this->pageQuery . '" value="' . $this->getCurrentPagerPage() . '" />' . "\n";
            $output .= Html::hidden( 'token', $this->getUser()->getEditToken() );
        }
        $output .= '</form>' . "\n";
        return $output;
    }

    /**
     * Purge caches (memcached, parser cache and Squid cache)
     */
    function clearCommentListCache() {
        global $wgMemc;
        wfDebug( "Clearing comments for page {$this->id} from cache\n" );
        $key = wfMemcKey( 'comment', 'pagelist', $this->id );
        $wgMemc->delete( $key );

        if ( is_object( $this->title ) ) {
            $this->title->invalidateCache();
            $this->title->purgeSquid();
        }
    }

}