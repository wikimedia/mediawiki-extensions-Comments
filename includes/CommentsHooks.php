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

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\Parser;

class CommentsHooks {
	/**
	 * Registers the following tags and magic words:
	 * - <comments />
	 * - NUMBEROFCOMMENTSPAGE
	 *
	 * @param MediaWiki\Parser\Parser &$parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'comments', [ 'DisplayComments', 'getParserHandler' ] );
		$parser->setFunctionHook( 'NUMBEROFCOMMENTSPAGE', 'NumberOfComments::getParserHandler', Parser::SFH_NO_HASH );
	}

	/**
	 * For the Echo extension: register our new presentation model with Echo so
	 * Echo knows how it should display our notifications in it.
	 *
	 * @param array &$notifications Echo notifications
	 * @param array &$notificationCategories Echo notification categories
	 * @param array &$icons Icon details
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
					'MediaWiki\Extension\Notifications\UserLocator::locateFromEventExtra',
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
	 * Purges comments caches on article purge action
	 * @param WikiPage &$page
	 */
	public static function onArticlePurge( &$page ) {
		$cp = new CommentsPage( $page->getId(), RequestContext::getMain() );
		$cp->clearCommentListCache();
	}

	/**
	 * Extension registration callback which does some AbuseFilter-related magic.
	 *
	 * @note "Borrowed" from ArticleFeedbackv5.
	 * @see https://phabricator.wikimedia.org/T301083
	 */
	public static function registerExtension() {
		global $wgAbuseFilterValidGroups, $wgAbuseFilterEmergencyDisableThreshold, $wgAbuseFilterEmergencyDisableCount, $wgAbuseFilterEmergencyDisableAge;
		global $wgAbuseFilterActions;
		global $wgCommentsAbuseFilterGroup;

		// Note: it's too early to use ExtensionRegistry->isLoaded()
		if ( $wgAbuseFilterActions !== null ) {
			if ( $wgCommentsAbuseFilterGroup != 'default' ) {
				// Add a custom filter group for AbuseFilter
				$wgAbuseFilterValidGroups[] = $wgCommentsAbuseFilterGroup;
				// set abusefilter emergency disable values for comments
				$wgAbuseFilterEmergencyDisableThreshold[$wgCommentsAbuseFilterGroup] = 0.10;
				$wgAbuseFilterEmergencyDisableCount[$wgCommentsAbuseFilterGroup] = 50;
				$wgAbuseFilterEmergencyDisableAge[$wgCommentsAbuseFilterGroup] = 86400; // One day.
			}
			$wgAbuseFilterActions += [
				'comment' => true
			];
		}
	}

	/**
	 * Adds the three new required database tables into the database when the
	 * user runs /maintenance/update.php (the core database updater script).
	 *
	 * @param MediaWiki\Installer\DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		$db = $updater->getDB();
		$dbType = $db->getType();

		// For non-MySQL/MariaDB/SQLite DBMSes, use the appropriately named files
		$patchFileSuffix = '';
		if ( !in_array( $dbType, [ 'mysql', 'sqlite' ] ) ) {
			$comments = "comments.{$dbType}.sql";
			$comments_vote = "comments_vote.{$dbType}.sql";
			$comments_block = "comments_block.{$dbType}.sql";
			$patchFileSuffix = '.' . $dbType; // e.g. ".postgres"
		} else {
			$comments = 'comments.sql';
			$comments_vote = 'comments_vote.sql';
			$comments_block = 'comments_block.sql';
		}

		$updater->addExtensionTable( 'Comments', "{$dir}/{$comments}" );
		$updater->addExtensionTable( 'Comments_Vote', "{$dir}/{$comments_vote}" );
		$updater->addExtensionTable( 'Comments_block', "{$dir}/{$comments_block}" );

		// Check lowercase normalized version for postgres
		$caseSensitiveDb = $dbType !== 'postgres';
		// Actor support
		if ( !$db->fieldExists( 'Comments', $caseSensitiveDb ? 'Comment_actor' : 'comment_actor', __METHOD__ ) ) {
			// 1) add new actor columns
			$updater->addExtensionField( 'Comments', $caseSensitiveDb ? 'Comment_actor' : 'comment_actor', "$dir/patches/actor/add-Comment_actor{$patchFileSuffix}.sql" );
			// 2) add the corresponding indexes
			$updater->addExtensionIndex( 'Comments', 'wiki_actor', "$dir/patches/actor/add-wiki_actor_index.sql" );
			// 3) populate the new column with data
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldCommentsUserColumnsToActor',
				"$dir/../maintenance/migrateOldCommentsUserColumnsToActor.php"
			] );
			// 4) drop old columns & indexes
			$updater->dropExtensionField( 'Comments', 'Comment_user_id', "$dir/patches/actor/drop-Comment_user_id.sql" );
			$updater->dropExtensionField( 'Comments', 'Comment_Username', "$dir/patches/actor/drop-Comment_Username.sql" );
			$updater->dropExtensionIndex( 'Comments', 'wiki_user_id', "$dir/patches/actor/drop-wiki_user_id-index.sql" );
			$updater->dropExtensionIndex( 'Comments', 'wiki_user_name', "$dir/patches/actor/drop-wiki_user_name-index.sql" );
		}

		if ( !$db->fieldExists( 'Comments_block', 'cb_actor', __METHOD__ ) ) {
			// 1) add new actor columns
			$updater->addExtensionField( 'Comments_block', 'cb_actor', "$dir/patches/actor/add-cb_actor{$patchFileSuffix}.sql" );
			$updater->addExtensionField( 'Comments_block', 'cb_actor_blocked', "$dir/patches/actor/add-cb_actor_blocked{$patchFileSuffix}.sql" );
			// 2) add the corresponding indexes
			$updater->addExtensionIndex( 'Comments_block', 'cb_actor', "$dir/patches/actor/add-cb_actor-index.sql" );
			// 3) populate the new column with data
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldCommentsBlockUserColumnsToActor',
				"$dir/../maintenance/migrateOldCommentsBlockUserColumnsToActor.php"
			] );
			// 4) drop old columns & indexes
			$updater->dropExtensionField( 'Comments_block', 'cb_user_id', "$dir/patches/actor/drop-cb_user_id.sql" );
			$updater->dropExtensionField( 'Comments_block', 'cb_user_name', "$dir/patches/actor/drop-cb_user_name.sql" );
			$updater->dropExtensionField( 'Comments_block', 'cb_user_id_blocked', "$dir/patches/actor/drop-cb_user_id_blocked.sql" );
			$updater->dropExtensionField( 'Comments_block', 'cb_user_name_blocked', "$dir/patches/actor/drop-cb_user_name_blocked.sql" );
			$updater->dropExtensionIndex( 'Comments_block', 'cb_user_id', "$dir/patches/actor/drop-cb_user_id-index.sql" );
		}

		if ( !$db->fieldExists( 'Comments_Vote', $caseSensitiveDb ? 'Comment_Vote_actor' : 'comment_vote_actor', __METHOD__ ) ) {
			// 1) add new actor columns
			$updater->addExtensionField( 'Comments_Vote', $caseSensitiveDb ? 'Comment_Vote_actor' : 'comment_vote_actor', "$dir/patches/actor/add-Comment_Vote_actor{$patchFileSuffix}.sql" );
			// 2) add the corresponding indexes
			$updater->addExtensionIndex( 'Comments_Vote', $caseSensitiveDb ? 'Comments_Vote_actor_index' : 'comments_vote_actor_index', "$dir/patches/actor/add-Comment_Vote_unique_actor_index.sql" );
			$updater->addExtensionIndex( 'Comments_Vote', $caseSensitiveDb ? 'Comment_Vote_actor' : 'comment_vote_actor', "$dir/patches/actor/add-Comment_Vote_actor-index.sql" );
			// 3) populate the new column with data
			$updater->addExtensionUpdate( [
				'runMaintenance',
				'MigrateOldCommentsVoteUserColumnsToActor',
				"$dir/../maintenance/migrateOldCommentsVoteUserColumnsToActor.php"
			] );
			// 4) drop old columns & indexes
			$updater->dropExtensionField( 'Comments_Vote', 'Comment_Vote_user_id', "$dir/patches/actor/drop-Comment_Vote_user_id.sql" );
			$updater->dropExtensionIndex( 'Comments_Vote', 'Comments_Vote_user_id_index', "$dir/patches/actor/drop-Comments_Vote_user_id_index.sql" );
			$updater->dropExtensionField( 'Comments_Vote', 'Comment_Vote_Username', "$dir/patches/actor/drop-Comment_Vote_Username.sql" );
			$updater->dropExtensionIndex( 'Comments_Vote', 'Comment_Vote_user_id', "$dir/patches/actor/drop-Comment_Vote_user_id-index.sql" );
		}
	}
}
