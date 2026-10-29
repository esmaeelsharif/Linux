<?php
/**
 * Content Extractability analyzer.
 *
 * Grades a post's BODY content for how easily an LLM can extract and cite it:
 * answer-first openings, semantic chunking, self-contained sections,
 * descriptive headings, lists, comparison tables and key-claim emphasis.
 *
 * Word counting is multibyte-aware so Persian/Arabic/CJK content is measured
 * correctly (PHP's str_word_count is Latin-only).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-post extractability scoring shown in the editor.
 */
class AIGS_Extract {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'wp_ajax_aigs_extract', array( $this, 'ajax_extract' ) );
		add_action( 'admin_init', array( $this, 'setup_columns' ) );
		add_action( 'pre_get_posts', aigs_guard( array( $this, 'sort_query' ) ) );
	}

	/**
	 * Add the score column + sortable + renderer to every public post type list table.
	 */
	public function setup_columns() {
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$pt}_sortable_columns", array( $this, 'sortable_column' ) );
		}
	}

	/**
	 * Insert the AI Score column right after the title.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function add_column( $cols ) {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['aigs_score'] = __( 'AI Score', 'omnigeo-ai-seo' );
			}
		}
		if ( ! isset( $out['aigs_score'] ) ) {
			$out['aigs_score'] = __( 'AI Score', 'omnigeo-ai-seo' );
		}
		return $out;
	}

	/**
	 * Mark the column sortable.
	 *
	 * @param array $cols Sortable columns.
	 * @return array
	 */
	public function sortable_column( $cols ) {
		$cols['aigs_score'] = 'aigs_score';
		return $cols;
	}

	/**
	 * Render the score badge in the list table.
	 *
	 * @param string $col     Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $col, $post_id ) {
		if ( 'aigs_score' !== $col ) {
			return;
		}
		$score = get_post_meta( $post_id, '_aigs_extract_score', true );
		if ( '' === $score ) {
			$score = get_post_meta( $post_id, '_aigs_page_score', true );
		}
		if ( '' === $score ) {
			echo '<span style="color:#9a9aa0" title="' . esc_attr__( 'Not analyzed yet. Open the editor or run a site scan.', 'omnigeo-ai-seo' ) . '">—</span>';
			return;
		}
		$score = (int) $score;
		$color = $score >= 75 ? '#18794e' : ( $score >= 50 ? '#9a6700' : '#c4332e' );
		printf(
			'<span style="display:inline-block;min-width:32px;text-align:center;padding:3px 9px;border-radius:999px;font-weight:600;font-size:12px;color:#fff;background:%1$s" title="%2$s">%3$d</span>',
			esc_attr( $color ),
			esc_attr__( 'AI content extractability score', 'omnigeo-ai-seo' ),
			(int) $score
		);
	}

	/**
	 * Order the list table by score when requested (includes posts without a score).
	 *
	 * @param WP_Query $query Query.
	 */
	public function sort_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'aigs_score' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set(
			'meta_query',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_aigs_extract_score',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_aigs_extract_score',
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Register the editor meta box.
	 */
	public function add_meta_box() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $types as $type ) {
			add_meta_box(
				'aigs_extract',
				__( 'OmniGEO Content Extractability', 'omnigeo-ai-seo' ),
				array( $this, 'render_box' ),
				$type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the meta box shell (results load via AJAX).
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_box( $post ) {
		$score = get_post_meta( $post->ID, '_aigs_extract_score', true );
		echo '<div class="aigs-app aigs-app--box">';
		echo '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">';
		echo '<button type="button" class="aigs-btn aigs-btn--primary" id="aigs-extract-run" data-post="' . (int) $post->ID . '">' . AIGS_Admin::icon( 'radar', 16 ) . esc_html__( 'Analyze content', 'omnigeo-ai-seo' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label style="font-size:12.5px;color:#5f5f63;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" id="aigs-extract-auto" checked /> ' . esc_html__( 'Auto-update as I edit', 'omnigeo-ai-seo' ) . '</label>';
		echo '</div>';
		echo '<p class="aigs-muted" style="font-size:12.5px;margin:9px 0 0">' . esc_html__( 'Analyzes the live editor content, no save required.', 'omnigeo-ai-seo' ) . '</p>';
		echo '<div id="aigs-answer-panel" class="aigs-answer" data-ai="' . ( AIGS_AI_Client::ready() ? '1' : '0' ) . '" style="margin-top:14px"></div>';
		echo '<div id="aigs-extract-result" style="margin-top:14px">';
		if ( '' !== $score ) {
			/* translators: %d is a value inserted into the message. */
			echo '<p class="aigs-muted" style="font-size:12.5px">' . sprintf( esc_html__( 'Last analysis score: %d / 100', 'omnigeo-ai-seo' ), (int) $score ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * AJAX: analyze a post.
	 */
	public function ajax_extract() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}

		if ( isset( $_POST['content'] ) ) {
			// Sanitize the editor HTML immediately (sanitize early), then render for
			// read-only analysis. The result is measured, never echoed back to the page.
			$raw   = wp_kses_post( wp_unslash( $_POST['content'] ) );
			$html  = do_shortcode( do_blocks( $raw ) );
			$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : get_the_title( $post_id );
		} else {
			global $post;
			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( array( 'message' => __( 'Post not found.', 'omnigeo-ai-seo' ) ) );
			}
			setup_postdata( $post );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally applying WordPress core the_content filter.
			$html = apply_filters( 'the_content', $post->post_content );
			wp_reset_postdata();
			$title = get_the_title( $post_id );
		}

		$keyword = class_exists( 'AIGS_SEO' ) ? AIGS_SEO::focus_keyword( $post_id ) : '';
		$report  = self::analyze( $html, $title, $keyword );

		// Answer Optimizer: what question is this page for, and is it answered?
		$answer = AIGS_Answer::analyze( AIGS_Answer::structure( $html ), $title, $keyword );
		if ( ! AIGS_WooCommerce::is_product_id( $post_id ) && count( $report['checks'] ) > 1 ) {
			$report['checks'] = array_merge( AIGS_Answer::check_rows( $answer ), $report['checks'] );
			$report['score']  = self::score_from_checks( $report['checks'] );
			$report['grade']  = self::grade( $report['score'] );
		}
		$report['answer'] = $answer;
		$report['open']   = array();
		foreach ( AIGS_Findings::query( array( 'post_id' => $post_id, 'limit' => 30 ) ) as $f ) {
			$m = AIGS_Rules::meta( $f['rule'] );
			if ( $m ) {
				$report['open'][] = array(
					'title' => $m['title'],
					'sev'   => (int) $f['severity'],
				);
			}
		}

		// Merge WooCommerce product-specific checks when analyzing a product.
		if ( class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::is_product_id( $post_id ) ) {
			$woo   = new AIGS_WooCommerce();
			$extra = $woo->product_checks( $post_id );
			if ( $extra ) {
				$base = $report['checks'];
				// Drop the lone "no content" placeholder so an empty long description isn't double-counted.
				if ( 1 === count( $base ) ) {
					$base = array();
				}
				$report['checks'] = array_merge( $base, $extra );
				$report['score']  = self::score_from_checks( $report['checks'] );
				$report['grade']  = self::grade( $report['score'] );
			}
		}

		update_post_meta( $post_id, '_aigs_extract_score', $report['score'] );
		wp_send_json_success( $report );
	}

	/**
	 * Analyze HTML content.
	 *
	 * @param string $html  Rendered post HTML.
	 * @param string $title Post title (for intent detection).
	 * @return array
	 */
	public static function analyze( $html, $title = '', $keyword = '' ) {
		if ( '' === trim( (string) $html ) ) {
			return array(
				'score'  => 0,
				'grade'  => 'F',
				'url'    => $title,
				'checks' => array( self::row( __( 'No content', 'omnigeo-ai-seo' ), 0, __( 'This post has no body content to analyze.', 'omnigeo-ai-seo' ), 1 ) ),
			);
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		$flow = $xpath->query( '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::p or self::ul or self::ol or self::table or self::blockquote]' );
		$seq  = array();
		foreach ( $flow as $n ) {
			$seq[] = array(
				'tag'  => strtolower( $n->nodeName ),
				'text' => trim( preg_replace( '/\s+/u', ' ', $n->textContent ) ),
			);
		}

		$h2     = $xpath->query( '//h2' );
		$h3     = $xpath->query( '//h3' );
		$tables = $xpath->query( '//table' );
		$lists  = $xpath->query( '//ul | //ol' );
		$strong = $xpath->query( '//strong | //b' );

		$headings_count = $h2->length + $h3->length;

		$paras = array();
		foreach ( $seq as $b ) {
			if ( 'p' === $b['tag'] && '' !== $b['text'] ) {
				$paras[] = $b['text'];
			}
		}
		$total_words = self::wc( implode( ' ', $paras ) );

		$checks = array();

		/* 1) Answer-first opening. */
		$lead = isset( $paras[0] ) ? self::wc( $paras[0] ) : 0;
		if ( 0 === $lead ) {
			$checks[] = self::row( __( 'Answer-first opening', 'omnigeo-ai-seo' ), 0, __( 'No opening paragraph. Begin with a direct 40–60 word answer to the page’s main question, AI lifts these as citations.', 'omnigeo-ai-seo' ), 2 );
		} elseif ( $lead >= 20 && $lead <= 75 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Answer-first opening', 'omnigeo-ai-seo' ), 2, sprintf( __( 'Concise %d-word opener AI can extract directly.', 'omnigeo-ai-seo' ), $lead ), 2 );
		} elseif ( $lead > 75 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Answer-first opening', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Opening is %d words, front-load a 40–60 word direct answer before the detail.', 'omnigeo-ai-seo' ), $lead ), 2 );
		} else {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Answer-first opening', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Opening is only %d words, expand it into a complete standalone answer.', 'omnigeo-ai-seo' ), $lead ), 2 );
		}

		/* 2) Self-contained sections (each H2 opens with a direct answer). */
		$sections = 0;
		$openers  = 0;
		$await    = false;
		foreach ( $seq as $b ) {
			if ( 'h2' === $b['tag'] ) {
				$sections++;
				$await = true;
			} elseif ( $await && 'p' === $b['tag'] && '' !== $b['text'] ) {
				$w = self::wc( $b['text'] );
				if ( $w >= 15 && $w <= 90 ) {
					$openers++;
				}
				$await = false;
			}
		}
		if ( 0 === $sections ) {
			$checks[] = self::row( __( 'Self-contained sections', 'omnigeo-ai-seo' ), 0, __( 'No H2 sections. Break content into labelled sections AI can extract one at a time.', 'omnigeo-ai-seo' ), 2 );
		} else {
			$ratio = $openers / $sections;
			if ( $ratio >= 0.6 ) {
				/* translators: %1$d, %2$d are values inserted into the message. */
				$checks[] = self::row( __( 'Self-contained sections', 'omnigeo-ai-seo' ), 2, sprintf( __( '%1$d of %2$d sections open with a standalone answer.', 'omnigeo-ai-seo' ), $openers, $sections ), 2 );
			} elseif ( $ratio >= 0.3 ) {
				/* translators: %1$d, %2$d are values inserted into the message. */
				$checks[] = self::row( __( 'Self-contained sections', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Only %1$d of %2$d sections start with a direct answer, add one under each heading.', 'omnigeo-ai-seo' ), $openers, $sections ), 2 );
			} else {
				/* translators: %1$d, %2$d are values inserted into the message. */
				$checks[] = self::row( __( 'Self-contained sections', 'omnigeo-ai-seo' ), 0, sprintf( __( 'Most sections don’t open with a direct answer (%1$d of %2$d).', 'omnigeo-ai-seo' ), $sections - $openers, $sections ), 2 );
			}
		}

		/* 3) Paragraph chunking (no walls of text). */
		$walls = 0;
		$maxw  = 0;
		foreach ( $paras as $p ) {
			$w = self::wc( $p );
			if ( $w > $maxw ) {
				$maxw = $w;
			}
			if ( $w > 120 ) {
				$walls++;
			}
		}
		if ( ! $paras ) {
			$checks[] = self::row( __( 'Paragraph chunking', 'omnigeo-ai-seo' ), 0, __( 'No paragraphs detected.', 'omnigeo-ai-seo' ) );
		} elseif ( 0 === $walls ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Paragraph chunking', 'omnigeo-ai-seo' ), 2, sprintf( __( 'Well chunked, longest paragraph is %d words.', 'omnigeo-ai-seo' ), $maxw ) );
		} elseif ( $walls <= 2 ) {
			/* translators: %1$d, %2$d are values inserted into the message. */
			$checks[] = self::row( __( 'Paragraph chunking', 'omnigeo-ai-seo' ), 1, sprintf( __( '%1$d long paragraph(s), up to %2$d words. Split into one-idea blocks of 40–75 words.', 'omnigeo-ai-seo' ), $walls, $maxw ) );
		} else {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Paragraph chunking', 'omnigeo-ai-seo' ), 0, sprintf( __( '%d wall-of-text paragraphs, AI can’t isolate a clean passage. Break them up.', 'omnigeo-ai-seo' ), $walls ) );
		}

		/* 4) Descriptive headings. */
		$generic = array(
			'introduction', 'intro', 'conclusion', 'overview', 'summary', 'about', 'more', 'details', 'content', 'misc', 'other',
			'مقدمه', 'نتیجه', 'نتیجه‌گیری', 'نتیجه گیری', 'خلاصه', 'درباره', 'درباره ما', 'بیشتر', 'جزئیات', 'معرفی',
		);
		$found   = array();
		foreach ( array( $h2, $h3 ) as $set ) {
			foreach ( $set as $hn ) {
				$t = AIGS_Text::normalize( $hn->textContent );
				if ( in_array( $t, array_map( array( 'AIGS_Text', 'normalize' ), $generic ), true ) ) {
					$found[] = $hn->textContent;
				}
			}
		}
		if ( $headings_count >= 3 && empty( $found ) ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Descriptive headings', 'omnigeo-ai-seo' ), 2, sprintf( __( '%d descriptive sub-headings.', 'omnigeo-ai-seo' ), $headings_count ) );
		} elseif ( $headings_count >= 1 ) {
			$rem = empty( $found )
				? __( 'Add a few more descriptive H2/H3 headings.', 'omnigeo-ai-seo' )
				/* translators: %s is a value inserted into the message. */
				: sprintf( __( 'Generic headings dilute meaning: %s. Prefer specific, question-style headings.', 'omnigeo-ai-seo' ), implode( ', ', array_slice( $found, 0, 3 ) ) );
			$checks[] = self::row( __( 'Descriptive headings', 'omnigeo-ai-seo' ), 1, $rem );
		} else {
			$checks[] = self::row( __( 'Descriptive headings', 'omnigeo-ai-seo' ), 0, __( 'No headings. Add a clear H2/H3 hierarchy.', 'omnigeo-ai-seo' ) );
		}

		/* 5) Lists. */
		if ( $lists->length >= 1 ) {
			$checks[] = self::row( __( 'Lists for steps & points', 'omnigeo-ai-seo' ), 2, __( 'Lists detected, easy for AI to enumerate.', 'omnigeo-ai-seo' ), 1 );
		} else {
			$checks[] = self::row( __( 'Lists for steps & points', 'omnigeo-ai-seo' ), 1, __( 'No bullet/numbered lists. Turn steps and enumerations into lists.', 'omnigeo-ai-seo' ), 1 );
		}

		/* 6) Comparison table (intent-aware). */
		$intent = AIGS_Answer::intent( $title );
		$cmp    = $intent['comparison'];
		if ( $cmp ) {
			if ( $tables->length >= 1 ) {
				$checks[] = self::row( __( 'Comparison table', 'omnigeo-ai-seo' ), 2, __( 'Comparison content includes a table, the easiest format for assistants to reuse.', 'omnigeo-ai-seo' ), 2 );
			} else {
				$checks[] = self::row( __( 'Comparison table', 'omnigeo-ai-seo' ), 0, __( 'This looks like “best/compare” content but has no table. Add one that compares options on real criteria.', 'omnigeo-ai-seo' ), 2 );
			}
		} elseif ( $tables->length >= 1 ) {
			$checks[] = self::row( __( 'Tables', 'omnigeo-ai-seo' ), 2, __( 'Structured table present, great for data extraction.', 'omnigeo-ai-seo' ), 1 );
		} else {
			$checks[] = self::row( __( 'Tables', 'omnigeo-ai-seo' ), 1, __( 'Where you compare options or list specs, a table makes the data easy to extract.', 'omnigeo-ai-seo' ), 1 );
		}

		/* 7) Key-claim emphasis. */
		if ( $sections > 0 ) {
			$s = $strong->length;
			if ( 0 === $s ) {
				$checks[] = self::row( __( 'Key-claim emphasis', 'omnigeo-ai-seo' ), 1, __( 'Bold the single most important claim in each section so AI can spot the primary assertion.', 'omnigeo-ai-seo' ) );
			} elseif ( $s > $sections * 4 || ( $total_words > 0 && $s > $total_words / 15 ) ) {
				/* translators: %d is a value inserted into the message. */
				$checks[] = self::row( __( 'Key-claim emphasis', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Over-emphasis (%d bold spans) dilutes the signal, bold one key sentence per section.', 'omnigeo-ai-seo' ), $s ) );
			} else {
				$checks[] = self::row( __( 'Key-claim emphasis', 'omnigeo-ai-seo' ), 2, __( 'Emphasis is used sparingly to mark key claims.', 'omnigeo-ai-seo' ) );
			}
		}

		/* 7b) Focus keyword coverage (from the active SEO plugin). */
		if ( '' !== trim( (string) $keyword ) ) {
			$in_lead    = isset( $paras[0] ) && self::contains( $paras[0], $keyword );
			$in_heading = false;
			foreach ( array( $h2, $h3 ) as $set ) {
				foreach ( $set as $hn ) {
					if ( self::contains( $hn->textContent, $keyword ) ) {
						$in_heading = true;
						break 2;
					}
				}
			}
			$hits = ( $in_lead ? 1 : 0 ) + ( $in_heading ? 1 : 0 );
			if ( 2 === $hits ) {
				/* translators: %s is a value inserted into the message. */
				$checks[] = self::row( __( 'Focus keyword coverage', 'omnigeo-ai-seo' ), 2, sprintf( __( 'Keyword “%s” appears in the opening and a heading.', 'omnigeo-ai-seo' ), $keyword ), 1 );
			} elseif ( 1 === $hits ) {
				$where    = $in_lead ? __( 'a heading', 'omnigeo-ai-seo' ) : __( 'the opening', 'omnigeo-ai-seo' );
				/* translators: %1$s, %2$s are values inserted into the message. */
				$checks[] = self::row( __( 'Focus keyword coverage', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Keyword “%1$s” is present but missing from %2$s.', 'omnigeo-ai-seo' ), $keyword, $where ), 1 );
			} else {
				/* translators: %s is a value inserted into the message. */
				$checks[] = self::row( __( 'Focus keyword coverage', 'omnigeo-ai-seo' ), 0, sprintf( __( 'Focus keyword “%s” is missing from the opening and headings.', 'omnigeo-ai-seo' ), $keyword ), 1 );
			}
		}

		/* 8) Depth. */
		if ( $total_words >= 600 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Content depth', 'omnigeo-ai-seo' ), 2, sprintf( __( '~%d words of substantive content.', 'omnigeo-ai-seo' ), $total_words ) );
		} elseif ( $total_words >= 300 ) {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Content depth', 'omnigeo-ai-seo' ), 1, sprintf( __( '~%d words, consider expanding for topical completeness.', 'omnigeo-ai-seo' ), $total_words ) );
		} else {
			/* translators: %d is a value inserted into the message. */
			$checks[] = self::row( __( 'Content depth', 'omnigeo-ai-seo' ), 0, sprintf( __( '~%d words, likely too thin to be treated as authoritative.', 'omnigeo-ai-seo' ), $total_words ) );
		}

		$score = self::score_from_checks( $checks );

		return array(
			'score'  => $score,
			'grade'  => self::grade( $score ),
			'url'    => $title,
			'checks' => $checks,
		);
	}

	/**
	 * Compute a 0-100 score from weighted checks (status 0/1/2).
	 *
	 * @param array $checks Checks.
	 * @return int
	 */
	public static function score_from_checks( $checks ) {
		$max = 0;
		$got = 0;
		foreach ( $checks as $c ) {
			$max += $c['weight'];
			$got += $c['weight'] * ( $c['status'] / 2 );
		}
		return $max > 0 ? (int) round( ( $got / $max ) * 100 ) : 0;
	}

	/**
	 * Multibyte-aware case-insensitive "contains".
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	private static function contains( $haystack, $needle ) {
		return AIGS_Text::contains_term( $haystack, $needle );
	}

	/**
	 * Multibyte-aware word count.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function wc( $text ) {
		return AIGS_Text::word_count( $text );
	}

	/**
	 * Build a check row.
	 *
	 * @param string $label  Label.
	 * @param int    $status 0|1|2.
	 * @param string $remark Remark.
	 * @param int    $weight Weight.
	 * @return array
	 */
	private static function row( $label, $status, $remark, $weight = 1 ) {
		return array(
			'label'  => $label,
			'status' => $status,
			'remark' => $remark,
			'weight' => $weight,
		);
	}

	/**
	 * Grade letter.
	 *
	 * @param int $s Score.
	 * @return string
	 */
	public static function grade( $s ) {
		if ( $s >= 90 ) {
			return 'A';
		}
		if ( $s >= 75 ) {
			return 'B';
		}
		if ( $s >= 60 ) {
			return 'C';
		}
		if ( $s >= 40 ) {
			return 'D';
		}
		return 'F';
	}
}
