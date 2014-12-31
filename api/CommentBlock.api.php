<?php

class CommentBlockAPI extends ApiBase {

    public function execute() {
        // Do nothing when the database is in read-only mode
        if ( wfReadOnly() ) {
            return true;
        }

        // Load user_name and user_id for person we want to block from the comment it originated from
        $dbr = wfGetDB( DB_SLAVE );
        $s = $dbr->selectRow(
            'Comments',
            array( 'comment_username', 'comment_user_id' ),
            array( 'CommentID' => $this->getMain()->getVal( 'commentID' ) ),
            __METHOD__
        );
        if ( $s !== false ) {
            $userID = $s->comment_user_id;
            $username = $s->comment_username;
        }

        CommentFunctions::blockUser( $userID, $username );

        if ( class_exists( 'UserStatsTrack' ) ) {
            $stats = new UserStatsTrack( $userID, $username );
            $stats->incStatField( 'comment_ignored' );
        }

        $result = $this->getResult();
        $result->addValue( $this->getModuleName(), 'ok', 'ok' );
        return true;
    }

    public function getAllowedParams() {
        return array(
            'commentID' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            )
        );
    }
}