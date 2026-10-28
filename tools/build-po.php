<?php
/**
 * Build languages/omnigeo-ai-seo-fa_IR.po and .mo.
 *
 * Source of truth is the .po file. On first run it is created from
 * tools/fa_IR.tsv (index<TAB>translation, in POT order). Every run validates
 * that each translation keeps the placeholders and HTML tags of its msgid,
 * then compiles the .mo.
 *
 * Usage: php tools/build-po.php
 */

$lang = dirname( __DIR__ ) . '/omnigeo-ai-seo/languages/';
$pot  = file_get_contents( $lang . 'omnigeo-ai-seo.pot' );

function aigs_po_unescape( $s ) {
	return str_replace( array( '\\n', '\\t', '\\"', '\\\\' ), array( "\n", "\t", '"', '\\' ), $s );
}
function aigs_po_escape( $s ) {
	return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
}

// Parse POT entries (keeps refs and comments for the .po).
preg_match_all( '/((?:^#.*\n)*)^msgid "((?:[^"\\\\]|\\\\.)*)"\n(?:^msgid_plural "((?:[^"\\\\]|\\\\.)*)"\n)?/m', $pot, $m, PREG_SET_ORDER );
$entries = array();
foreach ( $m as $e ) {
	if ( '' === $e[2] ) {
		continue;
	}
	$entries[] = array(
		'head'   => $e[1],
		'msgid'  => aigs_po_unescape( $e[2] ),
		'plural' => isset( $e[3] ) && '' !== $e[3] ? aigs_po_unescape( $e[3] ) : null,
	);
}

// Translations: from existing .po (msgid-keyed) or the TSV seed (index-keyed).
$tr     = array();
$po_src = $lang . 'omnigeo-ai-seo-fa_IR.po';
$tsv    = __DIR__ . '/fa_IR.tsv';
if ( file_exists( $tsv ) ) {
	foreach ( file( $tsv, FILE_IGNORE_NEW_LINES ) as $line ) {
		list( $i, $t ) = explode( "\t", $line, 2 );
		$tr[ $entries[ (int) $i ]['msgid'] ] = str_replace( '\\n', "\n", $t );
	}
} elseif ( file_exists( $po_src ) ) {
	preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"\n(?:^msgid_plural "(?:[^"\\\\]|\\\\.)*"\n)?^msgstr(?:\[0\])? "((?:[^"\\\\]|\\\\.)*)"/m', file_get_contents( $po_src ), $pm, PREG_SET_ORDER );
	foreach ( $pm as $p ) {
		if ( '' !== $p[1] ) {
			$tr[ aigs_po_unescape( $p[1] ) ] = aigs_po_unescape( $p[2] );
		}
	}
}

// Validate placeholders and tags.
$errors  = 0;
$missing = 0;
foreach ( $entries as $e ) {
	if ( ! isset( $tr[ $e['msgid'] ] ) || '' === $tr[ $e['msgid'] ] ) {
		$missing++;
		continue;
	}
	$t = $tr[ $e['msgid'] ];
	preg_match_all( '/%(?:\d+\$)?[sd]|%%/', $e['msgid'], $a );
	preg_match_all( '/%(?:\d+\$)?[sd]|%%/', $t, $b );
	$pa = $a[0];
	$pb = $b[0];
	sort( $pa );
	sort( $pb );
	// Unnumbered placeholders must keep their order; numbered ones may move.
	if ( $pa !== $pb ) {
		echo "PLACEHOLDER MISMATCH: {$e['msgid']}\n  => {$t}\n";
		$errors++;
	}
	preg_match_all( '/<\/?[a-z]+/i', $e['msgid'], $ta );
	preg_match_all( '/<\/?[a-z]+/i', $t, $tb );
	if ( $ta[0] !== $tb[0] ) {
		echo "TAG MISMATCH: {$e['msgid']}\n  => {$t}\n";
		$errors++;
	}
}
if ( $errors ) {
	exit( 1 );
}

// Write .po.
$po  = "# Persian (Iran) translation for OmniGEO Ai SEO.\n# This file is distributed under the GPLv2 or later.\n";
$po .= "msgid \"\"\nmsgstr \"\"\n";
$po .= "\"Project-Id-Version: OmniGEO Ai SEO\\n\"\n\"Language: fa_IR\\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"Plural-Forms: nplurals=1; plural=0;\\n\"\n\"X-Domain: omnigeo-ai-seo\\n\"\n\n";
$mo_entries = array();
foreach ( $entries as $e ) {
	$t   = isset( $tr[ $e['msgid'] ] ) ? $tr[ $e['msgid'] ] : '';
	$po .= $e['head'] . 'msgid ' . aigs_po_escape( $e['msgid'] ) . "\n";
	if ( null !== $e['plural'] ) {
		$po .= 'msgid_plural ' . aigs_po_escape( $e['plural'] ) . "\nmsgstr[0] " . aigs_po_escape( $t ) . "\n\n";
		if ( '' !== $t ) {
			$mo_entries[ $e['msgid'] . "\0" . $e['plural'] ] = $t;
		}
	} else {
		$po .= 'msgstr ' . aigs_po_escape( $t ) . "\n\n";
		if ( '' !== $t ) {
			$mo_entries[ $e['msgid'] ] = $t;
		}
	}
}
file_put_contents( $po_src, $po );

// Compile .mo (GNU format, little endian).
$mo_entries[''] = "Language: fa_IR\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nPlural-Forms: nplurals=1; plural=0;\n";
ksort( $mo_entries, SORT_STRING );
$n       = count( $mo_entries );
$ids     = '';
$strs    = '';
$offsets = array();
foreach ( $mo_entries as $id => $str ) {
	$offsets[] = array( strlen( $id ), strlen( $ids ), strlen( $str ), strlen( $strs ) );
	$ids      .= $id . "\0";
	$strs     .= $str . "\0";
}
$header_size = 28;
$orig_table  = $header_size;
$trans_table = $orig_table + 8 * $n;
$ids_start   = $trans_table + 8 * $n;
$strs_start  = $ids_start + strlen( $ids );
$mo          = pack( 'V7', 0x950412de, 0, $n, $orig_table, $trans_table, 0, $ids_start );
foreach ( $offsets as $o ) {
	$mo .= pack( 'V2', $o[0], $ids_start + $o[1] );
}
foreach ( $offsets as $o ) {
	$mo .= pack( 'V2', $o[2], $strs_start + $o[3] );
}
$mo .= $ids . $strs;
file_put_contents( $lang . 'omnigeo-ai-seo-fa_IR.mo', $mo );

echo count( $entries ) . " strings, {$missing} untranslated, .po and .mo written\n";
