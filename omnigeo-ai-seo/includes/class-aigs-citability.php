<?php
/**
 * Passage Citability.
 *
 * Answer engines quote one passage, not a whole page. This scores every
 * section (the text under each H2/H3) on five transparent criteria and says
 * what to change:
 *
 *  - stands alone   : does not open with a dangling reference ("This", "As
 *                     mentioned above") and names its subject
 *  - length         : long enough to hold a complete answer, short enough to
 *                     be quoted as one passage
 *  - evidence       : concrete data points (numbers, units, dates) and a
 *                     source (a link or an attribution)
 *  - clarity        : sentences of a readable length
 *  - answer first   : the first sentence answers or defines something
 *
 * Checks that depend on English wording are only applied to English text; for
 * other languages they are "not applicable" and the score is computed from the
 * remaining criteria, never counted as zero.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-passage citability analysis.
 */
class AIGS_Citability {

	/**
	 * Criterion weights (sum 100).
	 */
	const WEIGHTS = array(
		'standalone' => 20,
		'length'     => 20,
		'evidence'   => 30,
		'clarity'    => 15,
		'answer'     => 15,
	);

	/**
	 * Is the text English (enough to apply English-only checks)?
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_english( $text ) {
		$words = preg_split( '/[^a-z\']+/', strtolower( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) < 15 ) {
			return false;
		}
		$common = array_flip( array( 'the', 'and', 'of', 'to', 'a', 'in', 'is', 'that', 'for', 'it', 'with', 'as', 'are', 'on', 'be', 'this', 'by', 'or', 'you', 'can', 'from', 'your' ) );
		$hits   = 0;
		foreach ( $words as $w ) {
			if ( isset( $common[ $w ] ) ) {
				$hits++;
			}
		}
		return ( $hits / count( $words ) ) >= 0.12;
	}

	/**
	 * Split rendered HTML into passages: the content under each H2/H3.
	 *
	 * @param string $html Rendered HTML.
	 * @return array [ heading, text, words, links_out, has_list_or_table ]
	 */
	public static function passages( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return array();
		}
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );
		$home  = AIGS_AI_Client::host( home_url() );

		$out = array();
		$cur = null;
		foreach ( $xpath->query( '//*[self::h2 or self::h3 or self::p or self::ul or self::ol or self::table or self::blockquote]' ) as $n ) {
			$tag = strtolower( $n->nodeName );
			// Paragraphs inside lists, tables or quotes are part of their parent block.
			if ( 'p' === $tag && $n->parentNode && in_array( strtolower( $n->parentNode->nodeName ), array( 'li', 'td', 'th', 'blockquote' ), true ) ) {
				continue;
			}
			if ( in_array( $tag, array( 'ul', 'ol' ), true ) && $n->parentNode && 'li' === strtolower( $n->parentNode->nodeName ) ) {
				continue;
			}
			$text = trim( preg_replace( '/\s+/u', ' ', $n->textContent ) );
			if ( 'h2' === $tag || 'h3' === $tag ) {
				if ( $cur && $cur['words'] > 0 ) {
					$out[] = $cur;
				}
				$cur = array(
					'heading' => $text,
					'text'    => '',
					'words'   => 0,
					'links'   => 0,
					'struct'  => false,
				);
				continue;
			}
			if ( null === $cur || '' === $text ) {
				continue;
			}
			$cur['text']  .= ( '' === $cur['text'] ? '' : ' ' ) . $text;
			$cur['words'] += AIGS_Text::word_count( $text );
			if ( in_array( $tag, array( 'ul', 'ol', 'table' ), true ) ) {
				$cur['struct'] = true;
			}
			foreach ( $n->getElementsByTagName( 'a' ) as $a ) {
				$href = (string) $a->getAttribute( 'href' );
				if ( preg_match( '#^https?://#i', $href ) && ! AIGS_AI_Client::host_matches( $href, $home ) ) {
					$cur['links']++;
				}
			}
		}
		if ( $cur && $cur['words'] > 0 ) {
			$out[] = $cur;
		}
		return $out;
	}

	/**
	 * Concrete data points: percentages, amounts, numbers with units, years, ranges.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function data_points( $text ) {
		$t = AIGS_Text::normalize( $text );
		$n = 0;
		$n += preg_match_all( '/\d+(?:[.,]\d+)?\s?%/u', $t );
		$n += preg_match_all( '/(?:[$€£¥₹]\s?\d|\d[\d.,]*\s?(?:usd|eur|gbp|dollars?|euros?|pounds?))/u', $t );
		$n += preg_match_all( '/\d[\d.,]*\s?(?:kg|g|mg|lb|lbs|oz|km|m|cm|mm|mi|ft|in|l|ml|gb|mb|tb|kb|hz|ghz|mhz|w|kw|kwh|v|mah|°c|°f|ms|sec|seconds?|minutes?|mins?|hours?|hrs?|days?|weeks?|months?|years?|x)(?![\p{L}])/u', $t );
		$n += preg_match_all( '/(?<!\d)(?:19|20)\d{2}(?!\d)/u', $t );
		$n += preg_match_all( '/\d[\d.,]*\s?(?:million|billion|thousand|users|customers|people|times|percent)/u', $t );
		return (int) $n;
	}

	/**
	 * Does the passage name a source (link or attribution phrase)?
	 *
	 * @param array $p       Passage.
	 * @param bool  $english English text.
	 * @return bool
	 */
	private static function has_source( $p, $english ) {
		if ( $p['links'] > 0 ) {
			return true;
		}
		if ( $english ) {
			return (bool) preg_match( '/\b(according to|source:|sources:|study|survey|report(?:ed)? by|research(?:ers)? (?:at|from|by)|data from|published (?:by|in)|found that|shows that)\b/i', $p['text'] );
		}
		return false;
	}

	/**
	 * Score one passage.
	 *
	 * @param array $p       Passage from passages().
	 * @param bool  $english Apply English-only checks.
	 * @return array { score, checks: criterion => [status 2|1|0|null, tip] , tip }
	 */
	public static function score_passage( $p, $english ) {
		$sentences = AIGS_Text::sentences( $p['text'] );
		$first     = isset( $sentences[0] ) ? $sentences[0] : '';
		$c         = array();

		// 1. Stands alone (English only: dangling references are language-specific).
		if ( $english ) {
			$dangling = (bool) preg_match( '/^(this|that|these|those|it|they|he|she|its|their|here|there|such|as (?:mentioned|noted|discussed|we saw|shown|explained)|above|below|the former|the latter|also|however|but|and|so)\b/i', trim( $first ) );
			$overlap  = AIGS_Text::overlap( $p['heading'], $p['text'] ) >= 0.34;
			if ( $dangling ) {
				$c['standalone'] = array( 0, __( 'Starts with a reference to something earlier (“This…”, “As mentioned…”). Name the subject so the passage makes sense on its own.', 'omnigeo-ai-seo' ) );
			} elseif ( ! $overlap ) {
				$c['standalone'] = array( 1, __( 'The text barely repeats the topic of its heading. Name the subject in the first sentence.', 'omnigeo-ai-seo' ) );
			} else {
				$c['standalone'] = array( 2, '' );
			}
		} else {
			$c['standalone'] = array( null, '' );
		}

		// 2. Length: room for a full answer, still quotable as one passage.
		$w = (int) $p['words'];
		if ( $w >= 80 && $w <= 300 ) {
			$c['length'] = array( 2, '' );
		} elseif ( $w >= 40 && $w <= 450 ) {
			$c['length'] = $w < 80
				? array( 1, __( 'Short section. Add the detail a reader needs (how, why, how much) so it fully answers its heading.', 'omnigeo-ai-seo' ) )
				: array( 1, __( 'Long section. Split it under more specific subheadings so each part answers one question.', 'omnigeo-ai-seo' ) );
		} else {
			$c['length'] = $w < 40
				? array( 0, __( 'Too short to quote. Expand it into a complete answer of at least 80 words.', 'omnigeo-ai-seo' ) )
				: array( 0, __( 'Very long section. Break it into several focused sections.', 'omnigeo-ai-seo' ) );
		}

		// 3. Evidence: data points and a source. Strongest effect in published GEO research.
		$dp     = self::data_points( $p['text'] );
		$source = self::has_source( $p, $english );
		if ( $dp >= 2 && $source ) {
			$c['evidence'] = array( 2, '' );
		} elseif ( $dp >= 1 || $source ) {
			$c['evidence'] = array( 1, $source ? __( 'Add a concrete figure (a number, price, percentage, date or measurement).', 'omnigeo-ai-seo' ) : __( 'Add a source: link to or name where the facts come from.', 'omnigeo-ai-seo' ) );
		} else {
			$c['evidence'] = array( 0, __( 'No figures and no source. Add specific numbers and say or link where they come from.', 'omnigeo-ai-seo' ) );
		}

		// 4. Clarity: average sentence length.
		$avg = $sentences ? $w / count( $sentences ) : $w;
		if ( $avg >= 6 && $avg <= 24 ) {
			$c['clarity'] = array( 2, '' );
		} elseif ( $avg <= 32 ) {
			$c['clarity'] = array( 1, __( 'Long sentences. Aim for about 15–20 words per sentence.', 'omnigeo-ai-seo' ) );
		} else {
			$c['clarity'] = array( 0, __( 'Very long sentences make the passage hard to quote. Split them.', 'omnigeo-ai-seo' ) );
		}

		// 5. Answer first: a definition, verdict or figure in the first sentence.
		if ( $english ) {
			$answers = (bool) preg_match( '/\b(is|are|was|means|refers to|lets|helps|allows|costs?|takes|requires|depends on|should|works by|consists of)\b/i', $first ) || self::data_points( $first ) > 0;
			$c['answer'] = $answers ? array( 2, '' ) : array( 1, __( 'Open the section with the answer or a one-sentence definition, then explain.', 'omnigeo-ai-seo' ) );
		} else {
			$c['answer'] = self::data_points( $first ) > 0 || $p['struct'] ? array( 2, '' ) : array( null, '' );
		}

		// Weighted score over applicable criteria only. The tip is the unmet
		// criterion with the worst status; ties go to the heavier criterion.
		$got  = 0;
		$max  = 0;
		$tip  = '';
		$best = null;
		foreach ( self::WEIGHTS as $k => $weight ) {
			if ( null === $c[ $k ][0] ) {
				continue;
			}
			$max += $weight;
			$got += $weight * $c[ $k ][0] / 2;
			if ( $c[ $k ][0] < 2 ) {
				$rank = $c[ $k ][0] * 1000 - $weight;
				if ( null === $best || $rank < $best ) {
					$best = $rank;
					$tip  = $c[ $k ][1];
				}
			}
		}
		return array(
			'score'  => $max ? (int) round( 100 * $got / $max ) : 0,
			'checks' => $c,
			'tip'    => $tip,
			'data'   => $dp,
			'source' => $source,
		);
	}

	/**
	 * Analyse a page.
	 *
	 * @param string $html Rendered HTML.
	 * @return array { score, passages[], weak, evidence_points, sourced, english }
	 */
	public static function analyze( $html ) {
		$passages = self::passages( $html );
		$all      = '';
		foreach ( $passages as $p ) {
			$all .= ' ' . $p['text'];
		}
		$english = self::is_english( $all );
		$rows    = array();
		$sum     = 0;
		$weights = 0;
		$weak    = 0;
		$points  = 0;
		$sourced = 0;
		foreach ( $passages as $p ) {
			$s       = self::score_passage( $p, $english );
			$rows[]  = array(
				'heading' => $p['heading'],
				'words'   => (int) $p['words'],
				'score'   => $s['score'],
				'tip'     => $s['tip'],
			);
			// Longer passages weigh more in the page score (capped so one wall of text does not dominate).
			$w        = min( 300, max( 40, (int) $p['words'] ) );
			$sum     += $s['score'] * $w;
			$weights += $w;
			$points  += $s['data'];
			$sourced += $s['source'] ? 1 : 0;
			if ( $s['score'] < 50 ) {
				$weak++;
			}
		}
		return array(
			'score'           => $weights ? (int) round( $sum / $weights ) : 0,
			'passages'        => $rows,
			'weak'            => $weak,
			'evidence_points' => $points,
			'sourced'         => $sourced,
			'english'         => $english,
		);
	}
}
