<?php

class CommentVoteAPI extends ApiBase {

	public function execute() {
		$user = $this->getUser();
		// Blocked users cannot vote, obviously, and neither can those users without the necessary privileges
		if (
			$user->isBlocked() ||
			!$user->isAllowed( 'comment' )
		) {
			return '';
		}

		$comment = Comment::newFromID( $this->getMain()->getVal( 'commentID' ) );
		$voteValue = $this->getMain()->getVal( 'voteValue' );

		if ( $comment && is_numeric( $voteValue ) ) {
			$comment->vote( $voteValue );

			$html = $comment->getScoreHTML();
			$html = htmlspecialchars( $html );

			if ( class_exists( 'UserStatsTrack' ) ) {
				$stats = new UserStatsTrack( $user->getId(), $user->getName() );

				// Must update stats for user doing the voting
				if ( $voteValue == 1 ) {
					$stats->incStatField( 'comment_give_plus' );
				}
				if ( $voteValue == -1 ) {
					$stats->incStatField( 'comment_give_neg' );
				}

				// Also must update the stats for user receiving the vote
				$stats_comment_owner = new UserStatsTrack( $comment->user->getId(), $comment->user->getName() );
				$stats_comment_owner->updateCommentScoreRec( $voteValue );

				$stats_comment_owner->updateTotalPoints();
				if ( $voteValue === 1 ) {
					$stats_comment_owner->updateWeeklyPoints( $stats_comment_owner->point_values['comment_plus'] );
					$stats_comment_owner->updateMonthlyPoints( $stats_comment_owner->point_values['comment_plus'] );
				}
			}

			$result = $this->getResult();
			$result->addValue( $this->getModuleName(), 'html', $html );
			return true;
		}
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'commentID' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'voteValue' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'integer'
			],
		];
	}
}
