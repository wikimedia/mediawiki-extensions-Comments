<?php

class CommentSubmitAPI extends ApiBase {

    public function execute() {
        $user = $this->getUser();
        // Blocked users cannot submit new comments, and neither can those users
        // without the necessary privileges. Also prevent obvious cross-site request
        // forgeries (CSRF)
        if (
            $user->isBlocked() ||
            !$user->isAllowed( 'comment' ) ||
            wfReadOnly()
        ) {
            return true;
        }

        $commentText = $this->getMain()->getVal( 'commentText' );

        if ( $commentText != '' ) {
            // To protect against spam, it's necessary to check the supplied text
            // against spam filters (but comment admins are allowed to bypass the
            // spam filters)
            if ( !$user->isAllowed( 'commentadmin' ) && CommentFunctions::isSpam( $commentText ) ) {
                return wfMessage( 'comments-is-spam' )->plain();
            }

            // If the comment contains links but the user isn't allowed to post
            // links, reject the submission
            if ( !$user->isAllowed( 'commentlinks' ) && CommentFunctions::haveLinks( $commentText ) ) {
                return wfMessage( 'comments-links-are-forbidden' )->plain();
            }

            $page = new CommentsPage( $this->getMain()->getVal( 'pageID' ), $this->getContext() );

            Comment::add( $commentText, $page, $user, $this->getMain()->getVal( 'parentID' ) );

            if ( class_exists( 'UserStatsTrack' ) ) {
                $stats = new UserStatsTrack( $user->getID(), $user->getName() );
                $stats->incStatField( 'comment' );
            }
        }

        $result = $this->getResult();
        $result->addValue( $this->getModuleName(), 'ok', 'ok' );
        return true;
    }

    public function getAllowedParams() {
        return array(
            'pageID' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            ),
            'parentID' => array(
                ApiBase::PARAM_REQUIRED => false,
                ApiBase::PARAM_TYPE => 'integer'
            ),
            'commentText' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'string'
            )
        );
    }
}