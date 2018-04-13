<?php
/**
 * Hooked functions used by the Comments extension.
 * All class methods are public and static.
 *
 * @file
 * @ingroup Extensions
 * @author Jack Phoenix
 * @author Alexia E. Smith
 * @copyright (c) 2013 Curse Inc.
 * @license GPL-2.0-or-later
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
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$filename = "comments.{$dbType}.sql";
		} else {
			$filename = 'comments.sql';
		}

		$updater->addExtensionTable( 'Comments', "{$dir}/{$filename}" );
		$updater->addExtensionTable( 'Comments_Vote', "{$dir}/{$filename}" );
		$updater->addExtensionTable( 'Comments_block', "{$dir}/{$filename}" );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['Comments'] = [ 'Comment_Username', 'Comment_user_id' ];
		$renameUserSQL->tables['Comments_Vote'] = [ 'Comment_Vote_Username', 'Comment_Vote_user_id' ];
		$renameUserSQL->tables['Comments_block'] = [ 'cb_user_name', 'cb_user_id' ];
		$renameUserSQL->tables['Comments_block'] = [ 'cb_user_name_blocked', 'cb_user_id_blocked' ];
		return true;
	}
}
