<?php
/**
 * Hooked functions used by the Comments extension.
 * All class methods are public and static.
 *
 * @file
 * @ingroup Extensions
 * @author Jack Phoenix <jack@countervandalism.net>
 * @author Alexia E. Smith
 * @copyright (c) 2013 Curse Inc.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Comments Documentation
 */

class CommentsHooks {
	/**
	 * Registers the <comments> tag with the Parser.
	 *
	 * @param $parser Parser
	 * @return Boolean
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'comments', array( 'CommentsHooks', 'displayComments' ) );
		return true;
	}

	/**
	 * Callback function for onParserFirstCallInit().
	 *
	 * @param $input
	 * @param $args Array
	 * @param $parser Parser
	 * @return String: HTML
	 */
	public static function displayComments( $input, $args, $parser ) {
		global $wgOut;

		wfProfileIn( __METHOD__ );

		$parser->disableCache();

		// Add required CSS & JS via ResourceLoader
		$wgOut->addModules( 'ext.comments' );

		// Parse arguments
		// The preg_match() lines here are to support the old-style way of
		// adding arguments:
		// <comments>
		// Allow=Foo,Bar
		// Voting=Plus
		// </comments>
		// whereas the normal, standard MediaWiki style, which this extension
		// also supports is: <comments allow="Foo,Bar" voting="Plus" />
		$allow = '';
		if( preg_match( '/^\s*Allow\s*=\s*(.*)/mi', $input, $matches ) ) {
			$allow = htmlspecialchars( $matches[1] );
		} elseif( !empty( $args['allow'] ) ) {
			$allow = $args['allow'];
		}

		$voting = '';
		if( preg_match( '/^\s*Voting\s*=\s*(.*)/mi', $input, $matches ) ) {
			$voting = htmlspecialchars( $matches[1] );
		} elseif(
			!empty( $args['voting'] ) &&
			in_array( strtoupper( $args['voting'] ), array( 'OFF', 'PLUS', 'MINUS' ) )
		)
		{
			$voting = $args['voting'];
		}

		$comment = new Comment( $wgOut->getTitle()->getArticleID() );
		$comment->setAllow( $allow );
		$comment->setVoting( $voting );

		// This was originally commented out, I don't know why.
		// Uncommented to prevent E_NOTICE.
		$output = $comment->displayOrderForm();

		$output .= '<div id="allcomments">' . $comment->display() . '</div>';

		// If the database is in read-only mode, display a message informing the
		// user about that, otherwise allow them to comment
		if( !wfReadOnly() ) {
			$output .= $comment->displayForm();
		} else {
			$output .= wfMessage( 'comments-db-locked' )->parse();
		}

		wfProfileOut( __METHOD__ );

		return $output;
	}

	/**
	 * Registers NUMBEROFCOMMENTS as a valid magic word identifier.
	 *
	 * @param $variableIds Array: array of valid magic word identifiers
	 * @return Boolean
	 */
	public static function registerNumberOfCommentsMagicWord( &$variableIds ) {
		$variableIds[] = 'NUMBEROFCOMMENTS';
		return true;
	}

	/**
	 * Main backend logic for the {{NUMBEROFCOMMENTS}} magic word.
	 * If the {{NUMBEROFCOMMENTS}} magic word is found, first checks memcached
	 * to see if we can get the value from cache, but if that fails for some
	 * reason, then a COUNT(*) SQL query is done to fetch the amount from the
	 * database.
	 *
	 * @param $parser Parser
	 * @param $cache
	 * @param $magicWordId String: magic word identifier
	 * @param $ret Integer: what to return to the user (in our case, the number of comments)
	 * @return Boolean
	 */
	public static function assignValueToNumberOfComments( &$parser, &$cache, &$magicWordId, &$ret ) {
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
		}

		return true;
	}

	/**
	 * Adds the three new required database tables into the database when the
	 * user runs /maintenance/update.php (the core database updater script).
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __FILE__ ) . '/';

		$dbType = $updater->getDB()->getType();
		// For non-MySQL/MariaDB/SQLite DBMSes, use the appropriately named file
		if ( !in_array( $dbType, array( 'mysql', 'sqlite' ) ) ) {
			$filename = "comments.{$dbType}.sql";
		} else {
			$filename = 'comments.sql';
		}

		$updater->addExtensionUpdate( array( 'addTable', 'Comments', "{$dir}/{$filename}", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'Comments_Vote', "{$dir}/{$filename}", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'Comments_block', "{$dir}/{$filename}", true ) );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param $renameUserSQL Array
	 * @return Boolean
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['Comments'] = array( 'Comment_Username', 'Comment_user_id' );
		$renameUserSQL->tables['Comments_Vote'] = array( 'Comment_Vote_Username', 'Comment_Vote_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name', 'cb_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name_blocked', 'cb_user_id_blocked' );
		return true;
	}
}