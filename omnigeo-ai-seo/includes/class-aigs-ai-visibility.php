<?php
/**
 * Live AI-visibility check.
 *
 * Asks a real LLM (via the site owner's own API key) a set of prompts and
 * detects whether the brand / domain is mentioned in the answer. This is the
 * only module that talks to an external paid API, and it uses YOUR key.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand-mention detection across LLM answers.
 */
class AIGS_AI_Visibility {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'wp_ajax_aigs_ai_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_aigs_prompt_suggest', array( $this, 'ajax_prompts' ) );
	}

	/**
	 * AJAX: suggest prompts a real user might ask.
	 */
	public function ajax_prompts() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		wp_send_json_success( array( 'prompts' => $this->suggested_prompts() ) );
	}

	/**
	 * Build suggested prompts from business profile.
	 *
	 * @return array
	 */
	public function suggested_prompts() {
		$industry = aigs_get( 'industry', '' );
		$location = aigs_get( 'location', '' );
		$name     = aigs_business_name();
		$ind      = $industry ? $industry : __( 'this industry', 'omnigeo-ai-seo' );
		$loc      = $location ? ' in ' . $location : '';

		return array(
			sprintf( 'What are the best %1$s providers%2$s?', $ind, $loc ),
			sprintf( 'Recommend a trustworthy %1$s company%2$s.', $ind, $loc ),
			sprintf( 'Who should I contact for %1$s services%2$s?', $ind, $loc ),
			sprintf( 'Compare top %s options for a small business.', $ind ),
			sprintf( 'Is %s a good choice and what are the alternatives?', $name ),
		);
	}

	/**
	 * AJAX: run a visibility scan for one prompt.
	 */
	public function ajax_scan() {
		check_ajax_referer( 'aigs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		$prompt = isset( $_POST['prompt'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt'] ) ) : '';
		if ( '' === $prompt ) {
			wp_send_json_error( array( 'message' => __( 'Empty prompt.', 'omnigeo-ai-seo' ) ) );
		}
		$result = $this->scan( $prompt );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Run a single prompt against the configured provider and analyze mention.
	 *
	 * @param string $prompt Prompt.
	 * @return array|WP_Error
	 */
	public function scan( $prompt ) {
		$key = aigs_get( 'ai_api_key', '' );
		if ( ! $key ) {
			return new WP_Error( 'no_key', __( 'Add your AI API key in Settings first.', 'omnigeo-ai-seo' ) );
		}
		$provider = aigs_get( 'ai_provider', 'openai' );
		$answer   = $this->ask( $provider, $key, $prompt );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$brand  = aigs_business_name();
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = preg_replace( '/^www\./', '', (string) $domain );

		$mentioned = ( $brand && false !== stripos( $answer, $brand ) );
		$cited     = ( $domain && false !== stripos( $answer, $domain ) );

		return array(
			'prompt'    => $prompt,
			'mentioned' => $mentioned,
			'cited'     => $cited,
			'provider'  => $provider,
			'answer'    => wp_trim_words( wp_strip_all_tags( $answer ), 120 ),
		);
	}

	/**
	 * Dispatch to the right provider.
	 *
	 * @param string $provider openai|anthropic|perplexity.
	 * @param string $key      API key.
	 * @param string $prompt   Prompt.
	 * @return string|WP_Error
	 */
	private function ask( $provider, $key, $prompt ) {
		switch ( $provider ) {
			case 'anthropic':
				return $this->ask_anthropic( $key, $prompt );
			case 'perplexity':
				return $this->ask_perplexity( $key, $prompt );
			case 'openai':
			default:
				return $this->ask_openai( $key, $prompt );
		}
	}

	/**
	 * OpenAI chat completions.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	private function ask_openai( $key, $prompt ) {
		$resp = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => 'gpt-4o-mini',
						'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['error']['message'] ) ) {
			return new WP_Error( 'api', $data['error']['message'] );
		}
		return isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
	}

	/**
	 * Anthropic messages API.
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	private function ask_anthropic( $key, $prompt ) {
		$resp = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 45,
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'claude-3-5-haiku-latest',
						'max_tokens' => 1024,
						'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['error']['message'] ) ) {
			return new WP_Error( 'api', $data['error']['message'] );
		}
		return isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : '';
	}

	/**
	 * Perplexity chat completions (good for "real" web-grounded answers).
	 *
	 * @param string $key    Key.
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	private function ask_perplexity( $key, $prompt ) {
		$resp = wp_remote_post(
			'https://api.perplexity.ai/chat/completions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => 'sonar',
						'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( isset( $data['error']['message'] ) ) {
			return new WP_Error( 'api', $data['error']['message'] );
		}
		return isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
	}
}
