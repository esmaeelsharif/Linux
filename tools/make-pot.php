<?php
/**
 * Regenerate languages/omnigeo-ai-seo.pot from the plugin source.
 * Usage: php tools/make-pot.php
 *
 * Handles __, _e, esc_html__, esc_html_e, esc_attr__, esc_attr_e, _n and
 * keeps "translators:" comments, like wp i18n make-pot.
 */

$root  = dirname( __DIR__ ) . '/omnigeo-ai-seo/';
$files = array_merge( glob( $root . '*.php' ), glob( $root . 'includes/*.php' ) );
$single = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
$entries = array(); // key => [msgid, plural|null, refs[], comments[]]

function aigs_unquote( $tok ) {
	$q = $tok[0];
	$s = substr( $tok, 1, -1 );
	if ( "'" === $q ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $s );
	}
	return stripcslashes( $s );
}

foreach ( $files as $file ) {
	$rel    = substr( $file, strlen( $root ) );
	$tokens = token_get_all( file_get_contents( $file ) );
	$last_comment = '';
	$last_comment_line = -10;
	$n = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) && false !== stripos( $t[1], 'translators:' ) ) {
			$c = trim( preg_replace( '#^/\*+|\*+/$|^//#', '', trim( $t[1] ) ) );
			$last_comment      = trim( preg_replace( '/\s+/', ' ', $c ) );
			$last_comment_line = $t[2];
			continue;
		}
		if ( ! is_array( $t ) || T_STRING !== $t[0] ) {
			continue;
		}
		$fn = $t[1];
		if ( ! in_array( $fn, $single, true ) && '_n' !== $fn ) {
			continue;
		}
		// Collect string literal args at depth 1.
		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) { $j++; }
		if ( '(' !== $tokens[ $j ] ) { continue; }
		$args = array();
		$cur  = null;
		$depth = 0;
		for ( $k = $j; $k < $n; $k++ ) {
			$tk = $tokens[ $k ];
			if ( '(' === $tk ) { $depth++; if ( 1 === $depth ) { $cur = ''; continue; } }
			if ( ')' === $tk ) { $depth--; if ( 0 === $depth ) { $args[] = $cur; break; } }
			if ( ',' === $tk && 1 === $depth ) { $args[] = $cur; $cur = ''; continue; }
			if ( 1 === $depth ) {
				if ( is_array( $tk ) && T_CONSTANT_ENCAPSED_STRING === $tk[0] && '' === trim( (string) $cur ) ) {
					$cur = array( aigs_unquote( $tk[1] ) );
				} elseif ( is_array( $tk ) && T_WHITESPACE === $tk[0] ) {
					// ignore
				} elseif ( is_array( $cur ) ) {
					$cur = '#complex';
				} else {
					$cur .= is_array( $tk ) ? $tk[1] : $tk;
				}
			}
		}
		$msgid  = isset( $args[0] ) && is_array( $args[0] ) ? $args[0][0] : null;
		$plural = null;
		if ( '_n' === $fn ) {
			$plural = isset( $args[1] ) && is_array( $args[1] ) ? $args[1][0] : null;
		}
		if ( null === $msgid ) { continue; }
		$key = $msgid . "\0" . (string) $plural;
		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array( $msgid, $plural, array(), array() );
		}
		$entries[ $key ][2][] = $rel . ':' . $t[2];
		if ( '' !== $last_comment && $t[2] - $last_comment_line <= 3 ) {
			$entries[ $key ][3][ $last_comment ] = true;
		}
	}
}

function aigs_po_str( $s ) {
	$s = str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s );
	return '"' . $s . '"';
}

ksort( $entries );
$plugin = file_get_contents( $root . 'omnigeo-ai-seo.php' );
preg_match( '/Version:\s*([0-9.]+)/', $plugin, $vm );
$out  = "# Copyright (C) " . gmdate( 'Y' ) . " Deepwp\n# This file is distributed under the GPLv2 or later.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: OmniGEO Ai SEO {$vm[1]}\\n\"\n";
$out .= "\"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/omnigeo-ai-seo\\n\"\n";
$out .= "\"Last-Translator: \\n\"\n\"Language-Team: \\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i+0000' ) . "\\n\"\n\"X-Domain: omnigeo-ai-seo\\n\"\n\"X-Generator: tools/make-pot.php\\n\"\n\n";
foreach ( $entries as $e ) {
	foreach ( array_keys( $e[3] ) as $c ) {
		$out .= '#. ' . $c . "\n";
	}
	$out .= '#: ' . implode( ' ', array_slice( array_unique( $e[2] ), 0, 6 ) ) . "\n";
	if ( preg_match( '/%(\d+\$)?[sd]/', $e[0] ) ) {
		$out .= "#, php-format\n";
	}
	$out .= 'msgid ' . aigs_po_str( $e[0] ) . "\n";
	if ( null !== $e[1] ) {
		$out .= 'msgid_plural ' . aigs_po_str( $e[1] ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$out .= "msgstr \"\"\n\n";
	}
}
file_put_contents( $root . 'languages/omnigeo-ai-seo.pot', $out );
echo count( $entries ) . " strings\n";
