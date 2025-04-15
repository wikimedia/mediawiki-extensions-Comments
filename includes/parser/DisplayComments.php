<?php

use MediaWiki\Html\Html;

class DisplayComments {

	/**
	 * Callback function for onParserFirstCallInit(),
	 * this callback only displays comments section placeholder
	 * the rest is being done via JavaScript by calling an API endpoint
	 *
	 * @param string $input
	 * @param array $args
	 * @param MediaWiki\Parser\Parser $parser
	 *
	 * @return string HTML
	 * @throws MWException
	 */
	public static function getParserHandler( $input, $args, $parser ) {
		global $wgCommentsSortDescending;

		$po = $parser->getOutput();

		// If an unclosed <comments> tag is added to a page, the extension will
		// go to an infinite loop...this protects against that condition.
		$parser->setHook( 'comments', [ __CLASS__, 'nonDisplayComments' ] );

		// Add required CSS & JS via ResourceLoader
		$po->addModuleStyles( [ 'ext.comments.css' ] );
		$po->addModules( [ 'ext.comments.js' ] );
		$po->setJsConfigVar( 'wgCommentsSortDescending', $wgCommentsSortDescending );

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
		if ( preg_match( '/^\s*Allow\s*=\s*(.*)/mi', $input ?? '', $matches ) ) {
			$allow = htmlspecialchars( $matches[1] );
		} elseif ( !empty( $args['allow'] ) ) {
			$allow = $args['allow'];
		}

		$voting = '';
		if ( preg_match( '/^\s*Voting\s*=\s*(.*)/mi', $input ?? '', $matches ) ) {
			$voting = htmlspecialchars( $matches[1] );
		} elseif (
			!empty( $args['voting'] ) &&
			in_array( strtoupper( $args['voting'] ), [ 'OFF', 'PLUS', 'MINUS' ] )
		) {
			$voting = $args['voting'];
		}

		return Html::rawElement(
			'div',
			[
				'class' => 'comments-body',
				'id' => 'comments-body',
				'data-voting' => $voting,
				'data-allow' => $allow
			],
			Html::rawElement(
				'span',
				[
					'class' => 'loader'
				]
			) . 'Loading comments...'
		);
	}

	/**
	 * @param string $input
	 * @param string[] $args
	 * @param MediaWiki\Parser\Parser $parser
	 *
	 * @return string
	 */
	public static function nonDisplayComments( $input, $args, $parser ) {
		$attr = [];

		foreach ( $args as $name => $value ) {
			$attr[] = htmlspecialchars( $name ) . '="' . htmlspecialchars( $value ) . '"';
		}

		$output = '&lt;comments';
		if ( count( $attr ) > 0 ) {
			$output .= ' ' . implode( ' ', $attr );
		}

		if ( $input !== null ) {
			$output .= '&gt;' . htmlspecialchars( $input ) . '&lt;/comments&gt;';
		} else {
			$output .= ' /&gt;';
		}

		return $output;
	}
}
