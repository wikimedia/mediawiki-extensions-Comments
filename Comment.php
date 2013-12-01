<?php
/**
 * Comments extension - adds <comments> parserhook to allow commenting on pages
 *
 * @file
 * @ingroup Extensions
 * @version 2.8
 * @author David Pean <david.pean@gmail.com>
 * @author Misza <misza1313[ at ]gmail[ dot ]com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2008-2013 David Pean, Misza and Jack Phoenix
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
	'version' => '2.8',
	'author' => array( 'David Pean', 'Misza', 'Jack Phoenix' ),
	'descriptionmsg' => 'comments-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Comments'
);

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.comments'] = array(
	'scripts' => 'Comment.js',
	'styles' => 'Comments.css',
	'messages' => array(
		'comments-voted-label', 'comments-loading',
		'comments-auto-refresher-pause', 'comments-auto-refresher-enable',
		'comments-cancel-reply', 'comments-reply-to',
		'comments-block-warning-anon', 'comments-block-warning-user',
		'comments-delete-warning'
	),
	'localBasePath' => dirname( __FILE__ ),
	'remoteExtPath' => 'Comments',
	'position' => 'top' // available since r85616
);

# Configuration variables
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
$wgExtensionMessagesFiles['CommentsMagic'] = $dir . 'Comments.i18n.magic.php';
$wgAutoloadClasses['Comment'] = $dir . 'CommentClass.php';
$wgAutoloadClasses['CommentIgnoreList'] = $dir . 'SpecialCommentIgnoreList.php';
$wgAutoloadClasses['CommentListGet'] = $dir . 'CommentAction.php';
$wgSpecialPages['CommentIgnoreList'] = 'CommentIgnoreList';
$wgSpecialPages['CommentListGet'] = 'CommentListGet';
// Special page group for MW 1.13+
$wgSpecialPageGroups['CommentIgnoreList'] = 'users';

// Load the AJAX functions required by this extension
require_once( 'Comments_AjaxFunctions.php' );

$wgAutoloadClasses['CommentsLogFormatter'] = $dir . 'CommentsLogFormatter.php';
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
$wgAutoloadClasses['CommentsHooks'] = $dir . 'CommentsHooks.php';
$wgHooks['ParserFirstCallInit'][] = 'CommentsHooks::onParserFirstCallInit';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CommentsHooks::onLoadExtensionSchemaUpdates';
$wgHooks['RenameUserSQL'][] = 'CommentsHooks::onRenameUserSQL';
// Number of comments hooks
$wgHooks['ParserFirstCallInit'][] = 'NumberOfComments::setupNumberOfCommentsPageParser';
$wgHooks['MagicWordwgVariableIDs'][] = 'NumberOfComments::registerNumberOfCommentsMagicWord';
$wgHooks['MagicWordwgVariableIDs'][] = 'NumberOfComments::registerNumberOfCommentsPageMagicWord';
$wgHooks['ParserGetVariableValueSwitch'][] = 'NumberOfComments::getNumberOfCommentsMagic';
$wgHooks['ParserGetVariableValueSwitch'][] = 'NumberOfComments::getNumberOfCommentsPageMagic';

// NumberOfComments magic word setup
$wgAutoloadClasses['NumberOfComments'] = __DIR__ . '/NumberOfComments.php';
$wgExtensionMessagesFiles['NumberOfCommentsMagic'] = __DIR__ . '/Comments.i18n.magic.php';