<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\AtEase\AtEase;

class CommentFunctions {
	/**
	 * The following four functions are borrowed
	 * from includes/wikia/GlobalFunctionsNY.php
	 * @param int $date1
	 * @param int $date2
	 * @return array
	 */
	public static function dateDiff( $date1, $date2 ) {
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

	public static function getTimeOffset( $time, $timeabrv, $timename ) {
		$timeStr = ''; // misza: initialize variables, DUMB FUCKS!
		if ( $time[$timeabrv] > 0 ) {
			// Give grep a chance to find the usages:
			// comments-time-days, comments-time-hours, comments-time-minutes, comments-time-seconds, comments-time-months
			$timeStr = wfMessage( "comments-time-{$timename}", $time[$timeabrv] )->parse();
		}
		if ( $timeStr ) {
			$timeStr .= ' ';
		}
		return $timeStr;
	}

	public static function getTimeAgo( $time ) {
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
			if ( $timeStr < 2 ) {
				$timeStr .= $timeStrH;
				$timeStr .= $timeStrM;
				if ( !$timeStr ) {
					$timeStr .= $timeStrS;
				}
			}
		}
		if ( !$timeStr ) {
			$timeStr = wfMessage( 'comments-time-seconds', 1 )->parse();
		}
		return $timeStr;
	}

	/**
	 * Makes sure that link text is not too long by changing too long links to
	 * <a href=#>http://www.abc....xyz.html</a>
	 *
	 * @param array $matches
	 * @return string shortened URL
	 */
	public static function cutCommentLinkText( $matches ) {
		$tagOpen = $matches[1];
		$linkText = $matches[2];
		$tagClose = $matches[3];

		$image = preg_match( "/<img src=/i", $linkText );
		$isURL = ( preg_match( '%^(?:http|https|ftp)://(?:www\.)?.*$%i', $linkText ) ? true : false );

		if ( $isURL && !$image && strlen( $linkText ) > 30 ) {
			$start = substr( $linkText, 0, ( 30 / 2 ) - 3 );
			$end = substr( $linkText, strlen( $linkText ) - ( 30 / 2 ) + 3, ( 30 / 2 ) - 3 );
			$linkText = trim( $start ) . wfMessage( 'ellipsis' )->escaped() . trim( $end );
		}
		return $tagOpen . $linkText . $tagClose;
	}

	/**
	 * Simple spam check -- checks the supplied text against MediaWiki's
	 * built-in regex-based spam filters
	 *
	 * @param string $text text to check for spam patterns
	 * @return bool true if it contains spam, otherwise false
	 */
	public static function isSpam( $text ) {
		global $wgSpamRegex, $wgSummarySpamRegex;

		$retVal = false;
		// Allow to hook other anti-spam extensions so that sites that use,
		// for example, AbuseFilter, Phalanx or SpamBlacklist can add additional
		// checks
		MediaWikiServices::getInstance()->getHookContainer()->run( 'Comments::isSpam', [ &$text, &$retVal ] );
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
	 * @param string $text text to check
	 * @return bool true if it contains links, otherwise false
	 */
	public static function haveLinks( $text ) {
		$linkPatterns = [
			'/(https?)|(ftp):\/\//',
			'/=\\s*[\'"]?\\s*mailto:/',
		];
		foreach ( $linkPatterns as $linkPattern ) {
			if ( preg_match( $linkPattern, $text ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does the supplied text contain abuse, as determined by AbuseFilter?
	 *
	 * @note This borrows a _lot_ of GPL-licensed code from the ArticleFeedbackv5
	 * extension's AbuseFilter interoperability code.
	 *
	 * @see https://phabricator.wikimedia.org/T301083
	 *
	 * @param int $pageID Page ID
	 * @param User $user User who is trying to submit a comment
	 * @param string $text User-supplied text to check for abusiveness
	 * @return Status Status object; good when AF is not installed or initiating a Title fails, otherwise
	 *  whatever AF says the Status is
	 */
	public static function isAbusive( $pageID, $user, $text ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) ) {
			// AF not installed? Aww dang...nothing for us to do here, then.
			// Assume the best in such case...
			return Status::newGood();
		}

		global $wgCommentsAbuseFilterGroup;

		// Set up variables
		$title = Title::newFromID( $pageID );
		if ( !$title ) {
			return Status::newGood();
		}

		if ( class_exists( MediaWiki\Extension\AbuseFilter\AbuseFilterServices::class ) ) {
			// post-1.35
			$gen = MediaWiki\Extension\AbuseFilter\AbuseFilterServices::getVariableGeneratorFactory()->newGenerator();
			$runnerFactory = MediaWiki\Extension\AbuseFilter\AbuseFilterServices::getFilterRunnerFactory();
		} else {
			// 1.35 only
			$gen = new MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGenerator(
				new AbuseFilterVariableHolder()
			);
		}

		$vars = $gen->addUserVars( $user )
			->addTitleVars( $title, 'page' )
			->addGenericVars()
			->getVariableHolder();
		$vars->setVar( 'summary', 'Comment via the Comments extension' );
		$vars->setVar( 'action', 'comment' );
		$vars->setVar( 'new_wikitext', $text );
		$vars->setLazyLoadVar( 'new_size', 'length', [ 'length-var' => 'new_wikitext' ] );

		if ( class_exists( MediaWiki\Extension\AbuseFilter\FilterRunnerFactory::class ) ) {
			$status = $runnerFactory->newRunner(
				$user,
				$title,
				$vars,
				$wgCommentsAbuseFilterGroup
			)->run();
		} else {
			// 1.35 only
			$status = ( new AbuseFilterRunner( $user, $title, $vars, $wgCommentsAbuseFilterGroup ) )->run();
		}

		return $status;
	}

	/**
	 * Blocks comments from a user
	 *
	 * @param User $blocker The user who is blocking someone else's comments
	 * @param User $blocked User whose comments we want to block
	 */
	public static function blockUser( $blocker, $blocked ) {
		$dbw = Comment::getDBHandle( 'write' );

		AtEase::suppressWarnings(); // E_STRICT bitching
		$date = date( 'Y-m-d H:i:s' );
		AtEase::restoreWarnings();
		$dbw->insert(
			'Comments_block',
			[
				'cb_actor' => $blocker->getActorId(),
				'cb_actor_blocked' => $blocked->getActorId(),
				'cb_date' => $dbw->timestamp( $date )
			],
			__METHOD__
		);
	}

	/**
	 * Fetches the list of blocked users from the database
	 *
	 * @param User $user User whose block list we're loading
	 * @return array List of comment-blocked users' user names
	 */
	public static function getBlockList( $user ) {
		$blockList = [];
		$dbr = Comment::getDBHandle( 'read' );
		$res = $dbr->select(
			'Comments_block',
			'cb_actor_blocked',
			[ 'cb_actor' => $user->getActorId() ],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$blocked = User::newFromActorId( $row->cb_actor_blocked );
			if ( $blocked ) {
				$blockList[] = $blocked->getName();
			}
		}
		return $blockList;
	}

	/**
	 * @todo Apparently unused as of 3 January 2020
	 * @param User $user Has this user...
	 * @param User $blocked ...blocked comments from this user?
	 * @return bool True if they have
	 */
	public static function isUserCommentBlocked( $user, $blocked ) {
		$dbr = Comment::getDBHandle( 'read' );
		$s = $dbr->selectRow(
			'Comments_block',
			[ 'cb_id' ],
			[
				'cb_actor' => $user->getActorId(),
				'cb_actor_blocked' => $blocked->getActorId()
			],
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
	 * @param User $user User object who is deleting a block list entry
	 * @param User $blockedUser User object representing the blocked user (whose entry is being deleted)
	 */
	public static function deleteBlock( $user, $blockedUser ) {
		$dbw = Comment::getDBHandle( 'write' );
		$dbw->delete(
			'Comments_block',
			[
				'cb_actor' => $user->getActorId(),
				'cb_actor_blocked' => $blockedUser->getActorId()
			],
			__METHOD__
		);
	}

	/**
	 * Sort comments descending
	 *
	 * @param Comment $x
	 * @param Comment $y
	 * @return int
	 */
	public static function sortDescComments( $x, $y ) {
		if ( $x->timestamp < $y->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Sort threads ascending
	 *
	 * @param array $x
	 * @param array $y
	 * @return int
	 */
	public static function sortAsc( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)

		if ( $x[0]->timestamp < $y[0]->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Sort threads descending
	 *
	 * @param array $x
	 * @param array $y
	 * @return int
	 */
	public static function sortDesc( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)

		if ( $x[0]->timestamp > $y[0]->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Sort threads by score
	 *
	 * @param array $x
	 * @param array $y
	 * @return int
	 */
	public static function sortScore( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)

		if ( $x[0]->currentScore > $y[0]->currentScore ) {
			return -1;
		} else {
			return 1;
		}
	}

	/**
	 * Sort the comments purely by the time, from earliest to latest
	 *
	 * @param array $x
	 * @param array $y
	 * @return int
	 */
	public static function sortTime( $x, $y ) {
		// return -1  -  x goes above y
		// return  1  -  x goes below y
		// return  0  -  order irrelevant (only when x == y)
		if ( $x->timestamp == $y->timestamp ) {
			return 0;
		} elseif ( $x->timestamp < $y->timestamp ) {
			return -1;
		} else {
			return 1;
		}
	}
}
