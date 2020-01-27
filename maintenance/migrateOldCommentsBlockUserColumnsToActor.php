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
class MigrateOldCommentsBlockUserColumnsToActor extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		// @codingStandardsIgnoreLine
		$this->addDescription( 'Migrates data from old _user_name/_user_id columns in the Comments_block table to the new actor columns.' );
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
		return 'Comments_block has already been migrated to use the actor columns.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_MASTER );
		if ( !$dbw->fieldExists( 'Comments_block', 'cb_user_id', __METHOD__ ) ) {
			// Old field's been dropped already so nothing to do here...
			// Why is this loop here? Because Postgres was being weird, that's why.
			return true;
		}
		$dbw->query(
			// @codingStandardsIgnoreLine
			"UPDATE {$dbw->tableName( 'Comments_block' )} SET cb_actor=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=cb_user_id AND actor_name=cb_user_name)",
			__METHOD__
		);
		$dbw->query(
			// @codingStandardsIgnoreLine
			"UPDATE {$dbw->tableName( 'Comments_block' )} SET cb_actor_blocked=(SELECT actor_id FROM {$dbw->tableName( 'actor' )} WHERE actor_user=cb_user_id_blocked AND actor_name=cb_user_name_blocked)",
			__METHOD__
		);
		return true;
	}
}

$maintClass = MigrateOldCommentsBlockUserColumnsToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
