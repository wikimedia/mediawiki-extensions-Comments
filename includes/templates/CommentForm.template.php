<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

/**
 * HTML template for the commenting form
 * @ingroup Templates
 */
class CommentFormTemplate extends QuickTemplate {
	public function execute() {
		$user = $this->data['user'];

		if ( $this->data['commentPage']->allow ) {
			$pos = strpos(
				strtoupper( addslashes( $this->data['commentPage']->allow ) ),
				strtoupper( addslashes( $user->getName() ) )
			);
		}

		// SocialProfile isn't a hard dependency, but if it is installed, we can use
		// its avatar stuff
		if ( class_exists( 'wAvatar' ) ) {
			$avatar = new wAvatar( $user->getId(), 'ml' );
			$avatarURL = $avatar->getAvatarURL( array( 'class' => 'avatar shape-circle' ) );
		} else {
			$avatarURL = '';
		}
?>
<nav class="mw-ui tabs card">
	<?php
	// 'comment' user right is required to add new comments
	if ( !$user->isAllowed( 'comment' ) ) {
		echo wfMessage( 'comments-not-allowed' )->parse();
	} else {
		// Blocked users can't add new comments under any conditions...
		// and maybe there's a list of users who should be allowed to post
		// comments
		if ( $user->isBlocked() == false && ( $this->data['commentPage']->allow == '' || $pos !== false ) ) {
	?>
	<ul class="tabs-items">
		<li><a class="tab-item write tab-item-open" data-linked-element="item-1"><?php echo wfMessage( 'comments-tab-write' ) ?></a></li>
		<li><a class="tab-item preview" data-linked-element="item-2"><?php echo wfMessage( 'comments-tab-preview' ) ?></a></li>
	</ul>
	<ul class="tabs-content">
		<!-- TODO: Samantha: CTA message for anons (MediaWiki:Comments-anon-message) -->
		<div id="replyto" class="c-form-reply-to"></div><!-- TODO: Samantha: this element needs CSS love and magic -->
		<li class="tab-content write tab-content-open" id="item-1">
			<?php echo $avatarURL ?>
			<form action="" method="post" name="commentForm">
				<textarea name="commentText" id="comment" class="mw-ui textarea" placeholder="Add a comment" rows="8" maxlength="1500"></textarea>
				<span class="character-count">0 / 1500</span>
				<ul class="mw-ui button-group align-right">
					<li><a class="mw-ui button secondary destructive" type="button"><?php echo wfMessage( 'comments-cancel-reply' )->plain() ?></a></li>
					<li><a class="mw-ui button primary progressive" type="button"><?php echo wfMessage( 'comments-post' )->plain() ?></a></li>
				</ul>
				<?php
					echo Html::hidden( 'action', 'purge' );
					echo Html::hidden( 'pageId', $this->data['commentPage']->id );
					echo Html::hidden( 'commentid', '' );
					echo Html::hidden( 'lastCommentId', $this->data['commentPage']->getLatestCommentID() );
					echo Html::hidden( 'commentParentId', '' );
					echo Html::hidden( $this->data['commentPage']->pageQuery, $this->data['commentPage']->getCurrentPagerPage() );
					echo Html::hidden( 'token', $user->getEditToken() );
				?>
			</form>
		</li>
		<li class="tab-content preview" id="item-2"><!-- This is where the preview is supposed to be rendered --></li>
	</ul>
	<?php } // if user is not blocked etc. ?>
	<?php } // if user is allowed to comment at all ?>
</nav>
<?php
	} // execute()
} // class
