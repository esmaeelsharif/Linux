<?php
/**
 * Multilingual text helpers.
 *
 * Normalizes Persian/Arabic script variants (ي/ی, ك/ک, ZWNJ, digits, diacritics)
 * so matching, word counting and question detection behave the same whether
 * content is written in Persian, Arabic or a Latin language.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless text utilities shared by every module.
 */
class AIGS_Text {

	/**
	 * Character map applied before any comparison.
	 *
	 * @return array
	 */
	private static function char_map() {
		return array(
			'ي' => 'ی',
			'ى' => 'ی',
			'ك' => 'ک',
			'ة' => 'ه',
			'أ' => 'ا',
			'إ' => 'ا',
			'ٱ' => 'ا',
			'ؤ' => 'و',
			'ۀ' => 'ه',
			'۰' => '0',
			'۱' => '1',
			'۲' => '2',
			'۳' => '3',
			'۴' => '4',
			'۵' => '5',
			'۶' => '6',
			'۷' => '7',
			'۸' => '8',
			'۹' => '9',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		);
	}

	/**
	 * Normalize text for comparison: unify script variants, drop diacritics and
	 * tatweel, turn ZWNJ into a space, lowercase and collapse whitespace.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = strtr( $text, self::char_map() );
		// Arabic diacritics (harakat), superscript alef and tatweel.
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text );
		// ZWNJ / ZWJ / soft hyphen become a plain separator.
		$text = preg_replace( '/[\x{200C}\x{200D}\x{00AD}]/u', ' ', $text );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Does the text contain Arabic-script letters (Persian, Arabic, Urdu)?
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_rtl_script( $text ) {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $text );
	}

	/**
	 * Multibyte-aware word count (str_word_count is Latin-only).
	 *
	 * @param string $text Text or HTML.
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $text ) {
			return 0;
		}
		$text  = preg_replace( '/[\x{200C}\x{200D}]/u', '', $text );
		$parts = preg_split( '/[\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $parts as $p ) {
			// Ignore pure punctuation tokens such as "-" or "،".
			if ( preg_match( '/[\p{L}\p{N}]/u', $p ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Build a regex that finds a term with flexible separators and Persian
	 * suffixes, bounded so "Ana" does not match inside "banana".
	 *
	 * @param string $term Term.
	 * @return string Empty when the term is unusable.
	 */
	public static function term_regex( $term ) {
		$term = self::normalize( $term );
		if ( '' === $term ) {
			return '';
		}
		$tokens = preg_split( '/[\s\-_]+/u', $term, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $tokens ) {
			return '';
		}
		$quoted = array();
		foreach ( $tokens as $t ) {
			$quoted[] = preg_quote( $t, '/' );
		}
		$body   = implode( '[\s\-_.]*', $quoted );
		$suffix = self::is_rtl_script( $term ) ? '(?:\s?(?:ها|های|ی|ای|اش|ش|یم|تان))?' : '(?:\'s|’s|s)?';
		return '/(?<![\p{L}\p{N}])' . $body . $suffix . '(?![\p{L}\p{N}])/u';
	}

	/**
	 * Does the haystack contain the term (normalized, word-bounded)?
	 *
	 * @param string $haystack Text.
	 * @param string $term     Term.
	 * @return bool
	 */
	public static function contains_term( $haystack, $term ) {
		$re = self::term_regex( $term );
		if ( '' === $re ) {
			return false;
		}
		return (bool) preg_match( $re, self::normalize( $haystack ) );
	}

	/**
	 * Offset (in characters of the normalized text) of the first match, or -1.
	 *
	 * @param string $haystack Text.
	 * @param string $term     Term.
	 * @return int
	 */
	public static function term_offset( $haystack, $term ) {
		$re = self::term_regex( $term );
		if ( '' === $re ) {
			return -1;
		}
		$norm = self::normalize( $haystack );
		if ( preg_match( $re, $norm, $m, PREG_OFFSET_CAPTURE ) ) {
			return function_exists( 'mb_strlen' ) ? mb_strlen( substr( $norm, 0, $m[0][1] ), 'UTF-8' ) : $m[0][1];
		}
		return -1;
	}

	/**
	 * Common stop words (English + Persian) ignored by topical overlap.
	 *
	 * @return array
	 */
	private static function stop_words() {
		return array_flip(
			array(
				'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'is', 'are', 'was', 'be', 'it', 'its', 'this', 'that', 'as', 'at', 'by', 'from', 'how', 'what', 'why', 'which', 'who', 'when', 'where', 'do', 'does', 'can', 'should', 'your', 'you', 'i', 'we', 'our', 'my', 'vs', 'best', 'top', 'guide', 'complete',
				'و', 'در', 'به', 'از', 'که', 'این', 'آن', 'با', 'برای', 'را', 'است', 'هست', 'یک', 'تا', 'بر', 'هم', 'یا', 'اما', 'چه', 'چی', 'چطور', 'چگونه', 'چرا', 'کدام', 'آیا', 'چیست', 'چیه', 'کجا', 'کی', 'چقدر', 'چند', 'می', 'شود', 'کرد', 'کنیم', 'کنید', 'بهترین', 'راهنما', 'کامل', 'ها', 'های', 'ای', 'نیز', 'خود', 'شما', 'ما',
			)
		);
	}

	/**
	 * Meaningful tokens of a text.
	 *
	 * @param string $text Text.
	 * @return array Unique tokens.
	 */
	public static function tokens( $text ) {
		$norm  = self::normalize( $text );
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$stop = self::stop_words();
		$out  = array();
		foreach ( $parts as $p ) {
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $p, 'UTF-8' ) : strlen( $p );
			if ( $len < 2 || isset( $stop[ $p ] ) ) {
				continue;
			}
			$out[ $p ] = true;
		}
		return array_keys( $out );
	}

	/**
	 * Share (0-1) of the needle's tokens that appear in the haystack.
	 * A token counts when it matches exactly or as a prefix (e.g. "حسابداری" ~ "حسابدار").
	 *
	 * @param string $needle   Question / topic.
	 * @param string $haystack Text that should answer it.
	 * @return float
	 */
	public static function overlap( $needle, $haystack ) {
		$a = self::tokens( $needle );
		if ( ! $a ) {
			return 0.0;
		}
		$b    = self::tokens( $haystack );
		$bmap = array_flip( $b );
		$hit  = 0;
		foreach ( $a as $t ) {
			if ( isset( $bmap[ $t ] ) ) {
				$hit++;
				continue;
			}
			$stem = function_exists( 'mb_substr' ) ? mb_substr( $t, 0, 5, 'UTF-8' ) : substr( $t, 0, 5 );
			if ( mb_strlen( $stem, 'UTF-8' ) >= 4 ) {
				foreach ( $b as $u ) {
					if ( 0 === strpos( $u, $stem ) ) {
						$hit++;
						break;
					}
				}
			}
		}
		return $hit / count( $a );
	}

	/**
	 * Is the text phrased as a question (English or Persian)?
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function is_question( $text ) {
		$raw = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $raw ) {
			return false;
		}
		if ( preg_match( '/[?؟]\s*$/u', $raw ) ) {
			return true;
		}
		$t = self::normalize( $raw );
		if ( preg_match( '/^(what|how|why|when|where|which|who|whose|is|are|can|could|does|do|did|should|will|would)\b/u', $t ) ) {
			return true;
		}
		if ( preg_match( '/^(چه|چطور|چگونه|چرا|کدام|کی|کجا|آیا|چقدر|چند|چی)(?![\p{L}])/u', $t ) ) {
			return true;
		}
		return (bool) preg_match( '/(چیست|چیه|چطوره|کدامند|چگونه است|یعنی چه)\s*$/u', $t );
	}

	/**
	 * Does the text open with filler instead of an answer
	 * ("In this article…", "در این مقاله…")?
	 *
	 * @param string $text Opening paragraph.
	 * @return bool
	 */
	public static function is_filler_opening( $text ) {
		$t = self::normalize( $text );
		$patterns = array(
			'/^(in this (article|post|guide|blog)|today we|welcome to|have you ever|are you looking|in today\'s|let\'s (talk|dive)|before we (start|begin))/u',
			'/^(در این (مقاله|پست|مطلب|نوشته|بخش|راهنما)|امروز (می خواهیم|قصد داریم|میخواهیم)|با (ما )?همراه باشید|سلام|آیا تا به حال|حتما برای شما هم|قبل از (هر چیز|شروع))/u',
		);
		foreach ( $patterns as $re ) {
			if ( preg_match( $re, $t ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Split text into sentences (handles ., !, ?, ؟ and Persian full stop).
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function sentences( $text ) {
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		$parts = preg_split( '/(?<=[.!?؟۔])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Trim text to a word budget on a sentence boundary when possible.
	 *
	 * @param string $text  Text.
	 * @param int    $words Max words.
	 * @return string
	 */
	public static function excerpt_sentences( $text, $words = 50 ) {
		$out = '';
		foreach ( self::sentences( $text ) as $s ) {
			$next = trim( $out . ' ' . $s );
			if ( '' !== $out && self::word_count( $next ) > $words ) {
				break;
			}
			$out = $next;
			if ( self::word_count( $out ) >= $words ) {
				break;
			}
		}
		if ( self::word_count( $out ) > $words ) {
			$out = wp_trim_words( $out, $words, '…' );
		}
		return $out;
	}

	/**
	 * Current year in both calendars (Gregorian + Solar Hijri).
	 *
	 * @return array{0:int,1:int}
	 */
	public static function current_years() {
		$ts    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local calendar year is wanted.
		$greg  = (int) gmdate( 'Y', $ts );
		$doy   = (int) gmdate( 'z', $ts );
		$jalal = ( $doy >= 79 ) ? $greg - 621 : $greg - 622; // Nowruz ≈ 20/21 March.
		return array( $greg, $jalal );
	}

	/**
	 * Find an outdated year in a title ("Best laptops 2023", "قیمت ۱۴۰۱").
	 *
	 * @param string $title Title.
	 * @return int The outdated year, or 0.
	 */
	public static function outdated_year( $title ) {
		$t = self::normalize( $title );
		list( $greg, $jalal ) = self::current_years();
		if ( preg_match_all( '/(?<!\d)(19\d{2}|20\d{2}|13\d{2}|14\d{2})(?!\d)/u', $t, $m ) ) {
			foreach ( $m[1] as $y ) {
				$y = (int) $y;
				if ( $y >= 1990 && $y < $greg && $y > $greg - 15 ) {
					return $y;
				}
				if ( $y >= 1380 && $y < 1500 && $y < $jalal && $y > $jalal - 15 ) {
					return $y;
				}
			}
		}
		return 0;
	}
}
