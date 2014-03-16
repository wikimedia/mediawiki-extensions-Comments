/**
 * JavaScript for the Comments extension.
 * Rewritten by Jack Phoenix <jack@countervandalism.net> to be more
 * object-oriented.
 *
 * @file
 * @date 6 December 2013
 */
var Comment = {
	submitted: 0,
	isBusy: false,
	timer: '', // has to have an initial value...
	updateDelay: 7000,
	LatestCommentID: '',
	CurLatestCommentID: '',
	pause: 0,

	/**
	 * When a comment's author is ignored, "Show Comment" link will be
	 * presented to the user.
	 * If the user clicks on it, this function is called to show the hidden
	 * comment.
	 */
	show: function( id ) {
		jQuery( '#ignore-' + id ).hide( 100 );
		jQuery( '#comment-' + id ).show( 500 );
	},

	/**
	 * This function is called whenever a user clicks on the "block" image to
	 * block another user's comments.
	 *
	 * @param user_name String: name of the user whose comments we want to block
	 * @param user_id Integer: user ID number of the user whose comments we
	 *                         want to block (or 0 for anonymous users)
	 * @param c_id Integer: comment ID number
	 */
	blockUser: function( user_name, user_id, c_id ) {
		var message;
		var token = document.commentform.token.value;

		// Display a different message depending on whether we're blocking an
		// anonymous user or a registered one.
		// We could check if user_name is an IP with mw.util.isIPv4Address and
		// mw.util.isIPv6Address, but that'd be overkill since we know that all
		// anons have id = 0 in MediaWiki and we expose the user ID via HTML to
		// this function, so...
		if ( !user_id || user_id === 0 ) {
			message = mw.msg( 'comments-block-warning-anon' );
		} else {
			message = mw.msg( 'comments-block-warning-user', user_name );
		}

		if ( confirm( message ) ) {
			sajax_request_type = 'POST';
			sajax_do_call( 'wfCommentBlock', [ c_id, user_id, token ], function( response ) {
				jQuery( '#comment-' + c_id ).hide( 2000 );
				//alert( response.responseText );
				//window.location.href = window.location;
			});
		}
	},

	/**
	 * This function is called whenever a user clicks on the "Delete Comment"
	 * link to delete a comment.
	 *
	 * @param c_id Integer: comment ID number
	 */
	deleteComment: function( c_id ) {
		var pageId = document.commentform.pid.value,
			token = document.commentform.token.value;
		if ( confirm( mw.msg( 'comments-delete-warning' ) ) ) {
			sajax_request_type = 'POST';
			sajax_do_call( 'wfDeleteComment', [ pageId, c_id, token ], function( response ) {
				// The commented-out line is original code. I have no clue what
				// it was supposed to do, but the jQuery gimmick below looks
				// better, IMHO.
				//window.location.href = window.location;
				jQuery( '#comment-' + c_id ).hide( 2000 );
			});
		}
	},

	/**
	 * Vote for a comment.
	 * Formerly called "cv"
	 *
	 * @param cid Integer: comment ID number
	 * @param vt Integer: vote value
	 * @param vg
	 */
	vote: function( cid, vt, vg ) {
		var pageID = document.commentform.pid.value,
			token = document.commentform.token.value;
		sajax_request_type = 'POST';
		sajax_do_call(
			'wfCommentVote',
			[ cid, vt, ( ( vg ) ? vg : 0 ), pageID, token ],
			function( response ) {
				document.getElementById( 'Comment' + cid ).innerHTML = response.responseText;
				var img = '<img src="' + mw.config.get( 'wgExtensionAssetsPath' ) + '/Comments/images/voted.svg" alt="" />';
				document.getElementById( 'CommentBtn' + cid ).innerHTML =
					img + '<span class="CommentVoted">' +
					mw.msg( 'comments-voted-label' ) + '</span>';
			}
		);
	},

	/**
	 * This is ugly but we have to use this because AJAX function wfCommentList
	 * doesn't work...thanks, Parser.php
	 *
	 * @param pid Integer: page ID
	 * @param ord Sorting order
	 * @param end
	 * @param cpage Integer: comment page number (used for pagination)
	 */
	viewComments: function( pid, ord, end, cpage ) {
		document.commentform.cpage.value = cpage;
		document.getElementById( 'allcomments' ).innerHTML = mw.msg( 'comments-loading' ) + '<br /><br />';
		var x = sajax_init_object();
		var url = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) +
			'/index.php?title=Special:CommentListGet&pid=' + pid + '&ord=' +
			ord + '&cpage=' + cpage;

		x.open( 'get', url, true );

		x.onreadystatechange = function() {
			if ( x.readyState != 4 ) {
				return;
			}

			document.getElementById( 'allcomments' ).innerHTML = x.responseText;
			Comment.submitted = 0;
			if ( end ) {
				window.location.hash = 'end';
			}
		};

		x.send( null );
	},

	/**
	 * Submit a new comment.
	 */
	submit: function() {
		if ( Comment.submitted === 0 ) {
			Comment.submitted = 1;

			var pidVal = document.commentform.pid.value;
			var parentId;
			if ( !document.commentform.comment_parent_id.value ) {
				parentId = 0;
			} else {
				parentId = document.commentform.comment_parent_id.value;
			}
			var commentText = document.commentform.comment_text.value;
			var token = document.commentform.token.value;

			sajax_request_type = 'POST';
			sajax_do_call(
				'wfCommentSubmit',
				[ pidVal, parentId, commentText, token ],
				function( response ) {
					if ( response.responseText === 'ok' ) {
						document.commentform.comment_text.value = '';
						Comment.viewComments( document.commentform.pid.value, 0, 1, document.commentform.cpage.value );
					} else if ( response.responseText != undefined && response.responseText != null && response.responseText != '' ) {
						alert( response.responseText );
						Comment.submitted = 0;
					}
				}
			);
			Comment.cancelReply();
		}
	},

	/**
	 * Toggle comment auto-refreshing on or off
	 *
	 * @param status
	 */
	toggleLiveComments: function( status ) {
		if ( status ) {
			Comment.pause = 0;
		} else {
			Comment.pause = 1;
		}
		var msg;
		if ( status ) {
			msg = mw.msg( 'comments-auto-refresher-pause' );
		} else {
			msg = mw.msg( 'comments-auto-refresher-enable' );
		}

		jQuery( 'body' ).on( 'click', 'div#spy a', function() {
			Comment.toggleLiveComments( ( status ) ? 0 : 1 );
		} );
		jQuery( 'div#spy a' ).css( 'font-size', '10px' ).text( msg );

		if ( !Comment.pause ) {
			Comment.LatestCommentID = document.commentform.lastcommentid.value;
			Comment.timer = setTimeout(
				function() { Comment.checkUpdate(); },
				Comment.updateDelay
			);
		}
	},

	checkUpdate: function() {
		if ( Comment.isBusy ) {
			return;
		}
		var pid = document.commentform.pid.value;
		sajax_do_call( 'wfCommentLatestID', [ pid ], function( response ) {
			Comment.updateResults( response );
		});
		Comment.isBusy = true;
		return false;
	},

	updateResults: function( response ) {
		if ( !response || response.readyState != 4 ) {
			return;
		}

		if ( response.status == 200 ) {
			// Get last new ID
			Comment.CurLatestCommentID = response.responseText;
			if ( Comment.CurLatestCommentID != Comment.LatestCommentID ) {
				Comment.viewComments( document.commentform.pid.value, 0, 1, document.commentform.cpage.value );
				Comment.LatestCommentID = Comment.CurLatestCommentID;
			}
		}

		Comment.isBusy = false;
		if ( !Comment.pause ) {
			clearTimeout( Comment.timer );
			Comment.timer = setTimeout(
				function() { Comment.checkUpdate(); },
				Comment.updateDelay
			);
		}
	},

	/**
	 * Show the "reply to user X" form
	 *
	 * @param parentId Integer: parent comment (the one we're replying to) ID
	 * @param poster String: name of the person whom we're replying to
	 * @param posterGender String: gender of the person whom we're replying to
	 */
	reply: function( parentId, poster, posterGender ) {
		jQuery( '#replyto' ).text(
			mw.msg( 'comments-reply-to', poster, posterGender ) + ' ('
		);
		jQuery( '<a>', {
			href: 'javascript:void(0);',
			'class': 'comments-cancel-reply-link',
			text: mw.msg( 'comments-cancel-reply' )
		} ).appendTo( '#replyto' );
		jQuery( '#replyto' ).append( ') <br />' );

		document.commentform.comment_parent_id.value = parentId;
	},

	cancelReply: function() {
		document.getElementById( 'replyto' ).innerHTML = '';
		document.commentform.comment_parent_id.value = '';
	}
};

jQuery( document ).ready( function() {
	// Important note: these are all using jQuery( 'body' ) as the selector
	// instead of the class/ID/whatever so that they work after viewComments()
	// has been called (i.e. so that "Delete comment", reply, etc. links
	// continue working after you've submitted a comment yourself)

	// "Sort by X" feature
	jQuery( 'body' ).on( 'change', 'select[name="TheOrder"]', function() {
		Comment.viewComments(
			mw.config.get( 'wgArticleId' ), // or we could use jQuery( 'input[name="pid"]' ).val(), too
			jQuery( this ).val(),
			0,
			document.commentform.cpage.value
		);
	} );

	// Comment auto-refresher
	jQuery( 'body' ).on( 'click', 'div#spy a', function() {
		Comment.toggleLiveComments( 1 );
	} );

	// Voting links
	jQuery( 'body' ).on( 'click', 'a#comment-vote-link', function() {
		var that = jQuery( this );
		Comment.vote(
			that.data( 'comment-id' ),
			that.data( 'vote-type' ),
			that.data( 'voting' )
		);
	} );

	// "Block this user" links
	jQuery( 'body' ).on( 'click', 'a.comments-block-user', function() {
		var that = jQuery( this );
		Comment.blockUser(
			that.data( 'comments-safe-username' ),
			that.data( 'comments-user-id' ),
			that.data( 'comments-comment-id' )
		);
	} );

	// "Delete Comment" links
	jQuery( 'body' ).on( 'click', 'a.comment-delete-link', function() {
		Comment.deleteComment( jQuery( this ).data( 'comment-id' ) );
	} );

	// "Show this hidden comment" -- comments made by people on the user's
	// personal block list
	jQuery( 'body' ).on( 'click', 'div.c-ignored-links a', function() {
		Comment.show( jQuery( this ).data( 'comment-id' ) );
	} );

	// Reply links
	jQuery( 'body' ).on( 'click', 'a.comments-reply-to', function() {
		Comment.reply(
			jQuery( this ).data( 'comment-id' ),
			jQuery( this ).data( 'comments-safe-username' ),
			jQuery( this ).data( 'comments-user-gender' )
		);
	} );

	// "Reply to <username>" links
	jQuery( 'body' ).on( 'click', 'a.comments-cancel-reply-link', function() {
		Comment.cancelReply();
	} );

	// Handle clicks on the submit button (previously this was an onclick attr)
	jQuery( 'body' ).on( 'click', 'div.c-form-button input[type="button"]', function() {
		Comment.submit();
	} );

	// Change page
	jQuery( 'body' ).on( 'click', 'li.c-pager-item a.c-pager-link', function() {
		var ord = 0,
			commentsBody = jQuery( this ).parents( 'div.comments-body:first' );

		if ( commentsBody.length > 0 ) {
			var ordCrtl = commentsBody.first().find( 'select[name="TheOrder"]:first' );
			if ( ordCrtl.length > 0 ) {
				ord = ordCrtl.val();
			}
		}

		Comment.viewComments(
			mw.config.get( 'wgArticleId' ), // or we could use jQuery( 'input[name="pid"]' ).val(), too
			ord,
			0,
			jQuery( this ).data( 'cpage' )
		);
	} );
} );