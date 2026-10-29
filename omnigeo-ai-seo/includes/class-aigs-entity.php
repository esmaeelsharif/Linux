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
		// A local business still gets its own LocalBusiness node on the homepage,
		// because general SEO plugins do not output address and opening hours.
		if ( 'off' !== aigs_get( 'dedupe_schema', 'auto' ) && AIGS_SEO::outputs_schema() ) {
			if ( self::is_local() && ( is_front_page() || is_home() ) ) {
				$node = self::local_node_standalone();
				echo "\n<!-- OmniGEO Ai SEO local business -->\n";
				echo '<script type="application/ld+json">' . wp_json_encode( array_merge( array( '@context' => 'https://schema.org' ), $node ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
			}
			return;
		}

		$org_id  = home_url( '/#organization' );
		$site_id = home_url( '/#website' );
		$graph   = array();

		// Organization (site-wide).
		$org = array(
			'@type' => self::schema_type(),
			'@id'   => $org_id,
			'name'  => aigs_business_name(),
			'url'   => home_url( '/' ),
		);
		$org = array_merge( $org, self::contact_fields(), self::is_local() ? self::local_fields() : array() );
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

	/* ============================================================
	 * Business type and local business data
	 * ============================================================ */

	/**
	 * Supported schema.org types: organisations and local businesses.
	 *
	 * @return array{org:array,local:array}
	 */
	public static function business_types() {
		return array(
			'org'   => array(
				'Organization'            => __( 'Organization (general)', 'omnigeo-ai-seo' ),
				'Corporation'             => __( 'Corporation', 'omnigeo-ai-seo' ),
				'OnlineStore'             => __( 'Online store', 'omnigeo-ai-seo' ),
				'NewsMediaOrganization'   => __( 'News / media publisher', 'omnigeo-ai-seo' ),
				'EducationalOrganization' => __( 'Educational organization', 'omnigeo-ai-seo' ),
				'NGO'                     => __( 'Non-profit / NGO', 'omnigeo-ai-seo' ),
			),
			'local' => array(
				'LocalBusiness'               => __( 'Local business (general)', 'omnigeo-ai-seo' ),
				'Store'                       => __( 'Shop / store', 'omnigeo-ai-seo' ),
				'Restaurant'                  => __( 'Restaurant', 'omnigeo-ai-seo' ),
				'CafeOrCoffeeShop'            => __( 'Café / coffee shop', 'omnigeo-ai-seo' ),
				'Dentist'                     => __( 'Dentist', 'omnigeo-ai-seo' ),
				'MedicalClinic'               => __( 'Medical clinic', 'omnigeo-ai-seo' ),
				'Physician'                   => __( 'Physician / doctor', 'omnigeo-ai-seo' ),
				'Pharmacy'                    => __( 'Pharmacy', 'omnigeo-ai-seo' ),
				'VeterinaryCare'              => __( 'Veterinary care', 'omnigeo-ai-seo' ),
				'LegalService'                => __( 'Legal service / law firm', 'omnigeo-ai-seo' ),
				'AccountingService'           => __( 'Accounting service', 'omnigeo-ai-seo' ),
				'FinancialService'            => __( 'Financial service', 'omnigeo-ai-seo' ),
				'InsuranceAgency'             => __( 'Insurance agency', 'omnigeo-ai-seo' ),
				'RealEstateAgent'             => __( 'Real estate agent', 'omnigeo-ai-seo' ),
				'ProfessionalService'         => __( 'Professional service', 'omnigeo-ai-seo' ),
				'HomeAndConstructionBusiness' => __( 'Home services / construction', 'omnigeo-ai-seo' ),
				'Plumber'                     => __( 'Plumber', 'omnigeo-ai-seo' ),
				'Electrician'                 => __( 'Electrician', 'omnigeo-ai-seo' ),
				'HVACBusiness'                => __( 'Heating & air conditioning', 'omnigeo-ai-seo' ),
				'AutoRepair'                  => __( 'Car repair', 'omnigeo-ai-seo' ),
				'AutoDealer'                  => __( 'Car dealer', 'omnigeo-ai-seo' ),
				'BeautySalon'                 => __( 'Beauty salon', 'omnigeo-ai-seo' ),
				'HairSalon'                   => __( 'Hair salon', 'omnigeo-ai-seo' ),
				'DaySpa'                      => __( 'Spa', 'omnigeo-ai-seo' ),
				'HealthClub'                  => __( 'Gym / health club', 'omnigeo-ai-seo' ),
				'Hotel'                       => __( 'Hotel', 'omnigeo-ai-seo' ),
				'LodgingBusiness'             => __( 'Lodging (B&B, hostel…)', 'omnigeo-ai-seo' ),
				'TravelAgency'                => __( 'Travel agency', 'omnigeo-ai-seo' ),
				'ChildCare'                   => __( 'Child care', 'omnigeo-ai-seo' ),
			),
		);
	}

	/**
	 * Saved type, validated (legacy "business_type" values fall back to Organization).
	 *
	 * @return string
	 */
	public static function schema_type() {
		$t     = (string) aigs_get( 'business_type', 'Organization' );
		$types = self::business_types();
		return ( isset( $types['org'][ $t ] ) || isset( $types['local'][ $t ] ) ) ? $t : 'Organization';
	}

	/**
	 * Is the business a local (physical) business?
	 *
	 * @return bool
	 */
	public static function is_local() {
		$types = self::business_types();
		return isset( $types['local'][ self::schema_type() ] );
	}

	/**
	 * Days used for opening hours.
	 *
	 * @return array key => schema.org day
	 */
	public static function days() {
		return array(
			'mo' => 'Monday',
			'tu' => 'Tuesday',
			'we' => 'Wednesday',
			'th' => 'Thursday',
			'fr' => 'Friday',
			'sa' => 'Saturday',
			'su' => 'Sunday',
		);
	}

	/**
	 * Parse "09:00-13:00, 14:00-18:00" (or "24h") into ranges. Invalid parts are dropped.
	 *
	 * @param string $raw Raw text.
	 * @return array [ [open, close] ]
	 */
	public static function parse_ranges( $raw ) {
		$raw = strtolower( trim( strtr( (string) $raw, array( '–' => '-', '—' => '-', '.' => ':' ) ) ) );
		if ( '' === $raw ) {
			return array();
		}
		if ( in_array( $raw, array( '24h', '24/7', '24 h', '00:00-24:00' ), true ) ) {
			return array( array( '00:00', '23:59' ) );
		}
		$out = array();
		foreach ( preg_split( '/[,;]+/', $raw ) as $part ) {
			if ( preg_match( '/^\s*(\d{1,2})(?::(\d{2}))?\s*-\s*(\d{1,2})(?::(\d{2}))?\s*$/', $part, $m ) ) {
				$o  = (int) $m[1];
				$om = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
				$c  = (int) $m[3];
				$cm = isset( $m[4] ) && '' !== $m[4] ? (int) $m[4] : 0;
				if ( $o > 24 || $c > 24 || $om > 59 || $cm > 59 ) {
					continue;
				}
				if ( 24 === $c ) {
					$c  = 23;
					$cm = 59;
				}
				$out[] = array( sprintf( '%02d:%02d', $o % 24, $om ), sprintf( '%02d:%02d', $c, $cm ) );
			}
		}
		return $out;
	}

	/**
	 * openingHoursSpecification grouped by identical hours.
	 *
	 * @param array $hours day key => raw.
	 * @return array
	 */
	public static function opening_hours( $hours ) {
		$groups = array();
		foreach ( self::days() as $k => $day ) {
			$raw = isset( $hours[ $k ] ) ? $hours[ $k ] : '';
			foreach ( self::parse_ranges( $raw ) as $r ) {
				$groups[ $r[0] . '|' . $r[1] ][] = $day;
			}
		}
		$out = array();
		foreach ( $groups as $key => $days ) {
			list( $open, $close ) = explode( '|', $key );
			$out[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => count( $days ) > 1 ? $days : $days[0],
				'opens'     => $open,
				'closes'    => $close,
			);
		}
		return $out;
	}

	/**
	 * Phone and email (any organisation).
	 *
	 * @return array
	 */
	public static function contact_fields() {
		$out   = array();
		$phone = trim( (string) aigs_get( 'biz_phone', '' ) );
		$email = sanitize_email( (string) aigs_get( 'biz_email', '' ) );
		if ( '' !== $phone ) {
			$out['telephone'] = $phone;
		}
		if ( '' !== $email ) {
			$out['email'] = $email;
		}
		return $out;
	}

	/**
	 * Local business properties (address, geo, hours…).
	 *
	 * @return array
	 */
	public static function local_fields() {
		$out  = array();
		$addr = array_filter(
			array(
				'streetAddress'   => trim( (string) aigs_get( 'biz_street', '' ) ),
				'addressLocality' => trim( (string) aigs_get( 'biz_city', '' ) ),
				'addressRegion'   => trim( (string) aigs_get( 'biz_region', '' ) ),
				'postalCode'      => trim( (string) aigs_get( 'biz_postal', '' ) ),
				'addressCountry'  => trim( (string) aigs_get( 'biz_country', '' ) ),
			)
		);
		if ( $addr ) {
			$out['address'] = array_merge( array( '@type' => 'PostalAddress' ), $addr );
		}
		$lat = aigs_get( 'biz_lat', '' );
		$lng = aigs_get( 'biz_lng', '' );
		if ( '' !== (string) $lat && '' !== (string) $lng ) {
			$out['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}
		$hours = self::opening_hours( (array) aigs_get( 'biz_hours', array() ) );
		if ( $hours ) {
			$out['openingHoursSpecification'] = $hours;
		}
		$price = trim( (string) aigs_get( 'biz_price', '' ) );
		if ( '' !== $price ) {
			$out['priceRange'] = $price;
		}
		$area = array_values( array_filter( array_map( 'trim', explode( ',', (string) aigs_get( 'biz_area', '' ) ) ) ) );
		if ( $area ) {
			$out['areaServed'] = count( $area ) > 1 ? $area : $area[0];
		}
		$map = (string) aigs_get( 'biz_map', '' );
		if ( '' !== $map ) {
			$out['hasMap'] = $map;
		}
		$logo = (string) aigs_get( 'org_logo', '' );
		if ( '' !== $logo ) {
			$out['image'] = $logo;
		}
		return $out;
	}

	/**
	 * LocalBusiness node printed next to an SEO plugin's graph.
	 *
	 * @return array
	 */
	public static function local_node_standalone() {
		$node = array(
			'@type' => self::schema_type(),
			'@id'   => home_url( '/#localbusiness' ),
			'name'  => aigs_business_name(),
			'url'   => home_url( '/' ),
		);
		$same = array();
		foreach ( preg_split( '/[,\n]+/u', (string) aigs_get( 'org_sameas', '' ) ) as $u ) {
			$u = trim( $u );
			if ( filter_var( $u, FILTER_VALIDATE_URL ) ) {
				$same[] = $u;
			}
		}
		if ( $same ) {
			$node['sameAs'] = $same;
		}
		return array_merge( $node, self::contact_fields(), self::local_fields() );
	}

	/**
	 * What a local business profile is missing (empty when complete or not local).
	 *
	 * @return array Labels.
	 */
	public static function local_missing() {
		if ( ! self::is_local() ) {
			return array();
		}
		$miss = array();
		if ( '' === trim( (string) aigs_get( 'biz_street', '' ) ) || '' === trim( (string) aigs_get( 'biz_city', '' ) ) ) {
			$miss[] = __( 'address', 'omnigeo-ai-seo' );
		}
		if ( '' === trim( (string) aigs_get( 'biz_phone', '' ) ) ) {
			$miss[] = __( 'phone', 'omnigeo-ai-seo' );
		}
		if ( ! self::opening_hours( (array) aigs_get( 'biz_hours', array() ) ) ) {
			$miss[] = __( 'opening hours', 'omnigeo-ai-seo' );
		}
		return $miss;
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
