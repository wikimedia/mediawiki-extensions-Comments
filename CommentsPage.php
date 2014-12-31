<?php

/**
 * Class for Comments methods that are not specific to one comments,
 * but specific to one comment-using page
 */
class CommentsPage extends ContextSource {

    /**
     * @var Integer: page ID (page.page_id) of this page.
     */
    public $PageID = 0;

    /**
     * @var Integer: if this is _not_ 0, then the comments are ordered by their
     *               Comment_Score in descending order
     */
    public $OrderBy = 0; // @TODO remove, not specific to one comment

    /**
     * @var Integer: maximum amount of comments shown per page before pagination
     *               is enabled; also used as the LIMIT for the SQL query
     */
    public $Limit = 100;

    /**
     * @TODO document
     *
     * @var int
     */
    public $PagerLimit = 9;

    /**
     * @TODO document
     *
     * @var int
     */
    public $CurrentPagerPage = 0;

    /**
     * @TODO document
     *
     * @var string
     */
    public $Allow = '';

    /**
     * @TODO document
     *
     * @var string
     */
    public $Voting = '';

    /**
     * @var Boolean: allow positive (plus) votes?
     */
    public $AllowPlus = true;

    /**
     * @var Boolean: allow negative (minus) votes?
     */
    public $AllowMinus = true;

    /**
     * @TODO document
     *
     * @var string
     */
    public $PAGE_QUERY = 'cpage';

    /**
     * Constructor
     *
     * @param $pageID: current page ID
     */
    function __construct ( $pageID, $context ) {
        $this->PageID = $pageID;
        $this->setContext( $context );
    }

    /**
     * @TODO document
     *
     * @param $order
     */
    function setOrderBy( $order ) {
        if ( is_numeric( $order ) ) {
            if ( $order == 0 ) {
                $order = 0;
            } else {
                $order = 1;
            }
            $this->OrderBy = $order;
        }
    }

    /**
     * Gets the total amount of comments on this page
     * @TODO but the below function?
     *
     * @return int
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
     * Counts the amount of comments the current page has.
     * @TODO but the above function?
     *
     * @return int Amount of comments
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
     * Gets the ID number of the latest comment for the current page.
     *
     * @return int
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
     * Sets the list of users who are allowed to comment.
     *
     * @param string $allow List of users allowed to comment
     */
    function setAllow( $allow ) {
        $this->Allow = $allow;
    }

    /**
     * Set voting either totally off, or disallow "thumbs down" or disallow
     * "thumbs up".
     *
     * @param string $voting 'OFF', 'PLUS' or 'MINUS' (will be strtoupper()ed)
     */
    function setVoting( $voting ) {
        $this->Voting = $voting;
        $voting = strtoupper( $voting );

        if ( $voting == 'OFF' ) {
            $this->AllowMinus = false;
            $this->AllowPlus = false;
        }
        if ( $voting == 'PLUS' ) {
            $this->AllowMinus = false;
        }
        if ( $voting == 'MINUS' ) {
            $this->AllowPlus = false;
        }
    }

    /**
     * Check what comments the current user has voted on.
     *
     * @return array Array of comment ID numbers
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
        foreach ( $res as $row ) {
            $voted[] = $row->CommentID;
        }

        return $voted;
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
        $dbr = wfGetDB( DB_SLAVE );

        $tables = array();
        $fields = array(); // @TODO unused
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
        if ( $this->OrderBy != 0 ) {
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
            array( 'Comment_Page_ID' => $this->PageID ),
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
                # 'AlreadyVoted' => $row->AlreadyVoted, // misza: turned off - no such crap
                'Comment_Parent_ID' => $row->Comment_Parent_ID,
                'thread' => $thread,
                'timestamp' => $row->timestamp
            );
        }

        if ( $this->OrderBy == 0 ) {
            usort( $comments, array( 'Comment', 'sortCommentList' ) );
        }

        return $comments;
    }

    /**
     * @return int The page we are currently paged to
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
     * Display pager for the current page.
     *
     * @param int $pagerCurrent Page we are currently paged to
     * @param int $pagesCount The maximum page number
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
     * Display all the comments for the current page.
     * CSS and JS is loaded in Comment.php, function displayComments. @TODO update
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

        if ( !$data ) {
            wfDebug( "Loading comments for page {$this->PageID} from DB\n" );
            $comments = $this->getCommentList( $pagerCurrent );
            $wgMemc->set( $key, $comments );
        } else {
            wfDebug( "Loading comments for page {$this->PageID} from cache\n" );
            $comments = $data;
        }

        // Try cache for voted list for this user
        $voted = array();
        if ( $this->getUser()->isLoggedIn() ) {
            $key = wfMemcKey( 'comment', 'voted', $this->PageID, 'user_id', $this->getUser()->getID() );
            $data = $wgMemc->get( $key );

            if ( !$data ) {
                $voted = $this->getCommentVotedList();
                $wgMemc->set( $key, $voted );
            } else {
                wfDebug( "Loading comment voted for page {$this->PageID} for user {$this->getUser()->getID()} from cache\n" );
                $voted = $data;
            }
        }

        // Load complete blocked list for logged in user so they don't see their comments
        $block_list = array();
        if ( $this->getUser()->getID() != 0 ) {
            $block_list = CommentFunctions::getBlockList( $this->getUser()->getId() );
        }

        $AFCounter = 1;
        $AFBucket = array();
        if ( $comments ) {
            $pager = $this->displayPager( $pagerCurrent, $pagesCount );
            $output .= $pager;
            $output .= '<a id="cfirst" name="cfirst" rel="nofollow"></a>';
            foreach ( $comments as $comment ) {
                $CommentScore = $comment['Comment_Score'];

                $CommentPosterLevel = '';

                if ( $comment['Comment_user_id'] != 0 ) {
                    $title = Title::makeTitle( NS_USER, $comment['Comment_Username'] );

                    $CommentPoster = '<a href="' . htmlspecialchars( $title->getFullURL() ) .
                        '" rel="nofollow">' . $comment['Comment_Username'] . '</a>';

                    $CommentReplyTo = $comment['Comment_Username'];

                    if ( $wgUserLevels && class_exists( 'UserLevel' ) ) {
                        $user_level = new UserLevel( $comment['Comment_user_points'] );
                        $CommentPosterLevel = "{$user_level->getLevelName()}";
                    }

                    $user = User::newFromId( $comment['Comment_user_id'] );
                    $CommentReplyToGender = $user->getOption( 'gender', 'unknown' );
                } else {
                    if ( !array_key_exists( $comment['Comment_Username'], $AFBucket ) ) {
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

                if ( $this->getUser()->isAllowed( 'commentadmin' ) ) {
                    // $dlt = " | <span class=\"c-delete\"><a href=\"javascript:document.commentform.commentid.value={$comment['CommentID']};document.commentform.submit();\">" .
                    $dlt = ' | <span class="c-delete">' .
                        '<a href="javascript:void(0);" rel="nofollow" class="comment-delete-link" data-comment-id="' .
                        $comment['CommentID'] . '">' .
                        wfMessage( 'comments-delete-link' )->plain() . '</a></span>';
                }

                // Reply Link (does not appear on child comments)
                $replyRow = '';
                if ( $this->getUser()->isAllowed( 'comment' ) ) {
                    if ( $comment['Comment_Parent_ID'] == 0 ) {
                        if ( $replyRow ) {
                            $replyRow .= wfMessage( 'pipe-separator' )->plain();
                        }
                        $replyRow .= " | <a href=\"#end\" rel=\"nofollow\" class=\"comments-reply-to\" data-comment-id=\"{$comment['CommentID']}\" data-comments-safe-username=\"" .
                            htmlspecialchars( $CommentReplyTo, ENT_QUOTES ) . "\" data-comments-user-gender=\"" .
                            htmlspecialchars( $CommentReplyToGender ) . '">' .
                            wfMessage( 'comments-reply' )->plain() . '</a>';
                    }
                }

                if ( $comment['Comment_Parent_ID'] == 0 ) {
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

                if ( in_array( $comment['Comment_Username'], $block_list ) ) {
                    $hide_comment_style = 'display:none;';

                    $blockListTitle = SpecialPage::getTitleFor( 'CommentIgnoreList' );

                    $output .= "<div id=\"ignore-{$comment['CommentID']}\" class=\"c-ignored {$container_class}\">\n";
                    $output .= wfMessage( 'comments-ignore-message' )->parse();
                    $output .= '<div class="c-ignored-links">' . "\n";
                    $output .= "<a href=\"javascript:void(0);\" data-comment-id=\"{$comment['CommentID']}\">" .
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
                        CommentFunctions::getTimeAgo( strtotime( $comment['Comment_Date'] ) )
                    )->parse() . '</div>' . "\n";
                wfRestoreWarnings();

                $output .= '<div class="c-score">' . "\n";

                if ( $this->AllowMinus == true || $this->AllowPlus == true ) {
                    $output .= '<span class="c-score-title">' .
                        wfMessage( 'comments-score-text' )->plain() .
                        " <span id=\"Comment{$comment['CommentID']}\">{$CommentScore}</span></span>";

                    // Voting is possible only when database is unlocked
                    if ( !wfReadOnly() ) {
                        if ( !in_array( $comment['CommentID'], $voted ) ) {
                            // You can only vote for other people's comments,
                            // not for your own
                            if ( $this->getUser()->getName() != $comment['Comment_Username'] ) {
                                $output .= "<span id=\"CommentBtn{$comment['CommentID']}\">";
                                if ( $this->AllowPlus == true ) {
                                    $output .= $this->getVoteLink( $comment['CommentID'], 1 );
                                }

                                if ( $this->AllowMinus == true ) {
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
                $output .= '<a href="' . htmlspecialchars( $title->getFullURL() ) . "#comment-{$comment['CommentID']}\" rel=\"nofollow\">" .
                    wfMessage( 'comments-permalink' )->plain() . '</a> ';
                if ( $replyRow || $dlt ) {
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
        $output = '<form action="" method="post" name="commentform">' . "\n";

        if ( $this->Allow ) {
            $pos = strpos(
                strtoupper( addslashes( $this->Allow ) ),
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
            if ( $this->getUser()->isBlocked() == false && ( $this->Allow == '' || $pos !== false ) ) {
                $output .= '<div class="c-form-title">' .
                    wfMessage( 'comments-submit' )->plain() . '</div>' . "\n";
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

}