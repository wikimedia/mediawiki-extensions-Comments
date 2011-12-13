<?php
/**
 * Comments extension - adds <comments> parserhook to allow commenting on pages
 *
 * @file
 * @ingroup Extensions
 * @version 2.4.1
 * @author David Pean <david.pean@gmail.com>
 * @author Misza <misza@shoutwiki.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2008-2011 David Pean, Misza and Jack Phoenix
 * @link http://www.mediawiki.org/wiki/Extension:Comments Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'name' => 'Comments',
	'version' => '2.4.1',
	'author' => array( 'David Pean', 'Misza', 'Jack Phoenix' ),
	'description' => 'Adds <tt>&lt;comments&gt;</tt> parser hook that allows commenting on articles',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Comments'
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.comments'] = array(
	'scripts' => 'Comment.js',
	'styles' => 'Comments.css',
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'Comments',
	'position' => 'top' // available since r85616
);

// Configuration variables
// Web-readable path to the directory where the extension files are located
$wgCommentsDirectory = "$wgScriptPath/extensions/Comments";
// Path to an image which will be displayed instead of an avatar if social tools aren't installed.
// Should be 50x50px
$wgCommentsDefaultAvatar = 'http://www.shoutwiki.com/w/extensions/SocialProfile/avatars/default_ml.gif';

// New user rights
$wgAvailableRights[] = 'comment';
$wgAvailableRights[] = 'commentadmin';
// Allows everyone, including unregistered users, to comment
$wgGroupPermissions['*']['comment'] = true;
// Allows users in the commentadmin group to administrate comments (incl. comment deletion)
$wgGroupPermissions['commentadmin']['commentadmin'] = true;

// Set up the new special pages
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['Comments'] = $dir . 'Comments.i18n.php';
$wgAutoloadClasses['Comment'] = $dir . 'CommentClass.php';
$wgAutoloadClasses['CommentIgnoreList'] = $dir . 'SpecialCommentIgnoreList.php';
$wgAutoloadClasses['CommentListGet'] = $dir . 'CommentAction.php';
$wgSpecialPages['CommentIgnoreList'] = 'CommentIgnoreList';
$wgSpecialPages['CommentListGet'] = 'CommentListGet';
// Special page group for MW 1.13+
$wgSpecialPageGroups['CommentIgnoreList'] = 'users';

// Load the AJAX functions required by this extension
require_once( 'Comments_AjaxFunctions.php' );

// Add a new log type
$wgLogTypes[] = 'comments';
$wgLogNames['comments'] = 'commentslogpage';
$wgLogHeaders['comments'] = 'commentslogpagetext';
$wgLogActions['comments/comments'] = 'commentslogentry';
// This hides comment log entries from Special:Log, much like how patrol stuff
// is hidden by default, but can be enabled via a link
$wgFilterLogTypes['comments'] = true;

$wgHooks['ParserFirstCallInit'][] = 'wfComments';

/**
 * Register the <comments> tag with the Parser.
 *
 * @param $parser Object: instance of Parser
 * @return Boolean: true
 */
function wfComments( &$parser ) {
	$parser->setHook( 'comments', 'displayComments' );
	return true;
}

function displayComments( $input, $args, $parser ) {
	global $wgTitle, $wgOut, $wgScriptPath, $wgHooks;

	wfProfileIn( __METHOD__ );

	$parser->disableCache();

	// Add required CSS & JS
	if ( defined( 'MW_SUPPORTS_RESOURCE_MODULES' ) ) {
		$wgOut->addModules( 'ext.comments' );
	} else {
		$wgOut->addScriptFile( $wgScriptPath . '/extensions/Comments/Comment.js' );
		$wgOut->addExtensionStyle( $wgScriptPath . '/extensions/Comments/Comments.css' );
	}

	// Add i18n for JS
	$wgHooks['MakeGlobalVariablesScript'][] = 'wfAddCommentJSVars';

	// Parse arguments
	$allow = '';
	if( preg_match( '/^\s*Allow\s*=\s*(.*)/mi', $input, $matches ) ) {
		$allow = htmlspecialchars( $matches[1] );
	} elseif( !empty( $args['allow'] ) ) {
		$allow = $args['allow'];
	}

	$voting = '';
	if( preg_match( '/^\s*Voting\s*=\s*(.*)/mi', $input, $matches ) ) {
		$voting = htmlspecialchars( $matches[1] );
	} elseif(
		!empty( $args['voting'] ) &&
		in_array( strtoupper( $args['voting'] ), array( 'OFF', 'PLUS', 'MINUS' ) )
	)
	{
		$voting = $args['voting'];
	}

	$comment = new Comment( $wgTitle->getArticleID() );
	$comment->setAllow( $allow );
	$comment->setVoting( $voting );

	if( isset( $_POST['commentid'] ) ) { // isset added by misza
		$comment->setCommentID( $_POST['commentid'] );
		$comment->delete();
	}
	// This was originally commented out, I don't know why.
	// Uncommented to prevent E_NOTICE.
	$output = $comment->displayOrderForm();

	$output .= '<div id="allcomments">' . $comment->display() . '</div>';

	if( !wfReadOnly() ) {
		$output .= $comment->displayForm();
	} else {
		$output .= wfMsg( 'comments-db-locked' );
	}

	wfProfileOut( __METHOD__ );

	return $output;
}

/**
 * Add some i18n messages to the array of JS globals. This is called from
 * displayComments() (the callback function for wfComments).
 *
 * @param $vars Array: array of pre-existing JavaScript global variables
 * @return Boolean: true
 */
function wfAddCommentJSVars( $vars ) {
	$vars['_COMMENT_VOTED'] = wfMsg( 'comment-voted-label' );
	$vars['_COMMENT_LOADING'] = wfMsg( 'comment-loading' );
	$vars['_COMMENT_PAUSE_REFRESHER'] = wfMsg( 'comment-auto-refresher-pause' );
	$vars['_COMMENT_ENABLE_REFRESHER'] = wfMsg( 'comment-auto-refresher-enable' );
	$vars['_COMMENT_CANCEL_REPLY'] = wfMsg( 'comment-cancel-reply' );
	$vars['_COMMENT_REPLY_TO'] = wfMsg( 'comment-reply-to' );
	$vars['_COMMENT_BLOCK_WARNING'] = wfMsg( 'comment-block-warning' );
	$vars['_COMMENT_BLOCK_ANON'] = wfMsg( 'comment-block-anon' );
	$vars['_COMMENT_BLOCK_USER'] = wfMsg( 'comment-block-user' );
	return true;
}

// Translations for {{NUMBEROFCOMMENTS}}
//$wgExtensionMessagesFiles['NumberOfComments'] = $dir . 'Comments.i18n.magic.php';

$wgHooks['LanguageGetMagic'][] = 'wfNumberOfCommentsMagicWord';
function wfNumberOfCommentsMagicWord( &$magicWords, $langID ) {
	// tell MediaWiki that {{NUMBEROFCOMMENTS}} and all case variants found in
	// wiki text should be mapped to magic ID 'NUMBEROFCOMMENTS'
	// (0 means case-insensitive)
	$magicWords['NUMBEROFCOMMENTS'] = array( 0, 'NUMBEROFCOMMENTS' );
	return true;
}

$wgHooks['ParserGetVariableValueSwitch'][] = 'wfNumberOfCommentsAssignValue';
function wfNumberOfCommentsAssignValue( &$parser, &$cache, &$magicWordId, &$ret ) {
	global $wgMemc;

	if ( $magicWordId == 'NUMBEROFCOMMENTS' ) {
		$key = wfMemcKey( 'comments', 'magic-word' );
		$data = $wgMemc->get( $key );
		if ( $data != '' ) {
			// We have it in cache? Oh goody, let's just use the cached value!
			wfDebugLog(
				'Comments',
				'Got the amount of comments from memcached'
			);
			// return value
			$ret = $data;
		} else {
			// Not cached → have to fetch it from the database
			$dbr = wfGetDB( DB_SLAVE );
			$commentCount = (int)$dbr->selectField(
				'Comments',
				'COUNT(*) AS count',
				array(),
				__METHOD__
			);
			wfDebugLog( 'Comments', 'Got the amount of comments from DB' );
			// Store the count in cache...
			// (86400 = seconds in a day)
			$wgMemc->set( $key, $commentCount, 86400 );
			// ...and return the value to the user
			$ret = $commentCount;
		}
	}
	return true;
}

$wgHooks['MagicWordwgVariableIDs'][] = 'wfNumberOfCommentsVariableIds';
function wfNumberOfCommentsVariableIds( &$variableIds ) {
	$variableIds[] = 'NUMBEROFCOMMENTS';
	return true;
}