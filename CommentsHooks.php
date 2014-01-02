<?php
/**
 * Hooked functions used by the Comments extension.
 * All class methods are public and static.
 *
 * @file
 * @ingroup Extensions
 * @author Jack Phoenix <jack@countervandalism.net>
 * @author Alexia E. Smith
 * @copyright (c) 2013 Curse Inc.
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:Comments Documentation
 */

class CommentsHooks {
	/**
	 * Registers the <comments> tag with the Parser.
	 *
	 * @param $parser Parser
	 * @return Boolean
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'comments', array( 'CommentsHooks', 'displayComments' ) );
		return true;
	}

	/**
	 * Callback function for onParserFirstCallInit().
	 *
	 * @param $input
	 * @param $args Array
	 * @param $parser Parser
	 * @return String: HTML
	 */
	public static function displayComments( $input, $args, $parser ) {
		global $wgOut;

		wfProfileIn( __METHOD__ );

		$parser->disableCache();
		// If an unclosed <comments> tag is added to a page, the extension will
		// go to an infinite loop...this protects against that condition.
		$parser->setHook( 'comments', array( 'CommentsHooks', 'nonDisplayComments' ) );

		$title = $parser->getTitle();
		if ( $title->getArticleID() == 0 && $title->getDBkey() == 'CommentListGet' ) {
			return self::nonDisplayComments( $input, $args, $parser );
		}

		// Add required CSS & JS via ResourceLoader
		$wgOut->addModules( 'ext.comments' );

		// Parse arguments
		// The preg_match() lines here are to support the old-style way of
		// adding arguments:
		// <comments>
		// Allow=Foo,Bar
		// Voting=Plus
		// </comments>
		// whereas the normal, standard MediaWiki style, which this extension
		// also supports is: <comments allow="Foo,Bar" voting="Plus" />
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

		$comment = new Comment( $wgOut->getTitle()->getArticleID() );
		$comment->setAllow( $allow );
		$comment->setVoting( $voting );

		$output = '<div class="comments-body">';
		// This was originally commented out, I don't know why.
		// Uncommented to prevent E_NOTICE.
		$output .= $comment->displayOrderForm();

		$output .= '<div id="allcomments">' . $comment->display() . '</div>';

		// If the database is in read-only mode, display a message informing the
		// user about that, otherwise allow them to comment
		if( !wfReadOnly() ) {
			$output .= $comment->displayForm();
		} else {
			$output .= wfMessage( 'comments-db-locked' )->parse();
		}

		$output .= '</div>'; // div.comments-body

		wfProfileOut( __METHOD__ );

		return $output;
	}

	public static function nonDisplayComments( $input, $args, $parser ) {
		$attr = array();

		foreach ( $args as $name => $value ) {
			$attr[] = htmlspecialchars( $name ) . '="' . htmlspecialchars( $value ) . '"';
		}

		$output = '&lt;comments';
		if ( count( $attr ) > 0 ) {
			$output .= ' ' . implode( ' ', $attr );
		}

		if ( !is_null( $input ) ) {
			$output .= '&gt;' . htmlspecialchars( $input ) . '&lt;/comments&gt;';
		} else {
			$output .= ' /&gt;';
		}

		return $output;
	}

	/**
	 * Adds the three new required database tables into the database when the
	 * user runs /maintenance/update.php (the core database updater script).
	 *
	 * @param $updater DatabaseUpdater
	 * @return Boolean
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __FILE__ ) . '/';

		$dbType = $updater->getDB()->getType();
		// For non-MySQL/MariaDB/SQLite DBMSes, use the appropriately named file
		if ( !in_array( $dbType, array( 'mysql', 'sqlite' ) ) ) {
			$filename = "comments.{$dbType}.sql";
		} else {
			$filename = 'comments.sql';
		}

		$updater->addExtensionUpdate( array( 'addTable', 'Comments', "{$dir}/{$filename}", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'Comments_Vote', "{$dir}/{$filename}", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'Comments_block', "{$dir}/{$filename}", true ) );

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param $renameUserSQL Array
	 * @return Boolean
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		$renameUserSQL->tables['Comments'] = array( 'Comment_Username', 'Comment_user_id' );
		$renameUserSQL->tables['Comments_Vote'] = array( 'Comment_Vote_Username', 'Comment_Vote_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name', 'cb_user_id' );
		$renameUserSQL->tables['Comments_block'] = array( 'cb_user_name_blocked', 'cb_user_id_blocked' );
		return true;
	}
}