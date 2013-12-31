<?php

class NumberOfComments {
	/**
	 * Registers NUMBEROFCOMMENTS and NUMPBEROFCOMMENTSPAGE as a valid magic word identifier.
	 *
	 * @param $variableIds Array: array of valid magic word identifiers
	 * @return Boolean
	 */
	public static function registerNumberOfCommentsMagicWord( &$variableIds ) {
		$variableIds[] = 'NUMBEROFCOMMENTS';
		$variableIds[] = 'NUMBEROFCOMMENTSPAGE';
		return true;
	}

	/**
	 * Hook to setup parser function
	 * @param Parser $parser
	 * @return boolean
	 */
	static function setupNumberOfCommentsPageParser( &$parser ) {
		$parser->setFunctionHook( 'NUMBEROFCOMMENTSPAGE', 'NumberOfComments::getNumberOfCommentsPageParser', SFH_NO_HASH );
		return true;
	}

	/**
	 * Main backend logic for the {{NUMBEROFCOMMENTS}} and {{NUMBEROFCOMMENTSPAGE}}
	 * magic word.
	 * If the {{NUMBEROFCOMMENTS}} magic word is found, first checks memcached to
	 * see if we can get the value from cache, but if that fails for  some reason,
	 * then a COUNT(*) SQL query is done to fetch the amount from the database.
	 * If the {{NUMBEROFCOMMENTSPAGE}} magic word is found, uses
	 * NumberOfComments::getNumberOfCommentsPage to get the number of comments
	 * for this article.
	 *
	 * @param $parser Parser
	 * @param $cache
	 * @param $magicWordId String: magic word identifier
	 * @param $ret Integer: what to return to the user (in our case, the number of comments)
	 * @return Boolean
	 */
	public static function getNumberOfCommentsMagic( &$parser, &$cache, &$magicWordId, &$ret ) {
		global $wgMemc;

		if ( $magicWordId == 'NUMBEROFCOMMENTS' ) {
			$key = wfMemcKey( 'comments', 'magic-word' );
			$data = $wgMemc->get( $key );
			if ( $data != '' ) {
				// We have it in cache? Oh goody, let's just use the cached value!
				wfDebugLog(
					'Comments',
					'Got the amount of comments from memcached'
				);
				// return value
				$ret = $data;
			} else {
				// Not cached → have to fetch it from the database
				$dbr = wfGetDB( DB_SLAVE );
				$commentCount = (int)$dbr->selectField(
					'Comments',
					'COUNT(*) AS count',
					array(),
					__METHOD__
				);
				wfDebugLog( 'Comments', 'Got the amount of comments from DB' );
				// Store the count in cache...
				// (86400 = seconds in a day)
				$wgMemc->set( $key, $commentCount, 86400 );
				// ...and return the value to the user
				$ret = $commentCount;
			}
		} elseif ( $magicWordId == 'NUMBEROFCOMMENTSPAGE' ) {
			$id = $parser->getTitle()->getArticleID();
			$ret = NumberOfComments::getNumberOfCommentsPage( $id );
		}

		return true;
	}

	/**
	 * Hook for parser function {{NUMBEROFCOMMENTSPAGE:<page>}}
	 * @param Parser $parser
	 * @param string $pagename
	 * @return number
	 */
	static function getNumberOfCommentsPageParser( $parser, $pagename ) {
		$page = Title::newFromText( $pagename );

		if ( $page instanceof Title ) {
			$id = $page->getArticleID();
		} else {
			$id = $parser->getTitle()->getArticleID();
		}

		return NumberOfComments::getNumberOfCommentsPage( $id );
	}

	/**
	 * Get the actual number of comments
	 * @param int $pageId: ID of page to get number of comments for
	 * @return int
	 */
	static function getNumberOfCommentsPage( $pageId ) {
		global $wgMemc;

		$key = wfMemcKey( 'comments', 'numberofcommentspage', $pageId );
		$cache = $wgMemc->get( $key );

		if ( $cache ) {
			$val = intval( $cache );
		} else {
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->selectField(
				'Comments',
				'COUNT(*)',
				array( 'Comment_Page_ID' => $pageId ),
				__METHOD__
			);

			if ( !$res ) {
				$val = 0;
			} else {
				$val = intval( $res );
			}
			$wgMemc->set( $key, $val, 60 * 60 ); // cache for an hour
		}
		return $val;
	}

}