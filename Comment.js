/**
 * JavaScript for the Comments extension.
 * Rewritten by Jack Phoenix <jack@countervandalism.net> to be more
 * object-oriented.
 *
 * @file
 * @date 19 June 2011
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
	 * Change the opacity of an element in a cross-browser compatible manner.
	 *
	 * @param opacity Integer: opacity
	 * @param id String: element ID
	 */
	changeOpacity: function( opacity, id ) {
		var object = document.getElementById( id ).style;
		object.opacity = ( opacity / 100 );
		object.MozOpacity = ( opacity / 100 );
		object.KhtmlOpacity = ( opacity / 100 );
		object.filter = 'alpha(opacity=' + opacity + ')';
	},

	/**
	 * Code from http://brainerror.net/scripts/javascript/blendtrans/
	 *
	 * @param id String: element ID
	 * @param opacStart Integer
	 * @param opacEnd Integer
	 * @param millisec Integer
	 */
	opacity: function( id, opacStart, opacEnd, millisec ) {
		// speed for each frame
		var speed = Math.round( millisec / 100 );
		var timer = 0;
		var i;

		// determine the direction for the blending, if start and end are the same nothing happens
		if( opacStart > opacEnd ) {
			for( i = opacStart; i >= opacEnd; i-- ) {
				setTimeout( "Comment.changeOpacity(" + i + ",'" + id + "')", ( timer * speed ) );
				timer++;
				document.getElementById( id ).style.display = 'none'; // added by Jack
			}
		} else if( opacStart < opacEnd ) {
			for( i = opacStart; i <= opacEnd; i++ ) {
				setTimeout( "Comment.changeOpacity(" + i + ",'" + id + "')", ( timer * speed ) );
				timer++;
				document.getElementById( id ).style.display = 'block'; // added by Jack
			}
		}
	},

	/**
	 * When a comment's author is ignored, "Show Comment" link will be
	 * presented to the user.
	 * If the user clicks on it, this function is called to show the hidden
	 * comment.
	 */
	show: function( id ) {
		Comment.opacity( 'ignore-' + id, 100, 0, 6500 );
		Comment.opacity( 'comment-' + id, 0, 100, 500 );
	},

	/**
	 * This function is called whenever a user clicks on the "block" image to
	 * block another user's comments.
	 *
	 * @param user_name String: name of the user whose comments we want to block
	 * @param user_id Integer: user ID number of the user whose comments we
	 *                         want to block
	 * @param c_id Integer: comment ID number
	 * @param mk String: vote key (MD5-hashed combination of comment ID, the
	 *                   string 'pants' and user's name); unused
	 */
	blockUser: function( user_name, user_id, c_id, mk ) {
		if( !user_name ) {
			user_name = _COMMENT_BLOCK_ANON;
		} else {
			user_name = _COMMENT_BLOCK_USER + ' ' + user_name;
		}
		if( confirm( _COMMENT_BLOCK_WARNING + ' ' + user_name + ' ?' ) ) {
			sajax_request_type = 'POST';
			sajax_do_call( 'wfCommentBlock', [ c_id, user_id, mk ], function( response ) {
				alert( response.responseText );
				window.location.href = window.location;
			});
		}
	},

	/**
	 * Vote for a comment.
	 * Formerly called "cv"
	 *
	 * @param cid Integer: comment ID number
	 * @param vt Integer: vote value
	 * @param mk String: vote key (MD5-hashed combination of comment ID, the
	 *                   string 'pants' and user's name); unused
	 * @param vg
	 */
	vote: function( cid, vt, mk, vg ) {
		sajax_request_type = 'POST';
		sajax_do_call(
			'wfCommentVote',
			[ cid, vt, mk, ( ( vg ) ? vg : 0 ), document.commentform.pid.value ],
			function( response ) {
				document.getElementById( 'Comment' + cid ).innerHTML = response.responseText;
				var img = '<img src="' + wgScriptPath + '/extensions/Comments/images/voted.gif" alt="" />';
				document.getElementById( 'CommentBtn' + cid ).innerHTML =
					img + '<span class="CommentVoted">' + _COMMENT_VOTED + '</span>';
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
	 */
	viewComments: function( pid, ord, end ) {
		document.getElementById( 'allcomments' ).innerHTML = _COMMENT_LOADING + '<br /><br />';
		var x = sajax_init_object();
		var url = wgServer + wgScriptPath +
			'/index.php?title=Special:CommentListGet&pid=' + pid + '&ord=' +
			ord;

		x.open( 'get', url, true );

		x.onreadystatechange = function() {
			if( x.readyState != 4 ) {
				return;
			}

			document.getElementById( 'allcomments' ).innerHTML = x.responseText;
			Comment.submitted = 0;
			if( end ) {
				window.location.hash = 'end';
			}
		};

		x.send( null );
	},

	/**
	 * HTML-encodes ampersands and plus signs in the given input string.
	 *
	 * @param str String: input
	 * @return String: input with ampersands and plus signs encoded
	 */
	fixString: function( str ) {
		str = str.replace( /&/gi, '%26' );
		str = str.replace( /\+/gi, '%2B' );
		return str;
	},

	/**
	 * Submit a new comment.
	 */
	submit: function() {
		if( Comment.submitted === 0 ) {
			Comment.submitted = 1;

			// Moved variables here...
			var pidVal = document.commentform.pid.value;
			var parentId;
			if ( !document.commentform.comment_parent_id.value ) {
				parentId = 0;
			} else {
				parentId = document.commentform.comment_parent_id.value;
			}
			var fixedStr = Comment.fixString( document.commentform.comment_text.value );
			var sid = document.commentform.sid.value;
			var mk = document.commentform.mk.value;

			// @todo CHECKME: possible double-encoding
			// (fixString func + encodeURIComponent, which sajax object does)
			sajax_request_type = 'POST';
			sajax_do_call(
				'wfCommentSubmit',
				[ pidVal, parentId, fixedStr, sid, mk ],
				function( response ) {
					document.commentform.comment_text.value = '';
					Comment.viewComments( document.commentform.pid.value, 0, 1 );
				}
			);
			Comment.cancelReply();
		}
	},

	/**
	 * I'm not sure what is the purpose of this function. This is used in
	 * toggleLiveComments() below.
	 * AFAIK we can do document.getElementById( 'spy' ).innerHTML and get the
	 * desired results in all browsers, including Internet Explorer.
	 */
	Ob: function( e, f ) {
		if( document.all ) {
			return ( ( f ) ? document.all[e].style : document.all[e] );
		} else {
			return ( ( f ) ? document.getElementById( e ).style : document.getElementById( e ) );
		}
	},

	toggleLiveComments: function( status ) {
		var Pause;
		// @todo FIXME/CHECKME: maybe this should be Comment.pause instead?
		if( status ) {
			Pause = 0;
		} else {
			Pause = 1;
		}
		var msg;
		if ( status ) {
			msg = _COMMENT_PAUSE_REFRESHER;
		} else {
			msg = _COMMENT_ENABLE_REFRESHER;
		}
		Comment.Ob( 'spy' ).innerHTML =
			'<a href="javascript:Comment.toggleLiveComments(' + ( ( status ) ? 0 : 1 ) +
			')" style="font-size: 10px">' + msg + '</a>';
		if( !Comment.pause ) {
			Comment.LatestCommentID = document.commentform.lastcommentid.value;
			Comment.timer = setTimeout(
				'Comment.checkUpdate()',
				Comment.updateDelay
			);
		}
	},

	checkUpdate: function() {
		if( Comment.isBusy ) {
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
		if( !response || response.readyState != 4 ) {
			return;
		}

		if( response.status == 200 ) {
			// Get last new ID
			Comment.CurLatestCommentID = response.responseText;
			if( Comment.CurLatestCommentID != Comment.LatestCommentID ) {
				Comment.viewComments( document.commentform.pid.value, 0, 1 );
				Comment.LatestCommentID = Comment.CurLatestCommentID;
			}
		}

		Comment.isBusy = false;
		if( !Comment.pause ) {
			clearTimeout( Comment.timer );
			Comment.timer = setTimeout( 'Comment.checkUpdate()', Comment.updateDelay );
		}
	},

	/**
	 * Show the "reply to user X" form
	 *
	 * @param parentId Integer
	 * @param poster String: name of the person whom we're replying to
	 */
	reply: function( parentId, poster ) {
		document.getElementById( 'replyto' ).innerHTML = _COMMENT_REPLY_TO +
			' ' + poster + ' (<a href="javascript:Comment.cancelReply()">' +
			_COMMENT_CANCEL_REPLY + '</a>) <br />';
		document.commentform.comment_parent_id.value = parentId;
	},

	cancelReply: function() {
		document.getElementById( 'replyto' ).innerHTML = '';
		document.commentform.comment_parent_id.value = '';
	}
};