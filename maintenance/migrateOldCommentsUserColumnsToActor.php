<?php
/**
 * @file
 * @ingroup Maintenance
 */
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
class MigrateOldCommentsUserColumnsToActor extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		// @codingStandardsIgnoreLine
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in the Comments table to the new actor column.' );
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
		return 'Comments has already been migrated to use the actor column.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_MASTER );
		if ( !$dbw->fieldExists( 'Comments', 'Comment_user_id', __METHOD__ ) ) {
			// Old field's been dropped already so nothing to do here...
			// Why is this loop here? Because Postgres was being weird, that's why.
			return true;
		}

		// Copypasted code from AJAXPoll's migration script, written by Ostrzyciel
		// Find missing anonymous actors and insert them to the actor table
		// Do not attempt doing it with an insertSelect, it's apparently incompatible with postgres
		$res = $dbw->select(
			[
				'Comments',
				'actor'
			],
			[ 'Comment_Username' ],
			[
				'Comment_user_id' => 0,
				'actor_id IS NULL'
			],
			__METHOD__,
			[ 'DISTINCT' ],
			[
				'actor' => [ 'LEFT JOIN', [ 'actor_name = Comment_Username' ] ]
			]
		);

		$toInsert = [];

		foreach ( $res as $row ) {
			$toInsert[] = [ 'actor_name' => $row->Comment_Username ];
		}

		if ( !empty( $toInsert ) ) {
			$dbw->insert( 'actor', $toInsert, __METHOD__ );
		}
		// End copypasta

		// Find corresponding actors for comments
		$res = $dbw->select(
			'Comments',
			[
				'Comment_Username'
			]
		);
		foreach ( $res as $row ) {
			$user = new User();
			$user->setName( $row->Comment_Username );
			$dbw->update(
				'Comments',
				[
					'Comment_actor' => $user->getActorId( $dbw )
				],
				[
					'Comment_Username' => $row->Comment_Username
				]
			);
		}

		return true;
	}
}

$maintClass = MigrateOldCommentsUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
