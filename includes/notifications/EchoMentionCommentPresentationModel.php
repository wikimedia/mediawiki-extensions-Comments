<?php
/**
 * Echo notifications presentation stuff for @username mentions in comments.
 *
 * @file
 * @date 8 August 2019
 */
class EchoMentionCommentPresentationModel extends EchoMentionPresentationModel {

	public function getIconType() {
		return 'mention';
	}

	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	protected function getHeaderMessageKey() {
		if ( $this->event->getExtraParam( 'comment-id' ) ) {
			return 'notification-header-mention-comment';
		}
		return parent::getHeaderMessageKey();
	}

	public function getHeaderMessage() {
		$t = $this->event->getTitle();

		if ( $this->event->getExtraParam( 'comment-id' ) ) {
			// $1 & $2: user who mentioned you (duplicated for GENDER support)
			$msg = $this->getMessageWithAgent( $this->getHeaderMessageKey() );

			// $3 = your username
			$msg->params( $this->getViewingUserForGender() );

			// $4 = page where the <comments/> tag is (including the namespace prefix), e.g.
			// "Talk:Foo" or "Main Page"
			$msg->params( $t->getPrefixedText() );

			// $5 = comment ID, for building the correct fragment (basically always $4#comment-$5)
			$msg->params( $this->event->getExtraParam( 'comment-id' ) );

			// $6 = (snippet of the) comment (text) where you were mentioned
			// @todo In the parent implementation this is wrapped in if ( $this->hasSection() ) ...
			// Should we also do that?
			// Not relevant now that this is commented out since the snippet seems to get
			// shown regardless, at least in the flyout menu.
			// $msg->plaintextParams( $this->getTruncatedSectionTitle() );

			return $msg;
		}

		return parent::getHeaderMessage();
	}

	public function getBodyMessage() {
		$content = $this->event->getExtraParam( 'content' );
		if ( $content && $this->userCan( Revision::DELETED_TEXT ) ) {
			$msg = $this->msg( 'notification-body-mention' );
			$msg->plaintextParams(
				EchoDiscussionParser::getTextSnippet(
					$content,
					$this->language,
					150,
					$this->event->getTitle()
				)
			);
			return $msg;
		} else {
			return false;
		}
	}

	public function getPrimaryLink() {
		return [
			// Need FullURL so the section is included
			'url' => $this->getTitleWithSection()->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-mention' )->text()
		];
	}

	public function getSecondaryLinks() {
		$title = $this->event->getTitle();

		if ( $this->event->getExtraParam( 'comment-id' ) ) {
			$url = $title->getLocalURL() . '#comment-' . $this->event->getExtraParam( 'comment-id' );
			$viewChangesLink = [
				'url' => $url,
				'label' => $this->msg( 'notification-link-text-view-mention', $this->getViewingUserForGender() )->text(),
				'description' => '',
				'icon' => 'changes',
				'prioritized' => true,
			];

			return [ $this->getAgentLink(), $viewChangesLink ];
		}

		return parent::getSecondaryLinks();
	}

	// @todo Do we need to override this?
	protected function getSubjectMessageKey() {
		return 'notification-mention-email-subject';
	}

}
