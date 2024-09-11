<?php
/**
 * @file
 * @ingroup Maintenance
 */

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Run automatically with update.php
 *
 * @since January 2020
 */
class MigrateOldCommentsVoteUserColumnsToActor extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in the Comments_Vote table ' .
			'to the new actor columns.' );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'Comments_Vote has already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->fieldExists( 'Comments_Vote', 'Comment_Vote_user_id', __METHOD__ ) ) {
			// Old field's been dropped already so nothing to do here...
			// Why is this loop here? Because Postgres was being weird, that's why.
			return true;
		}

		// Copypasted code from AJAXPoll's migration script, written by Ostrzyciel
		// Find missing anonymous actors and insert them to the actor table
		// Do not attempt doing it with an insertSelect, it's apparently incompatible with postgres
		$res = $dbw->select(
			[
				'Comments_Vote',
				'actor'
			],
			[ 'Comment_Vote_Username' ],
			[
				'Comment_Vote_user_id' => 0,
				'actor_id IS NULL'
			],
			__METHOD__,
			[ 'DISTINCT' ],
			[
				'actor' => [ 'LEFT JOIN', [ 'actor_name = Comment_Vote_Username' ] ]
			]
		);

		$toInsert = [];

		foreach ( $res as $row ) {
			$toInsert[] = [ 'actor_name' => $row->Comment_Vote_Username ];
		}

		if ( !empty( $toInsert ) ) {
			$dbw->insert( 'actor', $toInsert, __METHOD__ );
		}
		// End copypasta

		$res = $dbw->select(
			'Comments_Vote',
			[
				'Comment_Vote_user_id',
				'Comment_Vote_Username'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		foreach ( $res as $row ) {
			$user = $userFactory->newFromAnyId( $row->Comment_Vote_user_id, $row->Comment_Vote_Username, null );
			if ( interface_exists( '\MediaWiki\User\ActorNormalization' ) ) {
				// MW 1.36+
				$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
			} else {
				$actorId = $user->getActorId( $dbw );
			}
			$dbw->update(
				'Comments_Vote',
				[
					'Comment_Vote_actor' => $actorId
				],
				[
					'Comment_Vote_user_id' => $row->Comment_Vote_user_id,
					'Comment_Vote_Username' => $row->Comment_Vote_Username
				]
			);
		}

		return true;
	}
}

$maintClass = MigrateOldCommentsVoteUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
