<?php
/**
 * Entity & author schema.
 *
 * Outputs a cross-linked JSON-LD @graph so AI engines can resolve identity:
 *  - Organization (site-wide) with logo, foundingDate and sameAs
 *  - WebSite (site-wide), published by the Organization
 *  - Person (the post author) with jobTitle, sameAs and knowsAbout
 *  - Article / WebPage (per post) linked to its author and publisher via @id
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the entity graph and the author profile fields.
 */
class AIGS_Entity {

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( aigs_get( 'enable_schema', 1 ) ) {
			add_action( 'wp_head', array( $this, 'output' ), 5 );
		}
		add_action( 'show_user_profile', array( $this, 'user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );
	}

	/**
	 * Print the entity @graph in <head>.
	 */
	public function output() {
		// Avoid duplicate schema: if an SEO plugin already emits an entity graph,
		// defer to it (unless the user forces our output off this behaviour).
		if ( 'off' !== aigs_get( 'dedupe_schema', 'auto' ) && AIGS_SEO::outputs_schema() ) {
			return;
		}

		$org_id  = home_url( '/#organization' );
		$site_id = home_url( '/#website' );
		$graph   = array();

		// Organization (site-wide).
		$org = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => aigs_business_name(),
			'url'   => home_url( '/' ),
		);
		$logo = aigs_get( 'org_logo', '' );
		if ( $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}
		$founded = aigs_get( 'org_founded', '' );
		if ( $founded ) {
			$org['foundingDate'] = $founded;
		}
		$org_same = $this->urls( aigs_get( 'org_sameas', '' ) );
		if ( $org_same ) {
			$org['sameAs'] = $org_same;
		}
		$graph[] = $org;

		// WebSite (site-wide).
		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => $site_id,
			'name'      => get_bloginfo( 'name' ),
			'url'       => home_url( '/' ),
			'publisher' => array( '@id' => $org_id ),
		);

		// Per-post Person + Article. Skip on WooCommerce product pages, WooCommerce
		// already emits rich Product schema, so an Article node would conflict.
		$is_woo_product = class_exists( 'AIGS_WooCommerce' ) && AIGS_WooCommerce::is_product();
		if ( is_singular() && ! $is_woo_product ) {
			$post_id    = get_queried_object_id();
			$author_id  = (int) get_post_field( 'post_author', $post_id );
			$author_url = get_author_posts_url( $author_id );
			$person_id  = $author_url . '#person';

			$person = array(
				'@type' => 'Person',
				'@id'   => $person_id,
				'name'  => get_the_author_meta( 'display_name', $author_id ),
				'url'   => $author_url,
			);
			$jt = get_user_meta( $author_id, 'aigs_jobtitle', true );
			if ( $jt ) {
				$person['jobTitle'] = $jt;
			}
			$psame = array();
			foreach ( array( 'aigs_linkedin', 'aigs_twitter' ) as $mk ) {
				$v = get_user_meta( $author_id, $mk, true );
				if ( $v && filter_var( $v, FILTER_VALIDATE_URL ) ) {
					$psame[] = $v;
				}
			}
			if ( $psame ) {
				$person['sameAs'] = $psame;
			}
			$ka = get_user_meta( $author_id, 'aigs_knowsabout', true );
			if ( $ka ) {
				$topics = array_filter( array_map( 'trim', preg_split( '/[,\n]+/u', $ka ) ) );
				if ( $topics ) {
					$person['knowsAbout'] = array_values( $topics );
				}
			}
			$graph[] = $person;

			$is_page = is_page( $post_id );
			$article = array(
				'@type'            => $is_page ? 'WebPage' : 'Article',
				'@id'              => get_permalink( $post_id ) . '#article',
				'headline'         => wp_strip_all_tags( get_the_title( $post_id ) ),
				'url'              => get_permalink( $post_id ),
				'mainEntityOfPage' => get_permalink( $post_id ),
				'datePublished'    => get_the_date( 'c', $post_id ),
				'dateModified'     => get_the_modified_date( 'c', $post_id ),
				'author'           => array( '@id' => $person_id ),
				'publisher'        => array( '@id' => $org_id ),
				'isPartOf'         => array( '@id' => $site_id ),
			);

			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				$img = wp_get_attachment_image_url( $thumb_id, 'full' );
				if ( $img ) {
					$article['image'] = $img;
				}
			}

			// Enrich from the per-post Schema meta box.
			$kw = get_post_meta( $post_id, '_aigs_keywords', true );
			if ( $kw ) {
				$article['keywords'] = $kw;
			}
			$about    = get_post_meta( $post_id, '_aigs_about', true );
			$post_url = $this->urls( get_post_meta( $post_id, '_aigs_sameas', true ) );
			if ( $about ) {
				$thing = array(
					'@type' => 'Thing',
					'name'  => $about,
				);
				if ( $post_url ) {
					$thing['sameAs'] = $post_url;
				}
				$article['about'] = $thing;
			} elseif ( $post_url ) {
				$article['mentions'] = array_map(
					static function ( $u ) {
						return array(
							'@type'  => 'Thing',
							'sameAs' => $u,
						);
					},
					$post_url
				);
			}

			if ( '1' === get_post_meta( $post_id, '_aigs_speakable', true ) ) {
				$article['speakable'] = array(
					'@type'       => 'SpeakableSpecification',
					'cssSelector' => array( 'h1', 'p' ),
				);
			}

			$graph[] = $article;
		}

		$json = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		echo "\n<!-- OmniGEO Ai SEO entity graph -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $json, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	/**
	 * Render author entity fields on the user profile screen.
	 *
	 * @param WP_User $user User.
	 */
	public function user_fields( $user ) {
		$jt = get_user_meta( $user->ID, 'aigs_jobtitle', true );
		$li = get_user_meta( $user->ID, 'aigs_linkedin', true );
		$tw = get_user_meta( $user->ID, 'aigs_twitter', true );
		$ka = get_user_meta( $user->ID, 'aigs_knowsabout', true );
		?>
		<h2><?php esc_html_e( 'OmniGEO Author entity', 'omnigeo-ai-seo' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="aigs_jobtitle"><?php esc_html_e( 'Job title', 'omnigeo-ai-seo' ); ?></label></th>
				<td><input type="text" name="aigs_jobtitle" id="aigs_jobtitle" value="<?php echo esc_attr( $jt ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th><label for="aigs_linkedin">LinkedIn URL</label></th>
				<td><input type="url" name="aigs_linkedin" id="aigs_linkedin" value="<?php echo esc_attr( $li ); ?>" class="regular-text" placeholder="https://www.linkedin.com/in/…" /></td>
			</tr>
			<tr>
				<th><label for="aigs_twitter"><?php esc_html_e( 'X / Twitter URL', 'omnigeo-ai-seo' ); ?></label></th>
				<td><input type="url" name="aigs_twitter" id="aigs_twitter" value="<?php echo esc_attr( $tw ); ?>" class="regular-text" placeholder="https://x.com/…" /></td>
			</tr>
			<tr>
				<th><label for="aigs_knowsabout"><?php esc_html_e( 'Knows about (topics, comma separated)', 'omnigeo-ai-seo' ); ?></label></th>
				<td>
					<input type="text" name="aigs_knowsabout" id="aigs_knowsabout" value="<?php echo esc_attr( $ka ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Areas of expertise, strengthens E-E-A-T signals for AI engines.', 'omnigeo-ai-seo' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save author entity fields. Core verifies the profile nonce before these hooks fire.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_user_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// Verify the profile-update nonce (set by WordPress core on the user-edit form).
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, 'aigs_jobtitle', isset( $_POST['aigs_jobtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['aigs_jobtitle'] ) ) : '' );
		update_user_meta( $user_id, 'aigs_linkedin', isset( $_POST['aigs_linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['aigs_linkedin'] ) ) : '' );
		update_user_meta( $user_id, 'aigs_twitter', isset( $_POST['aigs_twitter'] ) ? esc_url_raw( wp_unslash( $_POST['aigs_twitter'] ) ) : '' );
		update_user_meta( $user_id, 'aigs_knowsabout', isset( $_POST['aigs_knowsabout'] ) ? sanitize_text_field( wp_unslash( $_POST['aigs_knowsabout'] ) ) : '' );
	}

	/**
	 * Parse a comma/newline separated string into a clean URL array.
	 *
	 * @param string $raw Raw value.
	 * @return array
	 */
	private function urls( $raw ) {
		if ( ! $raw ) {
			return array();
		}
		$items = array_map( 'trim', preg_split( '/[,\n]+/u', $raw ) );
		$items = array_filter(
			$items,
			static function ( $u ) {
				return filter_var( $u, FILTER_VALIDATE_URL );
			}
		);
		return array_values( $items );
	}
}
