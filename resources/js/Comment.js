/**
 * JavaScript for the Comments extension.
 *
 * @param $
 * @param mw
 * @file
 */
( function ( $, mw ) {
	var Comment = {
		submitted: 0,
		isBusy: false,
		timer: '', // has to have an initial value...
		updateDelay: 7000,
		LatestCommentID: '',
		CurLatestCommentID: '',
		pause: 0,
		currentPage: 1,
		currentArticleID: mw.config.get( 'wgArticleId' ),

		/**
		 * When a comment's author is ignored, "Show Comment" link will be
		 * presented to the user.
		 * If the user clicks on it, this function is called to show the hidden
		 * comment.
		 *
		 * @param {string} id
		 */
		show: function ( id ) {
			$( '#ignore-' + id ).hide( 300 );
			$( '#comment-' + id ).show( 300 );
		},

		/**
		 * This function is called whenever a user clicks on the "block" image to
		 * block another user's comments.
		 *
		 * @param {string} username Name of the user whose comments we want to block
		 * @param {number} userID User ID number of the user whose comments we
		 *                         want to block (or 0 for anonymous users)
		 * @param {number} commentID Comment ID number
		 */
		blockUser: function ( username, userID, commentID ) {
			var message;

			// Display a different message depending on whether we're blocking an
			// anonymous user or a registered one.
			if ( !userID || userID === 0 ) {
				message = mw.msg( 'comments-block-warning-anon' );
			} else {
				message = mw.msg( 'comments-block-warning-user', username );
			}

			if ( window.confirm( message ) ) {
				( new mw.Api() ).postWithToken( 'csrf', {
					action: 'commentblock',
					commentID: commentID
				} ).done( function ( response ) {
					if ( response.commentblock.ok ) {
						$( 'a.comments-block-user[data-comments-user-id=' + userID + ']' )
							.parents( '.c-item' ).hide( 300 )
							.prev().show( 300 );
					}
				} );
			}
		},

		/**
		 * This function is called whenever a user clicks on the "Delete Comment"
		 * link to delete a comment.
		 *
		 * @param {number} commentID Comment ID number
		 */
		deleteComment: function ( commentID ) {
			if ( window.confirm( mw.msg( 'comments-delete-warning' ) ) ) {
				( new mw.Api() ).postWithToken( 'csrf', {
					action: 'commentdelete',
					commentID: commentID
				} ).done( function ( response ) {
					if ( response.commentdelete.ok ) {
						$( '#comment-' + commentID ).hide( 2000 );
					}
				} );
			}
		},

		/**
		 * Send a modified comment's new text to the backend and display backend-supplied
		 * response to the user.
		 *
		 * @param {number} commentID ID of the comment to edit
		 */
		edit: function ( commentID ) {
			var commentText, $comment;
			$comment = $( '#comment-' + commentID );
			commentText = $comment.find( 'textarea' ).val();
			if ( !commentText.length ) {
				return;
			}
			Comment.toggleEditMode();
			$comment.find( '.c-comment' ).html( '<span class="loader"></span>' );

			( new mw.Api() ).postWithToken( 'csrf', {
				action: 'commentedit',
				commentID: commentID,
				commentText: commentText,
				pageID: this.currentArticleID
			} ).done( function ( response ) {
				if ( response.commentedit.ok ) {
					$comment.find( '.c-comment' ).html( response.commentedit.ok );
				}
			} );
		},

		/**
		 * Enable the editing mode for a supplied comment.
		 *
		 * @param {number} commentID ID of the comment to edit
		 */
		toggleEditMode: function ( commentID ) {
			var $comment;
			$( '.c-item' ).removeClass( 'c-item--edit-mode' );
			if ( typeof commentID !== 'undefined' ) {
				$comment = $( '#comment-' + commentID );
				$comment.addClass( 'c-item--edit-mode' );
			}
		},

		/**
		 * Vote for a comment.
		 *
		 * @param {number} commentID Comment ID number
		 * @param {number} voteValue Vote value
		 */
		vote: function ( commentID, voteValue ) {
			( new mw.Api() ).postWithToken( 'csrf', {
				action: 'commentvote',
				commentID: commentID,
				voteValue: voteValue
			} ).done( function ( response ) {
				$( '#comment-' + commentID + ' .c-score' )
					.html( response.commentvote.html ) // this will still be escaped
					.html( $( '#comment-' + commentID + ' .c-score' ).text() ); // unescape
			} );
		},

		/**
		 * Called on DOM ready; loads the list of comments via AJAX by querying the API
		 * and injects the results into #comments-body
		 */
		initialize: function () {
			$.ajax( {
				url: mw.config.get( 'wgScriptPath' ) + '/api.php',
				data: {
					action: 'commentlist',
					format: 'json',
					pageID: this.currentArticleID,
					order: 1,
					pagerPage: 1,
					showForm: 1
				},
				cache: false
			} ).done( function ( response ) {
				document.getElementById( 'comments-body' ).innerHTML = response.commentlist.html;
				// Comments are parsed wikitext, and some templates depend on this hook to work
				mw.hook( 'wikipage.content' ).fire( $( '#comments-body' ) );
				// Move the "Sort by <date/score>" menu *above* all the comments, as it oughta be (T292893)
				$( '.c-order' ).prependTo( '#comments-body' );
				// This looks awfully silly but seems to be needed to have the permalink feature
				// (at least partially?) working (T295567)
				if ( window.location.hash !== '' ) {
					// eslint-disable-next-line no-self-assign
					window.location.hash = window.location.hash;
				}
			} );
		},

		/**
		 * @param {string} order Sorting order
		 * @param {boolean} end Scroll to bottom after?
		 */
		viewComments: function ( order, end ) {
			if ( typeof document.commentForm.cpage !== 'undefined' ) {
				document.commentForm.cpage.value = this.currentPage;
			}

			document.getElementById( 'allcomments' ).innerHTML = mw.msg( 'comments-loading' ) + '<br /><br />';

			$.ajax( {
				url: mw.config.get( 'wgScriptPath' ) + '/api.php',
				data: {
					action: 'commentlist',
					pageID: this.currentArticleID,
					order: order,
					pagerPage: this.currentPage,
					format: 'json'
				},
				cache: false
			} ).done( function ( response ) {
				document.getElementById( 'allcomments' ).innerHTML = response.commentlist.html;
				Comment.submitted = 0;
				if ( end ) {
					window.location.hash = 'end';
				}
			} );
		},

		/**
		 * Preview the contents of the <textarea> (more accurately the
		 * wikitext given to this function) and show the parsed HTML
		 * output in the given element.
		 *
		 * @param {string} text Wikitext to be sent to the API for parsing
		 * @param {string} selector jQuery selector for selecting the element
		 * where the preview text should be inserted
		 */
		preview: function ( text, selector ) {
			( new mw.Api() ).get( {
				action: 'parse',
				text: text,
				// do pre-save transformation to expand pipe tricks
				// (e.g. [[Template:FooBar|]] to [[Template:FooBar|FooBar]]) and the like
				pst: true,
				prop: 'text',
				contentmodel: 'wikitext',
				disablelimitreport: true // don't need the NewPP report stuff
			} ).done( function ( data ) {
				$( selector ).html( data.parse.text[ '*' ] );
			} ).fail( function () {
				$( selector ).text( mw.msg( 'comments-preview-failed' ) );
			} );
		},

		/**
		 * Submit a new comment.
		 */
		submit: function () {
			var pageID, parentID, commentText;

			if ( Comment.submitted === 0 ) {
				Comment.submitted = 1;

				pageID = document.commentForm.pageId.value;
				if ( !document.commentForm.commentParentId.value ) {
					parentID = 0;
				} else {
					parentID = document.commentForm.commentParentId.value;
				}
				commentText = document.commentForm.commentText.value;

				( new mw.Api() ).postWithToken( 'csrf', {
					action: 'commentsubmit',
					pageID: pageID,
					parentID: parentID,
					commentText: commentText
				} ).done( function ( response ) {
					var end;

					if ( response.commentsubmit && response.commentsubmit.ok ) {
						document.commentForm.commentText.value = '';
						end = 1;
						if ( mw.config.get( 'wgCommentsSortDescending' ) ) {
							end = 0;
						}
						Comment.viewComments(
							0,
							end
						);
					} else {
						window.alert( response.error.info );
						Comment.submitted = 0;
					}
				} ).fail( function ( textStatus, response ) {
					// textStatus is the error code from CommentSubmitAPI.php, i.e. one of these:
					// comments-missing-page, comments-is-spam or comments-links-are-forbidden
					// (and when AbuseFilter is installed, it can be e.g. abusefilter-disallowed as well)
					// response is the fuller object with code and info properties
					var msg;
					if ( textStatus === 'comments-missing-page' ) {
						// 'comments-missing-page' is not (yet?) an i18n msg, just a key
						// this corresponds to CommentSubmitAPI.php's behavior 1:1
						msg = mw.msg( 'apierror-nosuchpageid', pageID );
					} else if (
						response.code === 'comments-is-spam' ||
						response.code === 'comments-links-are-forbidden'
					) {
						msg = mw.msg( response.code );
					} else {
						// AbuseFilter case - response is whatever AF bubbled up and caused dieStatus()
						// to be triggered in CommentSubmitAPI.php; seems to be a string like "abusefilter-disallowed"
						msg = response.error.info;
					}
					window.alert( msg );
					Comment.submitted = 0;
				} );

				Comment.cancelReply();
			}
		},

		/**
		 * Toggle comment auto-refreshing on or off
		 *
		 * @param {boolean} status
		 */
		toggleLiveComments: function ( status ) {
			var msg;

			if ( status ) {
				Comment.pause = 0;
			} else {
				Comment.pause = 1;
			}
			if ( status ) {
				msg = mw.msg( 'comments-auto-refresher-pause' );
			} else {
				msg = mw.msg( 'comments-auto-refresher-enable' );
			}

			$( document.body ).on( 'click', 'div#spy a', function () {
				Comment.toggleLiveComments( ( status ) ? 0 : 1 );
			} );
			$( 'div#spy a' ).css( 'font-size', '10px' ).text( msg );

			if ( !Comment.pause ) {
				Comment.LatestCommentID = document.commentForm.lastCommentId.value;
				Comment.timer = setTimeout(
					function () {
						Comment.checkUpdate();
					},
					Comment.updateDelay
				);
			}
		},

		checkUpdate: function () {
			if ( Comment.isBusy ) {
				return;
			}

			$.ajax( {
				url: mw.config.get( 'wgScriptPath' ) + '/api.php',
				data: {
					action: 'commentlatestid',
					format: 'json',
					pageID: this.currentArticleID
				},
				cache: false
			} ).done( function ( response ) {
				if ( response.commentlatestid.id ) {
					// Get last new ID
					Comment.CurLatestCommentID = response.commentlatestid.id;
					if ( Comment.CurLatestCommentID !== Comment.LatestCommentID ) {
						Comment.viewComments(
							0,
							1
						);
						Comment.LatestCommentID = Comment.CurLatestCommentID;
					}
				}

				Comment.isBusy = false;
				if ( !Comment.pause ) {
					clearTimeout( Comment.timer );
					Comment.timer = setTimeout(
						function () {
							Comment.checkUpdate();
						},
						Comment.updateDelay
					);
				}
			} );

			Comment.isBusy = true;
			return false;
		},

		/**
		 * Show the "reply to user X" form
		 *
		 * @param {number} parentId Parent comment (the one we're replying to) ID
		 * @param {string} poster Name of the person whom we're replying to
		 * @param {string} posterGender Gender of the person whom we're replying to
		 */
		reply: function ( parentId, poster, posterGender ) {
			$( '#replyto' ).text(
				mw.message( 'comments-reply-to', poster, posterGender ).parse() + ' ('
			);
			$( '<a>', {
				class: 'comments-cancel-reply-link',
				style: 'cursor:pointer',
				text: mw.msg( 'comments-cancel-reply' )
			} ).appendTo( '#replyto' );
			$( '#replyto' ).append( ') <br />' );

			document.commentForm.commentParentId.value = parentId;
		},

		cancelReply: function () {
			document.getElementById( 'replyto' ).innerHTML = '';
			document.commentForm.commentParentId.value = '';
		}
	};

	$( function () {
		// Important note: these are all using $( document.body ) as the selector
		// instead of the class/ID/whatever so that they work after viewComments()
		// has been called (i.e. so that "Delete comment", reply, etc. links
		// continue working after you've submitted a comment yourself)

		var $CommentsWrapper = $( '#comments-body' );

		// "Sort by X" feature
		$( document.body )
			.on( 'change', 'select[name="TheOrder"]', function () {
				Comment.viewComments(
					$( this ).val(),
					0
				);
			} )

			// Comment auto-refresher
			.on( 'click', 'div#spy a', function () {
				Comment.toggleLiveComments( 1 );
			} )

			// Voting links
			.on( 'click', 'a#comment-vote-link', function () {
				var that = $( this );
				Comment.vote(
					that.data( 'comment-id' ),
					that.data( 'vote-type' )
				);
			} )

			// "Block this user" links
			.on( 'click', 'a.comments-block-user', function () {
				var that = $( this );
				Comment.blockUser(
					that.data( 'comments-safe-username' ),
					that.data( 'comments-user-id' ),
					that.data( 'comments-comment-id' )
				);
			} )

			// "Delete Comment" links
			.on( 'click', 'a.comment-delete-link', function () {
				Comment.deleteComment( $( this ).data( 'comment-id' ) );
			} )

			// Comment editing feature -- "Edit"/"Cancel"/"Save" links/buttons
			.on( 'click', 'a.comments-edit', function ( e ) {
				e.preventDefault();
				Comment.toggleEditMode( $( this ).data( 'comment-id' ) );
			} )

			.on( 'click', 'button.c-comment-edit-form-cancel', function ( e ) {
				e.preventDefault();
				Comment.toggleEditMode();
			} )

			.on( 'click', 'button.c-comment-edit-form-save', function ( e ) {
				e.preventDefault();
				Comment.edit( $( this ).data( 'comment-id' ) );
			} )

			// "Show this hidden comment" -- comments made by people on the user's
			// personal block list
			.on( 'click', 'div.c-ignored-links a', function () {
				Comment.show( $( this ).data( 'comment-id' ) );
			} )

			// Reply links
			.on( 'click', 'a.comments-reply-to', function () {
				Comment.reply(
					$( this ).data( 'comment-id' ),
					$( this ).data( 'comments-safe-username' ),
					$( this ).data( 'comments-user-gender' )
				);
			} )

			// "Reply to <username>" links
			.on( 'click', 'a.comments-cancel-reply-link', function () {
				Comment.cancelReply();
			} )

			// Handle clicks on the submit button (previously this was an onclick attr)
			.on( 'click', 'div.c-form-button input[name="wpSubmitComment"]', function () {
				Comment.submit();
				// 1) Make comment text <s>great</s> editable again (in case if the user
				// previewed their comment first and only then hit the "post comment" button)
				$( '#comment' ).prop( 'disabled', '' );
				// 2) Clear the preview area
				$( '.comment-preview-note' ).remove();
				$( 'form[name="commentForm"] .comment-preview' ).html( '' );
			} )

			// Handle clicks on the preview button
			.on( 'click', 'div.c-form-button input[name="wpPreview"]', function () {
				var textareaText = $( '#comment' ).val(),
					previewing = ( $( '#comment' ).prop( 'disabled' ) === true );

				// Note: This code has to be here, it won't work from the .click handler below,
				// after calling Comment.preview. Some JavaScript guru can properly explain
				// *why* that is so, I merely observed it and observed that putting the code
				// here makes the feature work as intended, which is good enough for me!
				if ( previewing ) {
					// When we are already previewing some user-given text and the user clicks on
					// the "Show preview" button, then actually labeled "Continue editing", we want
					// to do two things:
					// 1) Make comment text <s>great</s> editable again
					$( '#comment' ).prop( 'disabled', '' );
					// 2) Clear the preview area
					$( '.comment-preview-note' ).remove();
					$( 'form[name="commentForm"] .comment-preview' ).html( '' );
				}

				if ( textareaText !== '' && !previewing ) {
					// Disable editing the text in the textarea element until the user
					// chooses to continue editing
					$( '#comment' ).prop( 'disabled', true );

					// Insert the "This is just a preview" text before the preview, but only once!
					if ( $( 'form[name="commentForm"] .comment-preview' ).html() === '' ) {
						$( 'form[name="commentForm"] .comment-preview' ).before(
							'<div class="comment-preview-note">' +
							mw.msg( 'previewnote' ) +
							'</div>'
						);
					}

					// Call the API to render the text given by the user into pretty wikitext!
					Comment.preview(
						textareaText,
						'form[name="commentForm"] .comment-preview'
					);

					// Change the text of the "Show preview" button from "Show preview" to
					// "Continue editing" when we are already previewing something, and make it so
					// that upon clicking on this button the button text is changed back
					// to "Show preview"
					$( '.c-form-button input[name="wpPreview"]' )
						.val( mw.msg( 'comments-continue-editing-btn' ) )
						.click( function () {
							$( this ).val( mw.msg( 'showpreview' ) );
						} );
				}
			} )

			// Change page
			.on( 'click', 'li.c-pager-item a.c-pager-link', function () {
				var ordCrtl, ord = 0,
					commentsBody = $( this ).parents( 'div.comments-body:first' );

				if ( commentsBody.length > 0 ) {
					ordCrtl = commentsBody.first().find( 'select[name="TheOrder"]:first' );
					if ( ordCrtl.length > 0 ) {
						ord = ordCrtl.val();
					}
				}

				Comment.currentPage = parseInt( $( this ).data( 'cpage' ) );

				Comment.viewComments(
					ord,
					0
				);
			} );

		if ( $CommentsWrapper.length ) {
			Comment.initialize();
		}
	} );

}( jQuery, mediaWiki ) );
