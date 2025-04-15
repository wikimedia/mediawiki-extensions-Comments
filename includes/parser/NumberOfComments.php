<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class NumberOfComments {
	/**
	 * Registers NUMBEROFCOMMENTS and NUMPBEROFCOMMENTSPAGE as a valid magic word identifier.
	 *
	 * @param array &$variableIds Array of valid magic word identifiers
	 * @return bool true
	 */
	public static function onMagicWordwgVariableIDs( &$variableIds ) {
		$variableIds[] = 'NUMBEROFCOMMENTS';
		$variableIds[] = 'NUMBEROFCOMMENTSPAGE';

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
	 * @param MediaWiki\Parser\Parser $parser
	 * @param array &$cache
	 * @param string $magicWordId Magic word identifier
	 * @param string|int &$ret What to return to the user (in our case, the number of comments)
	 * @return bool
	 */
	public static function onParserGetVariableValueSwitch( $parser, &$cache, $magicWordId, &$ret ) {
		if ( $magicWordId == 'NUMBEROFCOMMENTS' ) {
			$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$key = $wanCache->makeKey( 'comments', 'magic-word' );
			$data = $wanCache->get( $key );
			if ( $data != '' ) {
				// We have it in cache? Oh goody, let's just use the cached value!
				wfDebugLog(
					'Comments',
					'Got the amount of comments from memcached'
				);
				// return value
				$ret = $cache[$magicWordId] = $data;
			} else {
				// Not cached → have to fetch it from the database
				$dbr = Comment::getDBHandle( 'read' );
				$commentCount = (int)$dbr->selectField(
					'Comments',
					'COUNT(*) AS count',
					[],
					__METHOD__
				);
				wfDebugLog( 'Comments', 'Got the amount of comments from DB' );
				// Store the count in cache...
				// (86400 = seconds in a day)
				$wanCache->set( $key, $commentCount, 86400 );
				// ...and return the value to the user
				$ret = $cache[$magicWordId] = $commentCount;
			}
		} elseif ( $magicWordId == 'NUMBEROFCOMMENTSPAGE' ) {
			$id = $parser->getTitle()->getArticleID();
			$ret = $cache[$magicWordId] = self::getNumberOfCommentsPage( $id );
		}

		return true;
	}

	/**
	 * Hook for parser function {{NUMBEROFCOMMENTSPAGE:<page>}}
	 *
	 * @param MediaWiki\Parser\Parser $parser
	 * @param string $pagename Page name
	 * @return int Amount of comments on the given page
	 */
	static function getParserHandler( $parser, $pagename ) {
		$page = Title::newFromText( $pagename );

		if ( $page instanceof Title ) {
			$id = $page->getArticleID();
		} else {
			$id = $parser->getTitle()->getArticleID();
		}

		return self::getNumberOfCommentsPage( $id );
	}

	/**
	 * Get the actual number of comments
	 *
	 * @param int $pageId ID of page to get number of comments for
	 * @return int
	 */
	static function getNumberOfCommentsPage( $pageId ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey( 'comments', 'numberofcommentspage', $pageId );
		$cachedValue = $cache->get( $key );

		if ( $cachedValue ) {
			$val = intval( $cachedValue );
		} else {
			$dbr = Comment::getDBHandle( 'read' );

			$res = $dbr->selectField(
				'Comments',
				'COUNT(*)',
				[ 'Comment_Page_ID' => $pageId ],
				__METHOD__
			);

			if ( !$res ) {
				$val = 0;
			} else {
				$val = intval( $res );
			}

			$cache->set( $key, $val, 60 * 60 ); // cache for an hour
		}

		return $val;
	}

}
