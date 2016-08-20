<?php
/**
 * Comments extension - adds <comments> parserhook to allow commenting on pages
 *
 * @file
 * @ingroup Extensions
 * @author David Pean <david.pean@gmail.com>
 * @author Misza <misza1313[ at ]gmail[ dot ]com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @copyright Copyright © 2008-2015 David Pean, Misza and Jack Phoenix
 * @link https://www.mediawiki.org/wiki/Extension:Comments Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Comments' );
	$wgMessagesDirs['Comments'] =  __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Comments extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the Comments extension requires MediaWiki 1.25+' );
}