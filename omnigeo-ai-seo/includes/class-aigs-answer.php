<?php
/**
 * Answer Optimizer.
 *
 * Reads a page the way an answer engine does: what question is this page for,
 * does the opening actually answer it, do question-style headings get a direct
 * answer, and which Q&A pairs could become FAQ markup. Works offline with
 * rules; an optional AI draft (your own key) proposes a direct answer that the
 * author reviews before inserting.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structural + answer analysis shared by the editor box and the site scanner.
 */
class AIGS_Answer {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_answer_draft', array( $this, 'ajax_draft' ) );
	}

	/**
	 * Parse rendered HTML into the structure every check needs.
	 *
	 * @param string $html Rendered content HTML.
	 * @return array
	 */
	public static function structure( $html ) {
		$out = array(
			'blocks'      => array(),
			'paragraphs'  => array(),
			'headings'    => array(),
			'tables'      => 0,
			'lists'       => 0,
			'ordered'     => 0,
			'strong'      => 0,
			'images'      => array(),
			'links'       => array(),
			'words'       => 0,
			'faq_blocks'  => false,
		);
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return $out;
		}
		if ( false !== strpos( $html, 'schema-faq' ) || false !== strpos( $html, 'rank-math-faq' ) || false !== strpos( $html, 'rank-math-list-item' ) ) {
			$out['faq_blocks'] = true;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		$flow = $xpath->query( '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::p or self::ul or self::ol or self::table or self::blockquote]' );
		$last = null;
		foreach ( $flow as $n ) {
			$tag  = strtolower( $n->nodeName );
			$text = trim( preg_replace( '/\s+/u', ' ', $n->textContent ) );
			// Skip paragraphs nested in lists/tables so they are not double counted.
			if ( 'p' === $tag && $n->parentNode && in_array( strtolower( $n->parentNode->nodeName ), array( 'li', 'td', 'th' ), true ) ) {
				continue;
			}
			$out['blocks'][] = array(
				'tag'  => $tag,
				'text' => $text,
			);
			if ( 'p' === $tag && '' !== $text ) {
				$out['paragraphs'][] = $text;
				if ( null !== $last && '' === $out['headings'][ $last ]['answer'] ) {
					$out['headings'][ $last ]['answer'] = $text;
				}
			} elseif ( in_array( $tag, array( 'h2', 'h3' ), true ) && '' !== $text ) {
				$out['headings'][] = array(
					'tag'    => $tag,
					'text'   => $text,
					'answer' => '',
				);
				$last = count( $out['headings'] ) - 1;
			} elseif ( in_array( $tag, array( 'ul', 'ol', 'table' ), true ) && null !== $last && '' === $out['headings'][ $last ]['answer'] ) {
				// A list or table directly under a heading is a valid structured answer.
				$out['headings'][ $last ]['answer'] = $text;
			}
		}

		$out['tables']  = $xpath->query( '//table' )->length;
		$out['lists']   = $xpath->query( '//ul | //ol' )->length;
		$out['ordered'] = $xpath->query( '//ol' )->length;
		$out['strong']  = $xpath->query( '//strong | //b' )->length;

		foreach ( $xpath->query( '//img' ) as $img ) {
			$out['images'][] = array(
				'src' => (string) $img->getAttribute( 'src' ),
				'alt' => trim( (string) $img->getAttribute( 'alt' ) ),
			);
		}
		foreach ( $xpath->query( '//a[@href]' ) as $a ) {
			$href = trim( (string) $a->getAttribute( 'href' ) );
			if ( '' !== $href && 0 !== strpos( $href, '#' ) && 0 !== stripos( $href, 'mailto:' ) && 0 !== stripos( $href, 'tel:' ) && 0 !== stripos( $href, 'javascript:' ) ) {
				$out['links'][] = $href;
			}
		}

		$out['words'] = AIGS_Text::word_count( implode( ' ', $out['paragraphs'] ) );
		foreach ( $out['blocks'] as $b ) {
			if ( in_array( $b['tag'], array( 'ul', 'ol', 'table', 'blockquote' ), true ) ) {
				$out['words'] += AIGS_Text::word_count( $b['text'] );
			}
		}
		return $out;
	}

	/**
	 * Search intent signals from the title.
	 *
	 * @param string $title Title.
	 * @return array{comparison:bool,howto:bool,definition:bool}
	 */
	public static function intent( $title ) {
		$t = AIGS_Text::normalize( $title );
		return array(
			'comparison' => (bool) preg_match( '/(\bbest\b|\btop \d|\bvs\.?\b|versus|compar|alternativ|بهترین|مقایسه|تفاوت|فرق|یا بهتر|جایگزین)/u', $t ),
			'howto'      => (bool) preg_match( '/(how to|how do|guide|tutorial|step by step|steps to|آموزش|راهنما|روش|نحوه|چطور|چگونه|مراحل|قدم به قدم)/u', $t ),
			'definition' => (bool) preg_match( '/(^what is|^what are|meaning|definition|چیست|چیه|یعنی چه|به چه معناست|تعریف)/u', $t ),
		);
	}

	/**
	 * The question this page should answer: the title when it is a question,
	 * otherwise one inferred from the focus keyword / title.
	 *
	 * @param string $title   Title.
	 * @param string $keyword Focus keyword.
	 * @return array{text:string,inferred:bool}
	 */
	public static function main_question( $title, $keyword = '' ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );
		if ( AIGS_Text::is_question( $title ) ) {
			return array(
				'text'     => $title,
				'inferred' => false,
			);
		}
		$topic = '' !== trim( (string) $keyword ) ? trim( $keyword ) : $title;
		$topic = trim( preg_replace( '/[:|\-–—].*$/u', '', $topic ) );
		if ( '' === $topic ) {
			$topic = $title;
		}
		$intent = self::intent( $title );

		if ( AIGS_Text::is_rtl_script( $topic ) ) {
			$clean = trim( preg_replace( '/^(بهترین|آموزش|راهنمای|راهنما|روش|نحوه)\s+/u', '', $topic ) );
			if ( $intent['comparison'] ) {
				$q = 'بهترین ' . $clean . ' کدام است؟';
			} elseif ( $intent['howto'] ) {
				$q = $clean . ' چگونه انجام می‌شود؟';
			} else {
				$q = $clean . ' چیست؟';
			}
		} else {
			$clean = trim( preg_replace( '/^(the best|best|how to|guide to|a guide to|complete guide to)\s+/iu', '', $topic ) );
			if ( $intent['comparison'] ) {
				$q = 'What is the best ' . $clean . '?';
			} elseif ( $intent['howto'] ) {
				$q = 'How do you ' . $clean . '?';
			} else {
				$q = 'What is ' . $clean . '?';
			}
		}
		return array(
			'text'     => $q,
			'inferred' => true,
		);
	}

	/**
	 * Full answer analysis.
	 *
	 * @param array  $s       Output of structure().
	 * @param string $title   Title.
	 * @param string $keyword Focus keyword.
	 * @return array
	 */
	public static function analyze( $s, $title, $keyword = '' ) {
		$q      = self::main_question( $title, $keyword );
		$intent = self::intent( $title );
		$lead   = isset( $s['paragraphs'][0] ) ? $s['paragraphs'][0] : '';
		$lw     = AIGS_Text::word_count( $lead );
		$topic  = '' !== trim( (string) $keyword ) ? $keyword . ' ' . $title : $title;
		$ov     = '' !== $lead ? AIGS_Text::overlap( $topic, $lead ) : 0.0;
		$filler = '' !== $lead && AIGS_Text::is_filler_opening( $lead );

		// Direct answer status: 2 good, 1 needs work, 0 missing.
		if ( '' === $lead ) {
			$direct = 0;
			$reason = 'missing';
		} elseif ( $filler ) {
			$direct = 0;
			$reason = 'filler';
		} elseif ( $ov < 0.34 ) {
			$direct = 1;
			$reason = 'off_topic';
		} elseif ( $lw > 80 ) {
			$direct = 1;
			$reason = 'long';
		} elseif ( $lw < 15 ) {
			$direct = 1;
			$reason = 'short';
		} else {
			$direct = 2;
			$reason = 'ok';
		}

		// Question headings and whether the first block after them answers.
		$qh   = array();
		$faq  = array();
		foreach ( $s['headings'] as $h ) {
			if ( ! AIGS_Text::is_question( $h['text'] ) ) {
				continue;
			}
			$ans_words = AIGS_Text::word_count( $h['answer'] );
			$answered  = $ans_words >= 8 && ! AIGS_Text::is_filler_opening( $h['answer'] );
			$qh[]      = array(
				'q'        => $h['text'],
				'answered' => $answered,
			);
			if ( $answered && count( $faq ) < 10 ) {
				$faq[] = array(
					'q' => $h['text'],
					'a' => AIGS_Text::excerpt_sentences( $h['answer'], 60 ),
				);
			}
		}
		$unanswered = array();
		foreach ( $qh as $row ) {
			if ( ! $row['answered'] ) {
				$unanswered[] = $row['q'];
			}
		}

		$tips = array();
		if ( 'filler' === $reason ) {
			$tips[] = __( 'Your opening is an introduction, not an answer. Replace it with one or two sentences that answer the main question directly; move the introduction below.', 'omnigeo-ai-seo' );
		} elseif ( 'missing' === $reason ) {
			$tips[] = __( 'Start the page with a short paragraph that answers the main question in 40–60 words.', 'omnigeo-ai-seo' );
		} elseif ( 'off_topic' === $reason ) {
			$tips[] = __( 'The opening paragraph barely mentions the page topic. Answer the main question in the first sentences using the same words people search with.', 'omnigeo-ai-seo' );
		} elseif ( 'long' === $reason ) {
			$tips[] = __( 'The answer is buried in a long opening. Put a 40–60 word summary first, then the details.', 'omnigeo-ai-seo' );
		} elseif ( 'short' === $reason ) {
			$tips[] = __( 'The opening is very short. Make it a complete, standalone answer that still makes sense when quoted alone.', 'omnigeo-ai-seo' );
		}
		if ( $unanswered ) {
			$tips[] = __( 'Some question headings are not followed by a direct answer. Answer each question in the first sentence under it.', 'omnigeo-ai-seo' );
		}
		if ( $intent['comparison'] && 0 === (int) $s['tables'] ) {
			$tips[] = __( 'This is comparison content: add a table that compares the options on the criteria buyers care about (price, features, pros and cons).', 'omnigeo-ai-seo' );
		}
		if ( $intent['howto'] && 0 === (int) $s['ordered'] ) {
			$tips[] = __( 'This is a how-to: list the steps as a numbered list so assistants can quote them in order.', 'omnigeo-ai-seo' );
		}
		if ( $intent['definition'] && '' !== $lead && ! preg_match( '/(\bis\b|\bare\b|\bmeans\b|\brefers to\b|است|هستند|یعنی|به معنای|عبارت است|اشاره دارد)/u', AIGS_Text::normalize( $lead ) ) ) {
			$tips[] = __( 'For a “what is” page, open with a one-sentence definition (“X is …”).', 'omnigeo-ai-seo' );
		}
		if ( count( $faq ) >= 2 ) {
			$tips[] = __( 'Answered question headings were found. You can add them as FAQ schema with one click.', 'omnigeo-ai-seo' );
		}

		return array(
			'question'   => $q,
			'intent'     => $intent,
			'lead'       => AIGS_Text::excerpt_sentences( $lead, 70 ),
			'lead_words' => $lw,
			'overlap'    => round( $ov, 2 ),
			'direct'     => $direct,
			'reason'     => $reason,
			'questions'  => $qh,
			'unanswered' => $unanswered,
			'faq'        => $faq,
			'tips'       => $tips,
			'has_table'  => (int) $s['tables'] > 0,
			'has_steps'  => (int) $s['ordered'] > 0,
		);
	}

	/**
	 * Check rows (same shape as the extractability report) for the editor score.
	 *
	 * @param array $a Output of analyze().
	 * @return array
	 */
	public static function check_rows( $a ) {
		$rows  = array();
		$label = __( 'Direct answer to the main question', 'omnigeo-ai-seo' );
		switch ( $a['reason'] ) {
			case 'ok':
				$rows[] = self::row( $label, 2, __( 'The opening answers the page’s main question concisely.', 'omnigeo-ai-seo' ), 2 );
				break;
			case 'filler':
				$rows[] = self::row( $label, 0, __( 'The page opens with an introduction (“In this article…”) instead of an answer.', 'omnigeo-ai-seo' ), 2 );
				break;
			case 'missing':
				$rows[] = self::row( $label, 0, __( 'No opening paragraph to quote.', 'omnigeo-ai-seo' ), 2 );
				break;
			case 'off_topic':
				$rows[] = self::row( $label, 1, __( 'The opening paragraph does not clearly address the page topic.', 'omnigeo-ai-seo' ), 2 );
				break;
			default:
				$rows[] = self::row( $label, 1, __( 'An answer is present but not in quotable 40–60 word form.', 'omnigeo-ai-seo' ), 2 );
		}
		if ( $a['questions'] ) {
			$total = count( $a['questions'] );
			$miss  = count( $a['unanswered'] );
			/* translators: 1: answered count, 2: total question headings. */
			$msg   = sprintf( __( '%1$d of %2$d question headings are answered right below the heading.', 'omnigeo-ai-seo' ), $total - $miss, $total );
			$rows[] = self::row( __( 'Answered question headings', 'omnigeo-ai-seo' ), 0 === $miss ? 2 : ( $miss < $total ? 1 : 0 ), $msg, 1 );
		}
		if ( $a['intent']['howto'] ) {
			$rows[] = self::row( __( 'Numbered steps', 'omnigeo-ai-seo' ), $a['has_steps'] ? 2 : 1, $a['has_steps'] ? __( 'Steps are in a numbered list.', 'omnigeo-ai-seo' ) : __( 'How-to content without a numbered list of steps.', 'omnigeo-ai-seo' ), 1 );
		}
		return $rows;
	}

	/**
	 * Row helper.
	 *
	 * @param string $label  Label.
	 * @param int    $status Status.
	 * @param string $remark Remark.
	 * @param int    $weight Weight.
	 * @return array
	 */
	private static function row( $label, $status, $remark, $weight ) {
		return array(
			'label'  => $label,
			'status' => $status,
			'remark' => $remark,
			'weight' => $weight,
		);
	}

	/**
	 * AJAX: draft a direct answer with the user's own AI key.
	 */
	public function ajax_draft() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		if ( ! AIGS_AI_Client::ready() ) {
			wp_send_json_error( array( 'message' => __( 'Add your AI API key in Settings first.', 'omnigeo-ai-seo' ) ) );
		}
		$content  = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : get_the_title( $post_id );
		$question = isset( $_POST['question'] ) ? sanitize_text_field( wp_unslash( $_POST['question'] ) ) : '';

		$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( do_blocks( $content ) ) ) );
		if ( AIGS_Text::word_count( $plain ) < 40 ) {
			wp_send_json_error( array( 'message' => __( 'Write a little more first. The draft is built only from facts already on the page.', 'omnigeo-ai-seo' ) ) );
		}
		$plain = wp_trim_words( $plain, 1500, '' );
		if ( '' === $question ) {
			$q        = self::main_question( $title, AIGS_SEO::focus_keyword( $post_id ) );
			$question = $q['text'];
		}

		// Same content + question = same draft; do not pay twice.
		$cache_key = 'aigs_draft_' . md5( $question . '|' . $plain . '|' . AIGS_AI_Client::model() );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$system = 'You edit web pages so answer engines can quote them. Write in the same language and script as the page text. Use only facts stated in the page text. Do not invent numbers, prices, names or claims. If the page does not contain the answer, reply exactly: NO_ANSWER';
		$prompt = "Question: {$question}\n\nPage title: {$title}\n\nPage text:\n{$plain}\n\nWrite one direct answer paragraph of 40 to 60 words that answers the question in the first sentence and could be quoted on its own. Return only the paragraph, no heading, no quotes, no markdown.";

		$res = AIGS_AI_Client::ask(
			$prompt,
			array(
				'system'     => $system,
				'max_tokens' => 400,
			)
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$text = trim( wp_strip_all_tags( (string) $res['text'] ) );
		$text = trim( $text, " \t\n\r\"“”«»" );
		if ( '' === $text || false !== strpos( $text, 'NO_ANSWER' ) ) {
			wp_send_json_error( array( 'message' => __( 'The page text does not contain enough information to answer the question. Add the facts first, then try again.', 'omnigeo-ai-seo' ) ) );
		}
		$out = array(
			'question' => $question,
			'draft'    => $text,
			'words'    => AIGS_Text::word_count( $text ),
			'model'    => $res['model'],
			'left'     => AIGS_AI_Client::remaining(),
		);
		set_transient( $cache_key, $out, WEEK_IN_SECONDS );
		wp_send_json_success( $out );
	}
}
