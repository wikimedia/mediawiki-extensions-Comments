<?php
/**
 * Comments of the Day parser hook -- shows the five newest comments that have
 * been sent within the last 24 hours.
 *
 * @file
 * @ingroup Extensions
 * @date 27 November 2015
 */

class CommentsOfTheDay {

	/**
	 * Register the new <commentsoftheday /> parser hook with the Parser.
	 *
	 * @param Parser $parser Instance of Parser
	 * @return bool
	 */
	public static function registerTag( &$parser ) {
		$parser->setHook( 'commentsoftheday', array( __CLASS__, 'getHTML' ) );
		return true;
	}

	/**
	 * Get comments of the day -- five newest comments within the last 24 hours
	 *
	 * @return string HTML
	 */
	public static function getHTML( $input, $args, $parser ) {
		$comments = self::get( (bool)$args['nocache'] );
		$commentOutput = '';

		foreach ( $comments as $comment ) {
			$commentOutput .= $comment->displayForCommentOfTheDay();
		}

		$output = '';
		if ( !empty( $commentOutput ) ) {
			$output .= $commentOutput;
		} else {
			$output .= wfMessage( 'comments-no-comments-of-day' )->plain();
		}

		return $output;
	}

	/**
	 * Get comments of the day, either from cache or the DB.
	 *
	 * @param bool $skipCache Skip using memcached and fetch data directly from the DB?
	 * @param int $cacheTime How long to cache the results in memcached? Default is one day (60 * 60 * 24).
	 * @param array $whereConds Additional WHERE conditions for the SQL clause
	 * @return array
	 */
	public static function get( $skipCache = false, $cacheTime = 86400, $whereConds = array() ) {
		global $wgMemc;

		// Try memcached first
		$key = wfMemcKey( 'comments-of-the-day', 'standalone-hook-new' );
		$data = $wgMemc->get( $key );

		if ( $data ) { // success, got it from memcached!
			$comments = $data;
		} elseif ( !$data || $skipCache ) { // just query the DB
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
				array( 'Comments', 'page' ),
				array(
					'Comment_Username', 'Comment_IP', 'Comment_Text',
					'Comment_Date', 'UNIX_TIMESTAMP(Comment_Date) AS timestamp',
					'Comment_User_Id', 'CommentID', 'Comment_Parent_ID',
					'Comment_Page_ID'
				),
				array_merge( $whereConds, array(
					'comment_page_id = page_id',
					'UNIX_TIMESTAMP(comment_date) > ' . ( time() - ( $cacheTime ) )
				) ),
				__METHOD__
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
					'Comment_user_id' => $row->Comment_User_Id,
					// @todo FIXME: absolutely disgusting -- should use Language's formatNum() for better i18n
					'Comment_user_points' => ( isset( $row->stats_total_points ) ? number_format( $row->stats_total_points ) : 0 ),
					'CommentID' => $row->CommentID,
					'Comment_Parent_ID' => $row->Comment_Parent_ID,
					'thread' => $thread,
					'timestamp' => $row->timestamp
				);

				$page = new CommentsPage( $row->Comment_Page_ID, new RequestContext() );
				$comments[] = new Comment( $page, new RequestContext(), $data );
			}

			usort( $comments, array( 'CommentFunctions', 'sortCommentScore' ) );
			$comments = array_slice( $comments, 0, 5 );

			$wgMemc->set( $key, $comments, $cacheTime );
		}

		return $comments;
	}

}