<?php

class CommentDeleteAPI extends ApiBase {

    public function execute() {
        $user = $this->getUser();
        // Blocked users cannot delete comments, and neither can unprivileged ones.
        // Also check for database read-only status
        if (
            $user->isBlocked() ||
            !$user->isAllowed( 'commentadmin' ) ||
            wfReadOnly()
        ) {
            return true;
        }

        $comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );
        $comment->delete();

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