<?php
// Print POT msgids as "index<TAB>msgid" (plural shown as msgid || plural).
$pot = file_get_contents( dirname( __DIR__ ) . '/omnigeo-ai-seo/languages/omnigeo-ai-seo.pot' );
preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"\n(?:msgid_plural "((?:[^"\\\\]|\\\\.)*)"\n)?/m', $pot, $m, PREG_SET_ORDER );
$i = 0;
foreach ( $m as $e ) {
	if ( '' === $e[1] ) { continue; }
	echo $i++ . "\t" . $e[1] . ( isset( $e[2] ) && '' !== $e[2] ? ' || ' . $e[2] : '' ) . "\n";
}
