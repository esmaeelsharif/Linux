<?php
/**
 * Bring-your-own-key AI client.
 *
 * One place that talks to OpenAI, Anthropic, Perplexity, Google Gemini,
 * OpenRouter, Mistral, DeepSeek or any OpenAI-compatible endpoint (gateways,
 * Azure-style proxies, local Ollama / LM Studio). Returns a uniform result
 * (text, model, citations, whether the answer was web-grounded) and enforces a
 * hard monthly request cap so a schedule can never run up a bill.
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
	 * url      = OpenAI-compatible chat completions endpoint ('' = native API or custom).
	 * grounded = the provider answers with a live web search and returns sources.
	 * keyless  = a key is optional (local servers).
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'openai'     => array(
				'label'    => 'OpenAI (ChatGPT API)',
				'model'    => 'gpt-4o-mini',
				'url'      => 'https://api.openai.com/v1/chat/completions',
				'grounded' => false,
				'keys'     => 'https://platform.openai.com/api-keys',
			),
			'anthropic'  => array(
				'label'    => 'Anthropic (Claude API)',
				'model'    => 'claude-haiku-5-5',
				'url'      => '',
				'grounded' => false,
				'keys'     => 'https://console.anthropic.com/settings/keys',
			),
			'perplexity' => array(
				'label'    => 'Perplexity (Sonar, web search)',
				'model'    => 'sonar',
				'url'      => 'https://api.perplexity.ai/chat/completions',
				'grounded' => true,
				'keys'     => 'https://www.perplexity.ai/settings/api',
			),
			'gemini'     => array(
				'label'    => 'Google Gemini',
				'model'    => 'gemini-2.5-flash',
				'url'      => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
				'grounded' => false,
				'keys'     => 'https://aistudio.google.com/apikey',
			),
			'openrouter' => array(
				'label'    => 'OpenRouter (hundreds of models, one key)',
				'model'    => 'openai/gpt-4o-mini',
				'url'      => 'https://openrouter.ai/api/v1/chat/completions',
				'grounded' => false,
				'keys'     => 'https://openrouter.ai/keys',
			),
			'mistral'    => array(
				'label'    => 'Mistral AI',
				'model'    => 'mistral-small-latest',
				'url'      => 'https://api.mistral.ai/v1/chat/completions',
				'grounded' => false,
				'keys'     => 'https://console.mistral.ai/api-keys',
			),
			'deepseek'   => array(
				'label'    => 'DeepSeek',
				'model'    => 'deepseek-chat',
				'url'      => 'https://api.deepseek.com/chat/completions',
				'grounded' => false,
				'keys'     => 'https://platform.deepseek.com/api_keys',
			),
			'custom'     => array(
				'label'    => __( 'Other OpenAI-compatible endpoint', 'omnigeo-ai-seo' ),
				'model'    => '',
				'url'      => '',
				'grounded' => false,
				'keys'     => '',
				'keyless'  => true,
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
	 * Turn a base URL ("https://host/v1") or a full endpoint into the
	 * chat-completions endpoint. Returns '' for anything that is not http(s).
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function normalize_endpoint( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! preg_match( '#^https?://[^\s/]+#i', $url ) ) {
			return '';
		}
		$url = preg_replace( '#[?\#].*$#', '', $url );
		$url = rtrim( $url, '/' );
		if ( ! preg_match( '#/chat/completions$#', $url ) ) {
			$url .= '/chat/completions';
		}
		return $url;
	}

	/**
	 * Endpoint for the active provider ('' when not applicable / not set).
	 *
	 * @return string
	 */
	public static function endpoint() {
		$p   = self::provider();
		$all = self::providers();
		if ( 'custom' === $p ) {
			return self::normalize_endpoint( aigs_get( 'ai_base_url', '' ) );
		}
		return $all[ $p ]['url'];
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
	 * Is the provider configured well enough to send a request?
	 *
	 * @return bool
	 */
	public static function ready() {
		if ( 'custom' === self::provider() ) {
			return '' !== self::endpoint() && '' !== self::model();
		}
		return '' !== self::key();
	}

	/**
	 * Does the active provider search the live web?
	 *
	 * @return bool
	 */
	public static function grounded() {
		$p = self::provider();
		if ( in_array( $p, array( 'custom', 'openrouter' ), true ) ) {
			// Gateways serve many models; the user says whether theirs searches the web
			// (for example Perplexity models or ":online" variants on OpenRouter).
			return (bool) aigs_get( 'ai_grounded', 0 );
		}
		$all = self::providers();
		return ! empty( $all[ $p ]['grounded'] );
	}

	/**
	 * Human label of the active provider.
	 *
	 * @return string
	 */
	public static function label() {
		$all = self::providers();
		return $all[ self::provider() ]['label'];
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
		$provider = self::provider();
		$key      = self::key();
		if ( ! self::ready() ) {
			if ( 'custom' === $provider ) {
				return new WP_Error( 'aigs_no_key', __( 'Enter the endpoint URL and model of your OpenAI-compatible provider in Settings first.', 'omnigeo-ai-seo' ) );
			}
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

		$model = self::model();
		self::count_usage();

		if ( 'anthropic' === $provider ) {
			$res = self::anthropic( $key, $model, $prompt, $args );
		} else {
			$headers = array();
			if ( '' !== $key ) {
				$headers['Authorization'] = 'Bearer ' . $key;
			}
			if ( 'openrouter' === $provider ) {
				// Optional attribution headers recommended by OpenRouter.
				$headers['HTTP-Referer'] = home_url( '/' );
				$headers['X-Title']      = 'OmniGEO Ai SEO';
			}
			// OpenAI's newer models reject "max_tokens"; every current chat model accepts this name.
			$args['token_param'] = 'openai' === $provider ? 'max_completion_tokens' : 'max_tokens';
			$res                 = self::chat_completions( self::endpoint(), $headers, $model, $prompt, $args );
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
	 * OpenAI-compatible chat completions (OpenAI, Perplexity, Gemini, OpenRouter,
	 * Mistral, DeepSeek, custom).
	 *
	 * @param string $url     Endpoint.
	 * @param array  $headers Auth / extra headers.
	 * @param string $model   Model.
	 * @param string $prompt  Prompt.
	 * @param array  $args    Args.
	 * @return array|WP_Error
	 */
	private static function chat_completions( $url, $headers, $model, $prompt, $args ) {
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
		$param = isset( $args['token_param'] ) ? $args['token_param'] : 'max_tokens';
		$data  = self::post_json(
			$url,
			$headers,
			array(
				'model'    => $model,
				'messages' => $messages,
				$param     => (int) $args['max_tokens'],
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
		if ( is_array( $text ) ) {
			// Some gateways return content parts: [ { type: text, text: … } ].
			$joined = '';
			foreach ( $text as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) ) {
					$joined .= $part['text'];
				}
			}
			$text = $joined;
		}
		$text = (string) $text;
		// Reasoning models served through gateways may wrap their thinking in <think> tags.
		$text = trim( preg_replace( '#<think>.*?</think>#s', '', $text ) );

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
		if ( ! empty( $data['choices'][0]['message']['annotations'] ) && is_array( $data['choices'][0]['message']['annotations'] ) ) {
			// OpenRouter web search and OpenAI search models: url_citation annotations.
			foreach ( $data['choices'][0]['message']['annotations'] as $an ) {
				if ( isset( $an['url_citation']['url'] ) ) {
					$cites[] = $an['url_citation']['url'];
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
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_ai_test', array( $this, 'ajax_test' ) );
	}

	/**
	 * AJAX: send a tiny request to confirm the provider, key and model work.
	 */
	public function ajax_test() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'omnigeo-ai-seo' ) ) );
		}
		$start = microtime( true );
		$res   = self::ask(
			'Reply with the single word: OK',
			array( 'max_tokens' => 20 )
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				/* translators: 1: provider label, 2: model, 3: seconds. */
				'message' => sprintf( __( 'Connected to %1$s (%2$s) in %3$s s.', 'omnigeo-ai-seo' ), self::label(), $res['model'], number_format_i18n( microtime( true ) - $start, 1 ) ),
				'left'    => self::remaining(),
			)
		);
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
