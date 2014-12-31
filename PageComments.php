<?php

/**
 * Class for Comments methods that are not specific to one comments,
 * but specific to one comment-using page
 */
class PageComments {

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

}