<?php
/**
 * AEO / GEO audit & scoring engine.
 *
 * Runs technical AI-readiness, content-quality and E-E-A-T checks on a URL and
 * returns a structured report with a 0-100 GEO score.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a URL's HTML and grades it for AI search readiness.
 */
class AIGS_Audit {

	/**
	 * Register hooks (AJAX endpoints).
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_audit', array( $this, 'ajax_audit' ) );
	}

	/**
	 * AJAX handler.
	 */
	public function ajax_audit() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : home_url( '/' );
		wp_send_json_success( $this->audit_url( $url ) );
	}

	/**
	 * Audit a URL.
	 *
	 * @param string $url URL to audit.
	 * @return array Report.
	 */
	public function audit_url( $url ) {
		// SSRF guard: reject invalid URLs and private/loopback/reserved hosts.
		if ( ! wp_http_validate_url( $url ) ) {
			return array(
				'url'    => $url,
				'error'  => __( 'That URL could not be validated. Use a public http or https address.', 'omnigeo-ai-seo' ),
				'score'  => 0,
				'checks' => array(),
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'OmniGEO-Ai-SEO-Audit/1.0 (+' . home_url() . ')',
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'url'     => $url,
				'error'   => $response->get_error_message(),
				'score'   => 0,
				'checks'  => array(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return array(
				'url'    => $url,
				/* translators: %d is the HTTP status code. */
				'error'  => sprintf( __( 'The page returned HTTP %d, so there is nothing to analyze.', 'omnigeo-ai-seo' ), $code ),
				'score'  => 0,
				'checks' => array(),
			);
		}

		$html = wp_remote_retrieve_body( $response );
		if ( '' === trim( $html ) ) {
			return array(
				'url'    => $url,
				'error'  => __( 'The page returned no HTML content to analyze.', 'omnigeo-ai-seo' ),
				'score'  => 0,
				'checks' => array(),
			);
		}
		$dom = $this->load_dom( $html );

		$checks = array();
		$checks[] = $this->c_title( $dom );
		$checks[] = $this->c_meta_description( $dom );
		$checks[] = $this->c_h1( $dom );
		$checks[] = $this->c_headings( $dom );
		$checks[] = $this->c_jsonld( $html );
		$checks[] = $this->c_faq_speakable( $html );
		$checks[] = $this->c_alt( $dom );
		$checks[] = $this->c_og_twitter( $dom );
		$checks[] = $this->c_lang( $dom );
		$checks[] = $this->c_links( $dom, $url );
		$checks[] = $this->c_word_count( $dom );
		$checks[] = $this->c_quick_answer( $dom );
		$checks[] = $this->c_eeat( $dom, $html );
		$checks[] = $this->c_robots();
		$checks[] = $this->c_https( $url );

		$max   = 0;
		$got   = 0;
		foreach ( $checks as $c ) {
			$max += $c['weight'];
			$got += $c['weight'] * ( $c['status'] / 2 ); // status 0,1,2 -> 0,.5,1.
		}
		$score = $max > 0 ? (int) round( ( $got / $max ) * 100 ) : 0;

		return array(
			'url'    => $url,
			'score'  => $score,
			'grade'  => $this->grade( $score ),
			'checks' => $checks,
		);
	}

	/**
	 * Build a DOMDocument from HTML safely.
	 *
	 * @param string $html HTML.
	 * @return DOMDocument
	 */
	private function load_dom( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		if ( $html ) {
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		}
		libxml_clear_errors();
		return $dom;
	}

	/**
	 * Helper to build a check row.
	 *
	 * @param string $label  Label.
	 * @param int    $status 0 fail, 1 partial, 2 pass.
	 * @param string $remark Remark.
	 * @param int    $weight Weight.
	 * @return array
	 */
	private function row( $label, $status, $remark, $weight = 1 ) {
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
	 * @param int $score Score.
	 * @return string
	 */
	private function grade( $score ) {
		if ( $score >= 90 ) {
			return 'A';
		}
		if ( $score >= 75 ) {
			return 'B';
		}
		if ( $score >= 60 ) {
			return 'C';
		}
		if ( $score >= 40 ) {
			return 'D';
		}
		return 'F';
	}

	/* ---------- Individual checks ---------- */

	private function c_title( $dom ) {
		$nodes = $dom->getElementsByTagName( 'title' );
		$title = $nodes->length ? trim( $nodes->item( 0 )->textContent ) : '';
		$len   = mb_strlen( $title );
		if ( $len >= 30 && $len <= 65 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Title tag', 'omnigeo-ai-seo' ), 2, sprintf( __( 'Good length (%d chars).', 'omnigeo-ai-seo' ), $len ) );
		}
		if ( $len > 0 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Title tag', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Present but length is %d (aim 30–65).', 'omnigeo-ai-seo' ), $len ) );
		}
		return $this->row( __( 'Title tag', 'omnigeo-ai-seo' ), 0, __( 'Missing title tag.', 'omnigeo-ai-seo' ) );
	}

	private function c_meta_description( $dom ) {
		$desc = $this->meta( $dom, 'name', 'description' );
		$len  = mb_strlen( $desc );
		if ( $len >= 80 && $len <= 160 ) {
			return $this->row( __( 'Meta description', 'omnigeo-ai-seo' ), 2, __( 'Well-sized meta description.', 'omnigeo-ai-seo' ) );
		}
		if ( $len > 0 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Meta description', 'omnigeo-ai-seo' ), 1, sprintf( __( 'Present (%d chars); aim 80–160.', 'omnigeo-ai-seo' ), $len ) );
		}
		return $this->row( __( 'Meta description', 'omnigeo-ai-seo' ), 0, __( 'No meta description, AI engines use it for summaries.', 'omnigeo-ai-seo' ) );
	}

	private function c_h1( $dom ) {
		$h1 = $dom->getElementsByTagName( 'h1' );
		if ( 1 === $h1->length ) {
			return $this->row( __( 'Single H1', 'omnigeo-ai-seo' ), 2, __( 'Exactly one clear H1.', 'omnigeo-ai-seo' ) );
		}
		if ( $h1->length > 1 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Single H1', 'omnigeo-ai-seo' ), 1, sprintf( __( '%d H1 tags found; prefer one.', 'omnigeo-ai-seo' ), $h1->length ) );
		}
		return $this->row( __( 'Single H1', 'omnigeo-ai-seo' ), 0, __( 'No H1 heading found.', 'omnigeo-ai-seo' ) );
	}

	private function c_headings( $dom ) {
		$count = 0;
		foreach ( array( 'h2', 'h3' ) as $tag ) {
			$count += $dom->getElementsByTagName( $tag )->length;
		}
		if ( $count >= 3 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Heading structure', 'omnigeo-ai-seo' ), 2, sprintf( __( '%d sub-headings, easy for AI to chunk.', 'omnigeo-ai-seo' ), $count ) );
		}
		if ( $count >= 1 ) {
			return $this->row( __( 'Heading structure', 'omnigeo-ai-seo' ), 1, __( 'Few sub-headings; add more H2/H3 sections.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'Heading structure', 'omnigeo-ai-seo' ), 0, __( 'No H2/H3 structure.', 'omnigeo-ai-seo' ) );
	}

	private function c_jsonld( $html ) {
		if ( false !== stripos( $html, 'application/ld+json' ) ) {
			return $this->row( __( 'Structured data (JSON-LD)', 'omnigeo-ai-seo' ), 2, __( 'JSON-LD structured data present.', 'omnigeo-ai-seo' ), 2 );
		}
		return $this->row( __( 'Structured data (JSON-LD)', 'omnigeo-ai-seo' ), 0, __( 'No JSON-LD, add schema via the meta box.', 'omnigeo-ai-seo' ), 2 );
	}

	private function c_faq_speakable( $html ) {
		$faq   = ( false !== stripos( $html, '"FAQPage"' ) || false !== stripos( $html, '"Question"' ) );
		$speak = ( false !== stripos( $html, 'speakable' ) );
		if ( $faq && $speak ) {
			return $this->row( __( 'FAQ / Speakable schema', 'omnigeo-ai-seo' ), 2, __( 'Both FAQ and Speakable markup detected.', 'omnigeo-ai-seo' ) );
		}
		if ( $faq || $speak ) {
			return $this->row( __( 'FAQ / Speakable schema', 'omnigeo-ai-seo' ), 1, __( 'One of FAQ/Speakable present; add the other.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'FAQ / Speakable schema', 'omnigeo-ai-seo' ), 0, __( 'No FAQ or Speakable markup.', 'omnigeo-ai-seo' ) );
	}

	private function c_alt( $dom ) {
		$imgs = $dom->getElementsByTagName( 'img' );
		if ( 0 === $imgs->length ) {
			return $this->row( __( 'Image alt text', 'omnigeo-ai-seo' ), 1, __( 'No images on page.', 'omnigeo-ai-seo' ) );
		}
		$missing = 0;
		foreach ( $imgs as $img ) {
			if ( '' === trim( $img->getAttribute( 'alt' ) ) ) {
				$missing++;
			}
		}
		if ( 0 === $missing ) {
			return $this->row( __( 'Image alt text', 'omnigeo-ai-seo' ), 2, __( 'All images have alt text.', 'omnigeo-ai-seo' ) );
		}
		/* translators: %d is a value inserted into the message. */
		return $this->row( __( 'Image alt text', 'omnigeo-ai-seo' ), 1, sprintf( __( '%d image(s) missing alt text.', 'omnigeo-ai-seo' ), $missing ) );
	}

	private function c_og_twitter( $dom ) {
		$og = $this->meta( $dom, 'property', 'og:title' );
		$tw = $this->meta( $dom, 'name', 'twitter:card' );
		if ( $og && $tw ) {
			return $this->row( __( 'Open Graph / Twitter', 'omnigeo-ai-seo' ), 2, __( 'Social meta tags present.', 'omnigeo-ai-seo' ) );
		}
		if ( $og || $tw ) {
			return $this->row( __( 'Open Graph / Twitter', 'omnigeo-ai-seo' ), 1, __( 'Partial social meta tags.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'Open Graph / Twitter', 'omnigeo-ai-seo' ), 0, __( 'Missing OG/Twitter tags.', 'omnigeo-ai-seo' ) );
	}

	private function c_lang( $dom ) {
		$html = $dom->getElementsByTagName( 'html' );
		$lang = $html->length ? $html->item( 0 )->getAttribute( 'lang' ) : '';
		if ( $lang ) {
			/* translators: %s is a value inserted into the message. */
			return $this->row( __( 'Language attribute', 'omnigeo-ai-seo' ), 2, sprintf( __( 'lang="%s" set.', 'omnigeo-ai-seo' ), esc_html( $lang ) ) );
		}
		return $this->row( __( 'Language attribute', 'omnigeo-ai-seo' ), 0, __( 'No <html lang> attribute.', 'omnigeo-ai-seo' ) );
	}

	private function c_links( $dom, $url ) {
		$host     = wp_parse_url( $url, PHP_URL_HOST );
		$internal = 0;
		$external = 0;
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			$href = $a->getAttribute( 'href' );
			if ( ! $href || 0 === strpos( $href, '#' ) ) {
				continue;
			}
			$h = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $h || $h === $host ) {
				$internal++;
			} else {
				$external++;
			}
		}
		if ( $internal >= 3 && $external >= 1 ) {
			/* translators: 1: number of internal links, 2: number of external links. */
			return $this->row( __( 'Internal & external links', 'omnigeo-ai-seo' ), 2, sprintf( __( '%1$d internal, %2$d external (authority signals).', 'omnigeo-ai-seo' ), $internal, $external ) );
		}
		if ( $internal >= 1 ) {
			return $this->row( __( 'Internal & external links', 'omnigeo-ai-seo' ), 1, __( 'Add more internal links and at least one authoritative outbound link.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'Internal & external links', 'omnigeo-ai-seo' ), 0, __( 'No meaningful links found.', 'omnigeo-ai-seo' ) );
	}

	private function c_word_count( $dom ) {
		$body  = $dom->getElementsByTagName( 'body' );
		$text  = $body->length ? $body->item( 0 )->textContent : '';
		$words = str_word_count( wp_strip_all_tags( $text ) );
		if ( $words >= 600 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Content depth', 'omnigeo-ai-seo' ), 2, sprintf( __( '~%d words, enough context for AI.', 'omnigeo-ai-seo' ), $words ) );
		}
		if ( $words >= 300 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'Content depth', 'omnigeo-ai-seo' ), 1, sprintf( __( '~%d words; consider expanding.', 'omnigeo-ai-seo' ), $words ) );
		}
		/* translators: %d is a value inserted into the message. */
		return $this->row( __( 'Content depth', 'omnigeo-ai-seo' ), 0, sprintf( __( 'Only ~%d words, too thin.', 'omnigeo-ai-seo' ), $words ) );
	}

	private function c_quick_answer( $dom ) {
		// Heuristic: a short, direct paragraph near the top.
		$ps = $dom->getElementsByTagName( 'p' );
		for ( $i = 0; $i < min( 3, $ps->length ); $i++ ) {
			$len = mb_strlen( trim( $ps->item( $i )->textContent ) );
			if ( $len >= 40 && $len <= 320 ) {
				return $this->row( __( 'Quick answer / snippet', 'omnigeo-ai-seo' ), 2, __( 'A concise lead paragraph is present (good for featured snippets).', 'omnigeo-ai-seo' ) );
			}
		}
		return $this->row( __( 'Quick answer / snippet', 'omnigeo-ai-seo' ), 1, __( 'Add a 1–3 sentence direct answer near the top.', 'omnigeo-ai-seo' ) );
	}

	private function c_eeat( $dom, $html ) {
		$signals = 0;
		if ( false !== stripos( $html, '"author"' ) || $this->meta( $dom, 'name', 'author' ) ) {
			$signals++;
		}
		if ( false !== stripos( $html, 'datePublished' ) || false !== stripos( $html, 'dateModified' ) ) {
			$signals++;
		}
		if ( false !== stripos( $html, '"Organization"' ) || false !== stripos( $html, '"Person"' ) ) {
			$signals++;
		}
		if ( $signals >= 2 ) {
			/* translators: %d is a value inserted into the message. */
			return $this->row( __( 'E-E-A-T signals', 'omnigeo-ai-seo' ), 2, sprintf( __( '%d trust signals (author/date/entity).', 'omnigeo-ai-seo' ), $signals ), 2 );
		}
		if ( 1 === $signals ) {
			return $this->row( __( 'E-E-A-T signals', 'omnigeo-ai-seo' ), 1, __( 'Add author, published/modified dates and entity markup.', 'omnigeo-ai-seo' ), 2 );
		}
		return $this->row( __( 'E-E-A-T signals', 'omnigeo-ai-seo' ), 0, __( 'No author/date/entity trust signals.', 'omnigeo-ai-seo' ), 2 );
	}

	private function c_robots() {
		$resp = wp_remote_get( home_url( '/robots.txt' ), array( 'timeout' => 10, 'sslverify' => true ) );
		if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
			return $this->row( __( 'robots.txt present', 'omnigeo-ai-seo' ), 2, __( 'robots.txt is reachable.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'robots.txt present', 'omnigeo-ai-seo' ), 0, __( 'robots.txt not reachable.', 'omnigeo-ai-seo' ) );
	}

	private function c_https( $url ) {
		if ( 0 === stripos( $url, 'https://' ) ) {
			return $this->row( __( 'HTTPS', 'omnigeo-ai-seo' ), 2, __( 'Served over HTTPS.', 'omnigeo-ai-seo' ) );
		}
		return $this->row( __( 'HTTPS', 'omnigeo-ai-seo' ), 0, __( 'Not HTTPS, fix before anything else.', 'omnigeo-ai-seo' ) );
	}

	/**
	 * Read a meta tag value.
	 *
	 * @param DOMDocument $dom  DOM.
	 * @param string      $attr property|name.
	 * @param string      $val  Value to match.
	 * @return string
	 */
	private function meta( $dom, $attr, $val ) {
		foreach ( $dom->getElementsByTagName( 'meta' ) as $m ) {
			if ( strtolower( $m->getAttribute( $attr ) ) === strtolower( $val ) ) {
				return trim( $m->getAttribute( 'content' ) );
			}
		}
		return '';
	}
}
