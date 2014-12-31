<?php

class CommentVoteAPI extends ApiBase {

    public function execute() {
        global $wgUser;

        // Blocked users cannot vote, obviously, and neither can those users
        // without the necessary privileges
        if (
            $wgUser->isBlocked() ||
            !$wgUser->isAllowed( 'comment' ) ||
            wfReadOnly()
        ) {
            return '';
        }

        $comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );
        $voteValue = $this->getMain()->getVal( 'voteValue' );

        if ( $comment && is_numeric( $voteValue ) ) {
            $comment->vote( $voteValue );

            $score = $comment->getScore();

            if ( class_exists( 'UserStatsTrack' ) ) {
                $stats = new UserStatsTrack( $wgUser->getID(), $wgUser->getName() );

                // Must update stats for user doing the voting
                if ( $voteValue == 1 ) {
                    $stats->incStatField( 'comment_give_plus' );
                }
                if ( $voteValue == -1 ) {
                    $stats->incStatField( 'comment_give_neg' );
                }

                // Also must update the stats for user receiving the vote
                $stats_comment_owner = new UserStatsTrack( $comment->userID, $comment->username );
                $stats_comment_owner->updateCommentScoreRec( $voteValue );

                $stats_comment_owner->updateTotalPoints();
                if ( $voteValue === 1 ) {
                    $stats_comment_owner->updateWeeklyPoints( $stats_comment_owner->point_values['comment_plus'] );
                    $stats_comment_owner->updateMonthlyPoints( $stats_comment_owner->point_values['comment_plus'] );
                }
            }

            $result = $this->getResult();
            $result->addValue( $this->getModuleName(), 'score', $score );
            return true;
        }
    }

    public function getAllowedParams() {
        return array(
            'commentID' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            ),
            'voteValue' => array(
                ApiBase::PARAM_REQUIRED => true,
                ApiBase::PARAM_TYPE => 'integer'
            ),
        );
    }
}