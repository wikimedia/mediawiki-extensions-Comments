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
class MigrateOldCommentsBlockUserColumnsToActor extends MediaWiki\Maintenance\LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in the Comments_block table ' .
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
	public function updateSkippedMessage() {
		return 'Comments_block has already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->fieldExists( 'Comments_block', 'cb_user_id', __METHOD__ ) ) {
			// Old field's been dropped already so nothing to do here...
			// Why is this loop here? Because Postgres was being weird, that's why.
			return true;
		}

		// Copypasted code from AJAXPoll's migration script, written by Ostrzyciel
		// Find missing anonymous actors and insert them to the actor table
		// Do not attempt doing it with an insertSelect, it's apparently incompatible with postgres
		$res = $dbw->select(
			[
				'Comments_block',
				'actor'
			],
			[ 'cb_user_name_blocked' ],
			[
				// comment ignoring is something available only for registered users;
				// Special:CommentIgnoreList requires logging in, _but_ this means that
				// while anons may never _block_ comments, anons may have _their_ comments
				// blocked!
				// Hence why we are ignoring the cb_user_name/cb_user_id fields here: it
				// should *never* be possible for cb_user_id to be 0!
				'cb_user_id_blocked' => 0,
				'actor_id IS NULL'
			],
			__METHOD__,
			[ 'DISTINCT' ],
			[
				'actor' => [ 'LEFT JOIN', [ 'actor_name = cb_user_name_blocked' ] ]
			]
		);

		$toInsert = [];

		foreach ( $res as $row ) {
			$toInsert[] = [ 'actor_name' => $row->cb_user_name_blocked ];
		}

		if ( !empty( $toInsert ) ) {
			$dbw->insert( 'actor', $toInsert, __METHOD__ );
		}
		// End copypasta

		$res = $dbw->select(
			'Comments_block',
			[
				'cb_user_id',
				'cb_user_name'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();

		foreach ( $res as $row ) {
			$user = $userFactory->newFromAnyId( $row->cb_user_id, $row->cb_user_name, null );
			$actorId = $services->getActorNormalization()->acquireActorId( $user, $dbw );
			$dbw->update(
				'Comments_block',
				[
					'cb_actor' => $actorId
				],
				[
					'cb_user_id' => $row->cb_user_id,
					'cb_user_name' => $row->cb_user_name
				]
			);
		}

		$res = $dbw->select(
			'Comments_block',
			[
				'cb_user_id_blocked',
				'cb_user_name_blocked'
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);

		foreach ( $res as $row ) {
			$user = $userFactory->newFromAnyId( $row->cb_user_id_blocked, $row->cb_user_name_blocked, null );
			$actorId = $services->getActorNormalization()->acquireActorId( $user, $dbw );
			$dbw->update(
				'Comments_block',
				[
					'cb_actor_blocked' => $actorId
				],
				[
					'cb_user_id_blocked' => $row->cb_user_id_blocked,
					'cb_user_name_blocked' => $row->cb_user_name_blocked
				]
			);
		}

		return true;
	}
}

$maintClass = MigrateOldCommentsBlockUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
