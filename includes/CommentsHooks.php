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
	 * - NUMBEROFCOMMENTSPAGE
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'comments', [ 'DisplayComments', 'getParserHandler' ] );
		$parser->setFunctionHook( 'NUMBEROFCOMMENTSPAGE', 'NumberOfComments::getParserHandler', Parser::SFH_NO_HASH );
	}

	/**
	 * For the Echo extension: register our new presentation model with Echo so
	 * Echo knows how it should display our notifications in it.
	 *
	 * @param array $notifications Echo notifications
	 * @param array $notificationCategories Echo notification categories
	 * @param array $icons Icon details
	 */
	public static function onBeforeCreateEchoEvent( &$notifications, &$notificationCategories, &$icons ) {
		$notifications['mention-comment'] = [
			'presentation-model' => 'EchoMentionCommentPresentationModel'
		];
		$notificationCategories['mention-comment'] = [
			'priority' => 4,
			'tooltip' => 'echo-pref-tooltip-mention-comment',
		];

		$notifications['mention-comment'] = [
			'category' => 'mention',
			'group' => 'interactive',
			'section' => 'alert',
			'presentation-model' => 'EchoMentionCommentPresentationModel',
			'user-locators' => [
				[
					'EchoUserLocator::locateFromEventExtra',
					[
						'mentioned-users'
					]
				]
			],
			// @todo FIXME: I've no clue if this is still actually used/needed...
			'bundle' => [ 'web' => true, 'email' => true ],
		];
	}

	/**
	 * Adds the three new required database tables into the database when the
	 * user runs /maintenance/update.php (the core database updater script).
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$dbType = $updater->getDB()->getType();
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$comments = "comments.{$dbType}.sql";
			$comments_vote = "comments_vote.{$dbType}.sql";
			$comments_block = "comments_block.{$dbType}.sql";
		} else {
			$comments = 'comments.sql';
			$comments_vote = 'comments_vote.sql';
			$comments_block = 'comments_block.sql';
		}

		$updater->addExtensionTable( 'Comments', "{$dir}/{$comments}" );
		$updater->addExtensionTable( 'Comments_Vote', "{$dir}/{$comments_vote}" );
		$updater->addExtensionTable( 'Comments_block', "{$dir}/{$comments_block}" );
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['Comments'] = [ 'Comment_Username', 'Comment_user_id' ];
		$renameUserSQL->tables['Comments_Vote'] = [ 'Comment_Vote_Username', 'Comment_Vote_user_id' ];
		$renameUserSQL->tables['Comments_block'] = [ 'cb_user_name', 'cb_user_id' ];
		$renameUserSQL->tables['Comments_block'] = [ 'cb_user_name_blocked', 'cb_user_id_blocked' ];
	}
}
