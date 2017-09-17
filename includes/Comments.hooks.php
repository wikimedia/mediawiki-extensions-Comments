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
	 * Registers the following tags and magic words:
	 * - <comments />
	 * - <commentsoftheday />
	 * - NUMBEROFCOMMENTSPAGE
	 *
	 * @param Parser $parser
	 * @return bool true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'comments', [ 'DisplayComments', 'getParserHandler' ] );
		$parser->setHook( 'commentsoftheday', [ 'CommentsOfTheDay', 'getParserHandler' ] );
		$parser->setFunctionHook( 'NUMBEROFCOMMENTSPAGE', 'NumberOfComments::getParserHandler', Parser::SFH_NO_HASH );

		return true;
	}

	/**
	 * Adds the three new required database tables into the database when the
	 * user runs /maintenance/update.php (the core database updater script).
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

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
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['Comments'] = array( 'Comment_Username', 'Comment_user_id' );
		$renameUserSQL->tables['Comments_Vote'] = array( 'Comment_Vote_Username', 'Comment_Vote_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name', 'cb_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name_blocked', 'cb_user_id_blocked' );
		return true;
	}
}
