<?php
/**
 * Comments extension - adds <comments> parserhook to allow commenting on pages
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com>
 * @author Misza <misza1313[ at ]gmail[ dot ]com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2008-2014 David Pean, Misza and Jack Phoenix
 * @link https://www.mediawiki.org/wiki/Extension:Comments Documentation
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
	'path' => __FILE__,
	'name' => 'Comments',
	'version' => '4.0.2',
	'author' => array( 'David Pean', 'Misza', 'Jack Phoenix', 'Adam Carter/UltrasonicNXT' ),
	'descriptionmsg' => 'comments-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Comments'
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.comments.css'] = array(
	'styles' => 'Comments.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Comments',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.comments.js'] = array(
	'scripts' => 'Comment.js',
	'messages' => array(
		'comments-voted-label', 'comments-loading',
		'comments-auto-refresher-pause', 'comments-auto-refresher-enable',
		'comments-cancel-reply', 'comments-reply-to',
		'comments-block-warning-anon', 'comments-block-warning-user',
		'comments-delete-warning'
	),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Comments'
);

# Configuration variables
// Path to an image which will be displayed instead of an avatar if social tools aren't installed.
// Should be 50x50px
$wgCommentsDefaultAvatar = 'http://www.shoutwiki.com/w/extensions/SocialProfile/avatars/default_ml.gif';
$wgCommentsSortDescending = false;

// New user rights
$wgAvailableRights[] = 'comment';
$wgAvailableRights[] = 'commentadmin';
$wgAvailableRights[] = 'commentlinks';
// Allows everyone, including unregistered users, to comment
$wgGroupPermissions['*']['comment'] = true;
// Allows users in the commentadmin group to administrate comments (incl. comment deletion)
$wgGroupPermissions['commentadmin']['commentadmin'] = true;
// Allows autoconfirmed users to use external links in comments
$wgGroupPermissions['autoconfirmed']['commentlinks'] = true;

// Set up the new special pages
$dir = __DIR__ . '/';
$wgMessagesDirs['Comments'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CommentsMagic'] = __DIR__ . '/Comments.i18n.magic.php';
$wgAutoloadClasses['Comment'] = __DIR__ . '/CommentClass.php';
$wgAutoloadClasses['CommentsPage'] = __DIR__ . '/CommentsPage.php';
$wgAutoloadClasses['CommentFunctions'] = __DIR__ . '/CommentFunctions.php';
$wgAutoloadClasses['CommentIgnoreList'] = __DIR__ . '/SpecialCommentIgnoreList.php';
$wgSpecialPages['CommentIgnoreList'] = 'CommentIgnoreList';

$wgAutoloadClasses['CommentsLogFormatter'] = __DIR__ . '/CommentsLogFormatter.php';
// Add a new log type
$wgLogTypes[] = 'comments';
// Default log formatter doesn't support wikilinks (?!?) so we have to have
// our own formatter here :-(
$wgLogActionsHandlers['comments/add'] = 'CommentsLogFormatter';
// For the delete action, we don't need nor /want/ the fragment in the page link,
// because the fragment points to the comment we just deleted! Hence we can use
// core LogFormatter as-is instead of our custom class. Fun!
$wgLogActionsHandlers['comments/delete'] = 'LogFormatter';
// This hides comment log entries from Special:Log, much like how patrol stuff
// is hidden by default, but can be enabled via a link
$wgFilterLogTypes['comments'] = true;
// Show comments in Special:RecentChanges?
$wgCommentsInRecentChanges = false;

// Hooked functions
$wgAutoloadClasses['CommentsHooks'] = __DIR__ . '/CommentsHooks.php';
$wgHooks['ParserFirstCallInit'][] = 'CommentsHooks::onParserFirstCallInit';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CommentsHooks::onLoadExtensionSchemaUpdates';
$wgHooks['RenameUserSQL'][] = 'CommentsHooks::onRenameUserSQL';
// Number of comments hooks
$wgHooks['ParserFirstCallInit'][] = 'NumberOfComments::setupNumberOfCommentsPageParser';
$wgHooks['MagicWordwgVariableIDs'][] = 'NumberOfComments::registerNumberOfCommentsMagicWord';
$wgHooks['ParserGetVariableValueSwitch'][] = 'NumberOfComments::getNumberOfCommentsMagic';

// NumberOfComments magic word setup
$wgAutoloadClasses['NumberOfComments'] = __DIR__ . '/NumberOfComments.php';
$wgExtensionMessagesFiles['NumberOfCommentsMagic'] = __DIR__ . '/Comments.i18n.magic.php';

// API
$wgAutoloadClasses['CommentBlockAPI'] = __DIR__ . '/api/CommentBlock.api.php';
$wgAutoloadClasses['CommentDeleteAPI'] = __DIR__ . '/api/CommentDelete.api.php';
$wgAutoloadClasses['CommentLatestIdAPI'] = __DIR__ . '/api/CommentLatestID.api.php';
$wgAutoloadClasses['CommentListAPI'] = __DIR__ . '/api/CommentList.api.php';
$wgAutoloadClasses['CommentSubmitAPI'] = __DIR__ . '/api/CommentSubmit.api.php';
$wgAutoloadClasses['CommentVoteAPI'] = __DIR__ . '/api/CommentVote.api.php';
$wgAPIModules['commentblock'] = 'CommentBlockAPI';
$wgAPIModules['commentdelete'] = 'CommentDeleteAPI';
$wgAPIModules['commentlatestid'] = 'CommentLatestIdAPI';
$wgAPIModules['commentlist'] = 'CommentListAPI';
$wgAPIModules['commentsubmit'] = 'CommentSubmitAPI';
$wgAPIModules['commentvote'] = 'CommentVoteAPI';
