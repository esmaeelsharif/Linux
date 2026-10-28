<?php
/**
 * Bring-your-own-key AI client.
 *
 * One place that talks to OpenAI, Anthropic and Perplexity, returns a uniform
 * result (text, model, citations, whether the answer was web-grounded) and
 * enforces a hard monthly request cap so a schedule can never run up a bill.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider abstraction.
 */
class AIGS_AI_Client {

	/**
	 * Provider catalogue.
	 *
	 * grounded = the provider answers with a live web search and returns sources.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'openai'     => array(
				'label'    => 'OpenAI (ChatGPT API)',
				'model'    => 'gpt-4o-mini',
				'grounded' => false,
			),
			'anthropic'  => array(
				'label'    => 'Anthropic (Claude API)',
				'model'    => 'claude-haiku-5-5',
				'grounded' => false,
			),
			'perplexity' => array(
				'label'    => 'Perplexity (Sonar, web search)',
				'model'    => 'sonar',
				'grounded' => true,
			),
		);
	}

	/**
	 * Active provider key (validated).
	 *
	 * @return string
	 */
	public static function provider() {
		$p = (string) aigs_get( 'ai_provider', 'openai' );
		return array_key_exists( $p, self::providers() ) ? $p : 'openai';
	}

	/**
	 * Model to use: the saved override or the provider default.
	 *
	 * @return string
	 */
	public static function model() {
		$m = trim( (string) aigs_get( 'ai_model', '' ) );
		if ( '' !== $m ) {
			return $m;
		}
		$all = self::providers();
		return $all[ self::provider() ]['model'];
	}

	/**
	 * API key. A wp-config.php constant wins over the database value.
	 *
	 * @return string
	 */
	public static function key() {
		if ( defined( 'AIGS_AI_API_KEY' ) && '' !== (string) AIGS_AI_API_KEY ) {
			return (string) AIGS_AI_API_KEY;
		}
		return (string) aigs_get( 'ai_api_key', '' );
	}

	/**
	 * Is a key configured?
	 *
	 * @return bool
	 */
	public static function ready() {
		return '' !== self::key();
	}

	/**
	 * Does the active provider search the live web?
	 *
	 * @return bool
	 */
	public static function grounded() {
		$all = self::providers();
		return ! empty( $all[ self::provider() ]['grounded'] );
	}

	/**
	 * Monthly cap on requests across every AI feature.
	 *
	 * @return int
	 */
	public static function monthly_cap() {
		return max( 0, (int) aigs_get( 'ai_monthly_cap', 60 ) );
	}

	/**
	 * Requests used this calendar month.
	 *
	 * @return int
	 */
	public static function used() {
		$u = get_option( 'aigs_ai_usage', array() );
		$m = gmdate( 'Y-m' );
		return ( is_array( $u ) && isset( $u[ $m ] ) ) ? (int) $u[ $m ] : 0;
	}

	/**
	 * Requests left this month.
	 *
	 * @return int
	 */
	public static function remaining() {
		return max( 0, self::monthly_cap() - self::used() );
	}

	/**
	 * Count one request against the cap (keeps 6 months of history).
	 */
	private static function count_usage() {
		$u = get_option( 'aigs_ai_usage', array() );
		$u = is_array( $u ) ? $u : array();
		$m = gmdate( 'Y-m' );
		$u[ $m ] = isset( $u[ $m ] ) ? (int) $u[ $m ] + 1 : 1;
		krsort( $u );
		update_option( 'aigs_ai_usage', array_slice( $u, 0, 6, true ), false );
	}

	/**
	 * Send one prompt.
	 *
	 * @param string $prompt User prompt.
	 * @param array  $args   Optional: system (string), max_tokens (int).
	 * @return array|WP_Error { text, model, provider, grounded, citations[] }
	 */
	public static function ask( $prompt, $args = array() ) {
		$key = self::key();
		if ( '' === $key ) {
			return new WP_Error( 'aigs_no_key', __( 'Add your AI API key in Settings first.', 'omnigeo-ai-seo' ) );
		}
		if ( self::remaining() <= 0 ) {
			return new WP_Error( 'aigs_cap', __( 'Monthly AI request limit reached. Raise it in Settings if you want to continue this month.', 'omnigeo-ai-seo' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'system'     => '',
				'max_tokens' => 1024,
			)
		);

		$provider = self::provider();
		$model    = self::model();
		self::count_usage();

		switch ( $provider ) {
			case 'anthropic':
				$res = self::anthropic( $key, $model, $prompt, $args );
				break;
			case 'perplexity':
				$res = self::chat_completions( 'https://api.perplexity.ai/chat/completions', $key, $model, $prompt, $args );
				break;
			default:
				$res = self::chat_completions( 'https://api.openai.com/v1/chat/completions', $key, $model, $prompt, $args );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['provider'] = $provider;
		$res['model']    = isset( $res['model'] ) && $res['model'] ? $res['model'] : $model;
		$res['grounded'] = self::grounded();
		return $res;
	}

	/**
	 * Shared HTTP POST with JSON decoding and error normalisation.
	 *
	 * @param string $url     Endpoint.
	 * @param array  $headers Headers.
	 * @param array  $body    Body.
	 * @return array|WP_Error Decoded body.
	 */
	private static function post_json( $url, $headers, $body ) {
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			/* translators: %d is the HTTP status code. */
			return new WP_Error( 'aigs_api', sprintf( __( 'The AI provider returned an unreadable response (HTTP %d).', 'omnigeo-ai-seo' ), $code ) );
		}
		if ( isset( $data['error'] ) ) {
			$msg = is_array( $data['error'] ) && isset( $data['error']['message'] ) ? $data['error']['message'] : ( is_string( $data['error'] ) ? $data['error'] : '' );
			/* translators: %d is the HTTP status code. */
			return new WP_Error( 'aigs_api', $msg ? $msg : sprintf( __( 'The AI provider returned HTTP %d.', 'omnigeo-ai-seo' ), $code ) );
		}
		if ( $code >= 400 ) {
			/* translators: %d is the HTTP status code. */
			return new WP_Error( 'aigs_api', sprintf( __( 'The AI provider returned HTTP %d.', 'omnigeo-ai-seo' ), $code ) );
		}
		return $data;
	}

	/**
	 * OpenAI-compatible chat completions (OpenAI, Perplexity).
	 *
	 * @param string $url    Endpoint.
	 * @param string $key    Key.
	 * @param string $model  Model.
	 * @param string $prompt Prompt.
	 * @param array  $args   Args.
	 * @return array|WP_Error
	 */
	private static function chat_completions( $url, $key, $model, $prompt, $args ) {
		$messages = array();
		if ( '' !== $args['system'] ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $args['system'],
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);
		$data = self::post_json(
			$url,
			array( 'Authorization' => 'Bearer ' . $key ),
			array(
				'model'      => $model,
				'messages'   => $messages,
				'max_tokens' => (int) $args['max_tokens'],
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = isset( $data['choices'][0]['message']['content'] ) ? (string) $data['choices'][0]['message']['content'] : '';

		// Perplexity returns its sources as `citations` (URLs) and/or `search_results`.
		$cites = array();
		if ( ! empty( $data['citations'] ) && is_array( $data['citations'] ) ) {
			foreach ( $data['citations'] as $c ) {
				if ( is_string( $c ) ) {
					$cites[] = $c;
				} elseif ( is_array( $c ) && ! empty( $c['url'] ) ) {
					$cites[] = $c['url'];
				}
			}
		}
		if ( ! empty( $data['search_results'] ) && is_array( $data['search_results'] ) ) {
			foreach ( $data['search_results'] as $r ) {
				if ( is_array( $r ) && ! empty( $r['url'] ) ) {
					$cites[] = $r['url'];
				}
			}
		}

		return array(
			'text'      => $text,
			'model'     => isset( $data['model'] ) ? (string) $data['model'] : $model,
			'citations' => self::clean_urls( $cites ),
		);
	}

	/**
	 * Anthropic Messages API.
	 *
	 * @param string $key    Key.
	 * @param string $model  Model.
	 * @param string $prompt Prompt.
	 * @param array  $args   Args.
	 * @return array|WP_Error
	 */
	private static function anthropic( $key, $model, $prompt, $args ) {
		$body = array(
			'model'      => $model,
			// Current models think adaptively before answering; leave room for both.
			'max_tokens' => max( 4096, (int) $args['max_tokens'] ),
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);
		// Short answers do not need deep reasoning. Effort only exists on 5.x+ models.
		if ( preg_match( '/^claude-(opus|sonnet|haiku|fable)-[5-9]/', $model ) ) {
			$body['output_config'] = array( 'effort' => 'low' );
		}
		if ( '' !== $args['system'] ) {
			$body['system'] = $args['system'];
		}
		$data = self::post_json(
			'https://api.anthropic.com/v1/messages',
			array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			$body
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'] ) {
			return new WP_Error( 'aigs_refusal', __( 'The model declined to answer this prompt.', 'omnigeo-ai-seo' ) );
		}
		// Newer models may return thinking blocks before the answer; join text blocks only.
		$text = '';
		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}
		return array(
			'text'      => $text,
			'model'     => isset( $data['model'] ) ? (string) $data['model'] : $model,
			'citations' => array(),
		);
	}

	/**
	 * Validate, de-duplicate and cap a URL list.
	 *
	 * @param array $urls URLs.
	 * @return array
	 */
	private static function clean_urls( $urls ) {
		$out = array();
		foreach ( (array) $urls as $u ) {
			$u = esc_url_raw( trim( (string) $u ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				$out[ $u ] = true;
			}
		}
		return array_slice( array_keys( $out ), 0, 30 );
	}

	/**
	 * Extract URLs mentioned inside answer text (for non-grounded providers).
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function urls_in_text( $text ) {
		if ( ! preg_match_all( '#https?://[^\s<>"\')\]]+#iu', (string) $text, $m ) ) {
			return array();
		}
		$urls = array_map(
			static function ( $u ) {
				return rtrim( $u, '.,;:!?،' );
			},
			$m[0]
		);
		return self::clean_urls( $urls );
	}

	/**
	 * Registrable-ish host of a URL without "www.".
	 *
	 * @param string $url URL or host.
	 * @return string
	 */
	public static function host( $url ) {
		$h = wp_parse_url( false === strpos( $url, '://' ) ? 'https://' . $url : $url, PHP_URL_HOST );
		$h = strtolower( (string) $h );
		return preg_replace( '/^www\./', '', $h );
	}

	/**
	 * Does a URL/host belong to a domain (exact or subdomain)?
	 *
	 * @param string $url    URL or host.
	 * @param string $domain Domain.
	 * @return bool
	 */
	public static function host_matches( $url, $domain ) {
		$h = self::host( $url );
		$d = self::host( $domain );
		if ( '' === $h || '' === $d ) {
			return false;
		}
		return $h === $d || substr( $h, -strlen( '.' . $d ) ) === '.' . $d;
	}
}
