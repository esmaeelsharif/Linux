<?php
/**
 * Versioned rule engine for the site-wide AEO / GEO scan.
 *
 * Every rule has an id, a severity, a plain-language explanation of why it
 * matters and how to fix it. Rules never fire for things that are optional
 * (a page without FAQ markup is not "broken"), and intentional choices can be
 * ignored from the Action Center.
 *
 * Groups:
 *  - page  : evaluated from one post's own data.
 *  - graph : need whole-site data (inbound links, duplicates, AI bot reads).
 *  - site  : site-level settings (post_id 0).
 *  - vis   : AI visibility gaps (post_id 0, created by the visibility module).
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule catalogue + evaluators.
 */
class AIGS_Rules {

	/**
	 * Bump when rule logic changes so cached pages are re-evaluated.
	 */
	const VERSION = '1.1.0';

	const LOW      = 1;
	const MEDIUM   = 2;
	const HIGH     = 3;
	const CRITICAL = 4;

	/**
	 * Rule catalogue.
	 *
	 * @return array
	 */
	public static function catalog() {
		static $cat = null;
		if ( null !== $cat ) {
			return $cat;
		}
		$cat = array(
			/* ---------- Site ---------- */
			'site_noindex'          => array( 'site', self::CRITICAL, 'technical', __( 'Your whole site is hidden from search engines', 'omnigeo-ai-seo' ), __( 'Settings → Reading → “Discourage search engines” is on. Search engines, and the AI answers built on their indexes, will drop your pages.', 'omnigeo-ai-seo' ), __( 'Untick “Discourage search engines from indexing this site” unless this is a staging site.', 'omnigeo-ai-seo' ), 'settings:options-reading.php' ),
			'site_plain_permalinks' => array( 'site', self::MEDIUM, 'technical', __( 'Use readable permalinks', 'omnigeo-ai-seo' ), __( 'URLs like ?p=123 say nothing about the page and are harder to cite.', 'omnigeo-ai-seo' ), __( 'Choose “Post name” under Settings → Permalinks.', 'omnigeo-ai-seo' ), 'settings:options-permalink.php' ),
			'site_entity_incomplete' => array( 'site', self::MEDIUM, 'trust', __( 'Complete your brand entity profile', 'omnigeo-ai-seo' ), __( 'Assistants decide who you are from consistent identity data. Your Organization profile is missing a logo or sameAs links to official profiles.', 'omnigeo-ai-seo' ), __( 'Add your logo and links to official profiles (LinkedIn, Instagram, Wikipedia/Wikidata if you have them) in Settings.', 'omnigeo-ai-seo' ), 'settings:aigs-settings' ),
			'site_local_incomplete' => array( 'site', self::MEDIUM, 'trust', __( 'Complete your local business details', 'omnigeo-ai-seo' ), __( 'People ask assistants “near me”, “open now” and “phone number” questions. Without an address, phone and opening hours in your LocalBusiness data, assistants guess or use outdated directory data.', 'omnigeo-ai-seo' ), __( 'Fill in the address, phone and opening hours under Settings → Local business details. Keep them identical to your Google Business Profile and other listings.', 'omnigeo-ai-seo' ), 'settings:aigs-settings#aigs-local' ),
			'site_ai_blocked'       => array( 'site', self::HIGH, 'technical', __( 'AI search crawlers are blocked', 'omnigeo-ai-seo' ), __( 'Your robots.txt rule blocks all AI crawlers, including the search bots that power ChatGPT search and Perplexity answers.', 'omnigeo-ai-seo' ), __( 'If you want to appear in AI answers but not in training data, choose “Allow AI search, block AI training” in Settings.', 'omnigeo-ai-seo' ), 'settings:aigs-settings' ),
			'site_meta_fallback'    => array( 'site', self::MEDIUM, 'technical', __( 'Your pages have no meta descriptions', 'omnigeo-ai-seo' ), __( 'No SEO plugin is active, so WordPress prints no meta description. Search engines and assistants then guess a summary.', 'omnigeo-ai-seo' ), __( 'Turn on the automatic meta description (built from each excerpt or opening paragraph).', 'omnigeo-ai-seo' ), 'auto:enable_meta_fallback' ),
			'edge_blocks_ai'        => array( 'site', self::HIGH, 'technical', __( 'Your CDN or firewall blocks AI crawlers', 'omnigeo-ai-seo' ), __( 'A test request to your homepage with an AI search crawler’s user agent was blocked or challenged, while a normal browser request worked. Your robots.txt may allow these bots, but they never reach your pages, so AI search cannot read or cite you.', 'omnigeo-ai-seo' ), __( 'Allow the AI search crawlers you want in your CDN, firewall or security plugin. The details below name the edge network that answered.', 'omnigeo-ai-seo' ), '' ),
			'ai_bots_absent'        => array( 'site', self::MEDIUM, 'technical', __( 'No AI search crawler has visited in 3 weeks', 'omnigeo-ai-seo' ), __( 'Tracking has been on for over three weeks and not a single AI search crawler or assistant fetch was recorded. Most often a CDN, firewall or security plugin blocks them before WordPress sees the request.', 'omnigeo-ai-seo' ), __( 'Check your CDN, firewall and security plugin for bot blocking, and make sure the AI search crawlers you want are allowed.', 'omnigeo-ai-seo' ), '' ),
			'bot_404'               => array( 'site', self::HIGH, 'technical', __( 'AI bots are hitting missing pages', 'omnigeo-ai-seo' ), __( 'AI crawlers and assistants requested URLs on your site that return “not found”. These are often old links that assistants still remember.', 'omnigeo-ai-seo' ), __( 'Redirect each URL to the closest live page (for example with a redirect plugin) or restore the content.', 'omnigeo-ai-seo' ), '' ),

			/* ---------- Page: indexability ---------- */
			'noindex'               => array( 'page', self::HIGH, 'technical', __( 'Page is set to noindex', 'omnigeo-ai-seo' ), __( 'Your SEO plugin tells search engines not to index this page, so search-based AI answers will not find it.', 'omnigeo-ai-seo' ), __( 'If this page should be found, remove noindex in your SEO plugin. If it is intentional, ignore this item.', 'omnigeo-ai-seo' ), 'edit' ),
			'canonical_elsewhere'   => array( 'page', self::MEDIUM, 'technical', __( 'Canonical points to another URL', 'omnigeo-ai-seo' ), __( 'This page declares a different URL as the original, so engines will index and cite that one instead.', 'omnigeo-ai-seo' ), __( 'Clear the custom canonical unless the duplicate is intentional.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Page: metadata ---------- */
			'title_length'          => array( 'page', self::LOW, 'technical', __( 'Improve the SEO title length', 'omnigeo-ai-seo' ), __( 'Titles that are very short say little; very long ones get cut off in results and answer cards.', 'omnigeo-ai-seo' ), __( 'Aim for roughly 30–65 characters that name the topic clearly.', 'omnigeo-ai-seo' ), 'edit' ),
			'meta_desc_missing'     => array( 'page', self::MEDIUM, 'technical', __( 'Write a meta description', 'omnigeo-ai-seo' ), __( 'Without one, engines invent a summary from random text on the page.', 'omnigeo-ai-seo' ), __( 'Write a 120–160 character summary in your SEO plugin, or add an excerpt.', 'omnigeo-ai-seo' ), 'edit' ),
			'meta_desc_length'      => array( 'page', self::LOW, 'technical', __( 'Adjust meta description length', 'omnigeo-ai-seo' ), __( 'The description is too short to be useful or too long to be shown fully.', 'omnigeo-ai-seo' ), __( 'Aim for roughly 80–160 characters.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Page: answers (AEO) ---------- */
			'no_direct_answer'      => array( 'page', self::MEDIUM, 'answers', __( 'Add a direct answer at the top', 'omnigeo-ai-seo' ), __( 'Answer engines quote short, self-contained passages. This page does not answer its main question in the opening.', 'omnigeo-ai-seo' ), __( 'Open with 40–60 words that answer the main question directly. The Answer Optimizer in the editor can draft it from your own text.', 'omnigeo-ai-seo' ), 'edit' ),
			'filler_opening'        => array( 'page', self::MEDIUM, 'answers', __( 'Replace the introduction with an answer', 'omnigeo-ai-seo' ), __( 'The page opens with filler such as “In this article…” or “Today we will look at…”, which assistants skip.', 'omnigeo-ai-seo' ), __( 'Move the introduction down and start with the answer itself.', 'omnigeo-ai-seo' ), 'edit' ),
			'unanswered_questions'  => array( 'page', self::LOW, 'answers', __( 'Answer question headings directly', 'omnigeo-ai-seo' ), __( 'Some headings ask a question but the text under them does not answer it straight away.', 'omnigeo-ai-seo' ), __( 'Make the first sentence under each question heading the answer.', 'omnigeo-ai-seo' ), 'edit' ),
			'thin_content'          => array( 'page', self::MEDIUM, 'answers', __( 'Expand thin content', 'omnigeo-ai-seo' ), __( 'Very short pages rarely contain enough facts to be trusted or quoted.', 'omnigeo-ai-seo' ), __( 'Add the facts, steps, examples and specifics a buyer or reader would need.', 'omnigeo-ai-seo' ), 'edit' ),
			'no_structure'          => array( 'page', self::MEDIUM, 'answers', __( 'Break long content into sections', 'omnigeo-ai-seo' ), __( 'Long text without H2 sections is hard to split into quotable answers.', 'omnigeo-ai-seo' ), __( 'Add descriptive H2 headings, ideally phrased the way people ask.', 'omnigeo-ai-seo' ), 'edit' ),
			'wall_of_text'          => array( 'page', self::LOW, 'answers', __( 'Split very long paragraphs', 'omnigeo-ai-seo' ), __( 'Paragraphs over ~120 words mix several ideas, so no single passage answers one question.', 'omnigeo-ai-seo' ), __( 'Keep one idea per paragraph, about 40–80 words.', 'omnigeo-ai-seo' ), 'edit' ),
			'comparison_no_table'   => array( 'page', self::MEDIUM, 'answers', __( 'Add a comparison table', 'omnigeo-ai-seo' ), __( 'The title promises a comparison or “best of”, but there is no table. Tables are the easiest format for assistants to read and reuse.', 'omnigeo-ai-seo' ), __( 'Add a table comparing the options on real criteria (price, features, pros and cons).', 'omnigeo-ai-seo' ), 'edit' ),
			'howto_no_steps'        => array( 'page', self::LOW, 'answers', __( 'List the steps in order', 'omnigeo-ai-seo' ), __( 'How-to content is quoted step by step. Without a numbered list the steps are hard to extract.', 'omnigeo-ai-seo' ), __( 'Put the steps in a numbered list.', 'omnigeo-ai-seo' ), 'edit' ),
			'outdated_year'         => array( 'page', self::MEDIUM, 'answers', __( 'Update the outdated year in the title', 'omnigeo-ai-seo' ), __( 'A past year in the title tells readers and engines the information may be stale.', 'omnigeo-ai-seo' ), __( 'Refresh the facts, then update the year (or remove it).', 'omnigeo-ai-seo' ), 'edit' ),
			'stale_content'         => array( 'page', self::LOW, 'answers', __( 'Refresh content that has not been updated in a long time', 'omnigeo-ai-seo' ), __( 'Time-sensitive pages that have not changed in over a year lose trust.', 'omnigeo-ai-seo' ), __( 'Review facts, prices and examples, then update the page.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Page: trust ---------- */
			'author_profile'        => array( 'page', self::LOW, 'trust', __( 'Complete the author profile', 'omnigeo-ai-seo' ), __( 'The author has no bio or job title, so there is no visible expertise behind the content.', 'omnigeo-ai-seo' ), __( 'Add a short bio and the OmniGEO author fields (job title, LinkedIn) to the user profile.', 'omnigeo-ai-seo' ), 'author' ),
			'img_alt_missing'       => array( 'page', self::LOW, 'trust', __( 'Add alt text to images', 'omnigeo-ai-seo' ), __( 'Images without alt text are invisible to crawlers and screen readers.', 'omnigeo-ai-seo' ), __( 'Describe what each image shows in its alt text.', 'omnigeo-ai-seo' ), 'edit' ),
			'no_featured_image'     => array( 'page', self::LOW, 'trust', __( 'Add a featured image', 'omnigeo-ai-seo' ), __( 'A featured image is used in Article schema, link previews and some AI answer cards.', 'omnigeo-ai-seo' ), __( 'Set a relevant featured image.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Page: schema ---------- */
			'faq_empty'             => array( 'page', self::MEDIUM, 'schema', __( 'FAQ schema selected but empty', 'omnigeo-ai-seo' ), __( 'The page is set to FAQ schema but has no questions, so nothing is output.', 'omnigeo-ai-seo' ), __( 'Add questions in the OmniGEO Schema box, or set the type back to None.', 'omnigeo-ai-seo' ), 'edit' ),
			'faq_duplicate'         => array( 'page', self::MEDIUM, 'schema', __( 'Duplicate FAQ markup', 'omnigeo-ai-seo' ), __( 'This page has an FAQ block from your SEO plugin and OmniGEO FAQ schema. OmniGEO now skips its copy, but the settings disagree.', 'omnigeo-ai-seo' ), __( 'Keep one FAQ source: set the OmniGEO schema type to None.', 'omnigeo-ai-seo' ), 'edit' ),
			'howto_incomplete'      => array( 'page', self::LOW, 'schema', __( 'HowTo schema has no steps', 'omnigeo-ai-seo' ), __( 'HowTo markup without steps is incomplete, and Google no longer shows HowTo rich results.', 'omnigeo-ai-seo' ), __( 'Set the schema type to None and use a numbered list in the content instead.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Graph ---------- */
			'orphan'                => array( 'graph', self::MEDIUM, 'links', __( 'Link to orphan pages', 'omnigeo-ai-seo' ), __( 'No other page on your site links here. Crawlers find it late and treat it as unimportant.', 'omnigeo-ai-seo' ), __( 'Add a link from 2–3 related pages. Suggestions are listed for each page.', 'omnigeo-ai-seo' ), 'edit' ),
			'no_internal_links'     => array( 'graph', self::LOW, 'links', __( 'Add internal links', 'omnigeo-ai-seo' ), __( 'This page links to no other page on your site, which breaks the topic connections engines use.', 'omnigeo-ai-seo' ), __( 'Link to 2–3 related pages. Suggestions are listed for each page.', 'omnigeo-ai-seo' ), 'edit' ),
			'duplicate_title'       => array( 'graph', self::MEDIUM, 'technical', __( 'Make duplicate titles unique', 'omnigeo-ai-seo' ), __( 'Several pages share the same title, so engines cannot tell which one answers the query.', 'omnigeo-ai-seo' ), __( 'Give each page a title that states its specific angle.', 'omnigeo-ai-seo' ), 'edit' ),
			'duplicate_desc'        => array( 'graph', self::LOW, 'technical', __( 'Make duplicate meta descriptions unique', 'omnigeo-ai-seo' ), __( 'Several pages share one meta description.', 'omnigeo-ai-seo' ), __( 'Write a specific description for each page.', 'omnigeo-ai-seo' ), 'edit' ),
			'ai_read_weak'          => array( 'graph', self::HIGH, 'answers', __( 'Improve pages AI assistants are already reading', 'omnigeo-ai-seo' ), __( 'Assistants such as ChatGPT or Perplexity fetched this page for a real user in the last 30 days, but it scores low for answer readiness. These are your fastest wins.', 'omnigeo-ai-seo' ), __( 'Open the page and follow the Answer Optimizer tips at the top of the editor.', 'omnigeo-ai-seo' ), 'edit' ),

			/* ---------- Products ---------- */
			'product_short_desc'    => array( 'page', self::MEDIUM, 'products', __( 'Write product short descriptions', 'omnigeo-ai-seo' ), __( 'The short description is the one-line summary shopping assistants lift. It is missing.', 'omnigeo-ai-seo' ), __( 'Write 20–60 words on what it is, who it is for and the key spec. Bulk tools are in Products.', 'omnigeo-ai-seo' ), 'products' ),
			'product_thin_desc'     => array( 'page', self::MEDIUM, 'products', __( 'Expand thin product descriptions', 'omnigeo-ai-seo' ), __( 'Shoppers ask assistants detailed questions. A thin description gives them nothing to answer with.', 'omnigeo-ai-seo' ), __( 'Describe benefits, use cases, materials, sizes and compatibility.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_attributes'    => array( 'page', self::MEDIUM, 'products', __( 'Add product specifications', 'omnigeo-ai-seo' ), __( 'Without attributes (size, material, brand, compatibility) assistants cannot match the product to specific requests or comparisons.', 'omnigeo-ai-seo' ), __( 'Add at least 2–3 attributes under Product data → Attributes.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_image'         => array( 'page', self::MEDIUM, 'products', __( 'Add product images', 'omnigeo-ai-seo' ), __( 'Products without an image are rarely shown in shopping answers.', 'omnigeo-ai-seo' ), __( 'Add a clear product image.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_image_alt'     => array( 'page', self::LOW, 'products', __( 'Add alt text to product images', 'omnigeo-ai-seo' ), __( 'The main product image has no alt text.', 'omnigeo-ai-seo' ), __( 'Use the bulk “alt text from product name” fix in Products.', 'omnigeo-ai-seo' ), 'products' ),
			'product_no_category'   => array( 'page', self::LOW, 'products', __( 'Categorize products', 'omnigeo-ai-seo' ), __( 'Uncategorized products are hard to place in context.', 'omnigeo-ai-seo' ), __( 'Assign a specific product category.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_no_price'      => array( 'page', self::HIGH, 'products', __( 'Products without a price', 'omnigeo-ai-seo' ), __( 'A published product without a price produces incomplete Product schema and cannot appear in price comparisons.', 'omnigeo-ai-seo' ), __( 'Set a regular price, or hide the product if it is not for sale.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_no_gtin'       => array( 'page', self::LOW, 'products', __( 'Add GTIN / barcode identifiers', 'omnigeo-ai-seo' ), __( 'GTIN, EAN or ISBN identifiers let engines match your product to the same item elsewhere.', 'omnigeo-ai-seo' ), __( 'Fill “GTIN, UPC, EAN or ISBN” under Product data → Inventory where you have one.', 'omnigeo-ai-seo' ), 'edit' ),
			'product_duplicate_desc' => array( 'graph', self::MEDIUM, 'products', __( 'Rewrite copied product descriptions', 'omnigeo-ai-seo' ), __( 'Several products share the same description, so none of them stands out as the answer.', 'omnigeo-ai-seo' ), __( 'Describe what makes each product different.', 'omnigeo-ai-seo' ), 'edit' ),
		);
		return $cat;
	}

	/**
	 * Severity labels.
	 *
	 * @return array
	 */
	public static function severities() {
		return array(
			self::CRITICAL => __( 'Critical', 'omnigeo-ai-seo' ),
			self::HIGH     => __( 'High', 'omnigeo-ai-seo' ),
			self::MEDIUM   => __( 'Medium', 'omnigeo-ai-seo' ),
			self::LOW      => __( 'Low', 'omnigeo-ai-seo' ),
		);
	}

	/**
	 * Category labels.
	 *
	 * @return array
	 */
	public static function categories() {
		return array(
			'answers'    => __( 'Answers & content', 'omnigeo-ai-seo' ),
			'technical'  => __( 'Technical', 'omnigeo-ai-seo' ),
			'trust'      => __( 'Trust & entity', 'omnigeo-ai-seo' ),
			'links'      => __( 'Internal links', 'omnigeo-ai-seo' ),
			'schema'     => __( 'Schema', 'omnigeo-ai-seo' ),
			'products'   => __( 'Products', 'omnigeo-ai-seo' ),
			'visibility' => __( 'AI visibility', 'omnigeo-ai-seo' ),
		);
	}

	/**
	 * Rule meta as an associative array (handles dynamic vis_* ids).
	 *
	 * @param string $rule Rule id.
	 * @return array|null
	 */
	public static function meta( $rule ) {
		if ( 0 === strpos( $rule, 'vis_gap_' ) ) {
			return array(
				'grp'   => 'vis',
				'sev'   => self::MEDIUM,
				'cat'   => 'visibility',
				'title' => __( 'Competitors appear in AI answers where you do not', 'omnigeo-ai-seo' ),
				'why'   => __( 'In your latest AI visibility check, the assistant named competitors for this question but not your brand.', 'omnigeo-ai-seo' ),
				'fix'   => __( 'Create or improve a page that answers this exact question, with a direct answer, specifics and proof. Then link to it from related pages.', 'omnigeo-ai-seo' ),
				'act'   => 'draft',
			);
		}
		if ( 0 === strpos( $rule, 'vis_fact_' ) ) {
			return array(
				'grp'   => 'vis',
				'sev'   => self::HIGH,
				'cat'   => 'visibility',
				'title' => __( 'AI answers state wrong facts about you', 'omnigeo-ai-seo' ),
				'why'   => __( 'An assistant mentioned your brand with details that do not match your official facts, such as an old phone number, an unofficial website or a founding year. People act on these answers.', 'omnigeo-ai-seo' ),
				'fix'   => __( 'Correct the detail everywhere it appears: your site, schema, Google Business Profile and other listings, directories and old press pages. A short “key facts” page helps assistants find the right version.', 'omnigeo-ai-seo' ),
				'act'   => 'facts',
			);
		}
		$cat = self::catalog();
		if ( ! isset( $cat[ $rule ] ) ) {
			return null;
		}
		$r = $cat[ $rule ];
		return array(
			'grp'   => $r[0],
			'sev'   => $r[1],
			'cat'   => $r[2],
			'title' => $r[3],
			'why'   => $r[4],
			'fix'   => $r[5],
			'act'   => $r[6],
		);
	}

	/**
	 * Rule ids belonging to a group.
	 *
	 * @param string $grp Group.
	 * @return array
	 */
	public static function ids( $grp ) {
		$out = array();
		foreach ( self::catalog() as $id => $r ) {
			if ( $r[0] === $grp ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Evaluate page rules for one post context (see AIGS_Scanner::context()).
	 *
	 * @param array $c Context.
	 * @return array rule => evidence array
	 */
	public static function evaluate_page( $c ) {
		$f = array();
		$s = $c['structure'];
		$a = $c['answer'];

		// Indexability.
		if ( $c['noindex'] ) {
			$f['noindex'] = array();
		}
		if ( '' !== $c['canonical'] && ! self::same_url( $c['canonical'], $c['url'] ) ) {
			$f['canonical_elsewhere'] = array( 'canonical' => $c['canonical'] );
		}

		// Metadata.
		$tl = function_exists( 'mb_strlen' ) ? mb_strlen( $c['seo_title'], 'UTF-8' ) : strlen( $c['seo_title'] );
		if ( $tl > 0 && ( $tl < 15 || $tl > 70 ) ) {
			$f['title_length'] = array( 'chars' => $tl );
		}
		if ( $c['has_seo_plugin'] ) {
			$dl = function_exists( 'mb_strlen' ) ? mb_strlen( $c['meta_desc'], 'UTF-8' ) : strlen( $c['meta_desc'] );
			if ( 0 === $dl && ! $c['low_priority'] && ! $c['seo_auto_desc'] ) {
				$f['meta_desc_missing'] = array();
			} elseif ( $dl > 0 && ( $dl < 50 || $dl > 170 ) ) {
				$f['meta_desc_length'] = array( 'chars' => $dl );
			}
		}

		// Content rules skip boilerplate pages (contact, privacy, cart…) and products
		// (products have their own rules below).
		if ( ! $c['low_priority'] && ! $c['is_product'] ) {
			$words = (int) $s['words'];
			if ( $words < 150 ) {
				$f['thin_content'] = array( 'words' => $words );
			} elseif ( $words < 300 && 'post' === $c['type'] ) {
				$f['thin_content'] = array( 'words' => $words );
			}

			if ( 'filler' === $a['reason'] ) {
				$f['filler_opening'] = array( 'lead' => wp_trim_words( $a['lead'], 18, '…' ) );
			} elseif ( $words >= 150 && in_array( $a['reason'], array( 'missing', 'off_topic', 'long' ), true ) ) {
				$f['no_direct_answer'] = array(
					'question' => $a['question']['text'],
					'reason'   => $a['reason'],
				);
			}
			if ( $a['unanswered'] ) {
				$f['unanswered_questions'] = array( 'questions' => array_slice( $a['unanswered'], 0, 5 ) );
			}
			$h2 = 0;
			foreach ( $s['headings'] as $h ) {
				if ( 'h2' === $h['tag'] ) {
					$h2++;
				}
			}
			if ( $words >= 400 && 0 === $h2 ) {
				$f['no_structure'] = array( 'words' => $words );
			}
			$walls = 0;
			foreach ( $s['paragraphs'] as $p ) {
				if ( AIGS_Text::word_count( $p ) > 120 ) {
					$walls++;
				}
			}
			if ( $walls >= 2 ) {
				$f['wall_of_text'] = array( 'count' => $walls );
			}
			if ( $a['intent']['comparison'] && ! $a['has_table'] && $words >= 200 ) {
				$f['comparison_no_table'] = array();
			}
			if ( $a['intent']['howto'] && ! $a['has_steps'] && $words >= 200 ) {
				$f['howto_no_steps'] = array();
			}
			$year = AIGS_Text::outdated_year( $c['title'] );
			if ( $year ) {
				$f['outdated_year'] = array( 'year' => $year );
			}
			$age_days = (int) floor( ( time() - $c['modified'] ) / DAY_IN_SECONDS );
			if ( ! $year && 'post' === $c['type'] && $age_days > 365 && ( $a['intent']['comparison'] || preg_match( '/(price|cost|review|قیمت|هزینه|بررسی|خرید)/u', AIGS_Text::normalize( $c['title'] ) ) ) ) {
				$f['stale_content'] = array( 'days' => $age_days );
			}

			if ( 'post' === $c['type'] && ! $c['author_complete'] ) {
				$f['author_profile'] = array( 'author_id' => $c['author_id'] );
			}
			if ( 'post' === $c['type'] && ! $c['thumb_id'] ) {
				$f['no_featured_image'] = array();
			}
		}

		$missing_alt = 0;
		foreach ( $s['images'] as $img ) {
			if ( '' === $img['alt'] ) {
				$missing_alt++;
			}
		}
		if ( $missing_alt > 0 ) {
			$f['img_alt_missing'] = array( 'count' => $missing_alt );
		}

		// Schema.
		if ( 'FAQPage' === $c['schema_type'] ) {
			if ( 0 === $c['faq_count'] ) {
				$f['faq_empty'] = array();
			} elseif ( $s['faq_blocks'] ) {
				$f['faq_duplicate'] = array();
			}
		} elseif ( 'HowTo' === $c['schema_type'] ) {
			$f['howto_incomplete'] = array();
		}

		// Products.
		if ( $c['is_product'] && ! empty( $c['product'] ) ) {
			$p = $c['product'];
			if ( 0 === $p['short_words'] ) {
				$f['product_short_desc'] = array();
			}
			if ( $p['long_words'] < 60 ) {
				$f['product_thin_desc'] = array( 'words' => $p['long_words'] );
			}
			if ( $p['attributes'] < 2 ) {
				$f['product_attributes'] = array( 'count' => $p['attributes'] );
			}
			if ( ! $p['image'] ) {
				$f['product_image'] = array();
			} elseif ( ! $p['image_alt'] ) {
				$f['product_image_alt'] = array();
			}
			if ( ! $p['categorized'] ) {
				$f['product_no_category'] = array();
			}
			if ( ! $p['has_price'] ) {
				$f['product_no_price'] = array();
			}
			if ( $p['gtin_supported'] && ! $p['gtin'] ) {
				$f['product_no_gtin'] = array();
			}
		}

		return $f;
	}

	/**
	 * Page answer-readiness score (0-100) from its findings.
	 *
	 * @param array $rules Rule ids that fired.
	 * @return int
	 */
	public static function page_score( $rules ) {
		$penalty = array(
			self::CRITICAL => 35,
			self::HIGH     => 18,
			self::MEDIUM   => 9,
			self::LOW      => 3,
		);
		$score = 100;
		foreach ( $rules as $rule ) {
			$m = self::meta( $rule );
			if ( $m && 'vis' !== $m['grp'] ) {
				$score -= $penalty[ $m['sev'] ];
			}
		}
		return max( 0, $score );
	}

	/**
	 * Compare URLs ignoring scheme, trailing slash and case.
	 *
	 * @param string $a URL.
	 * @param string $b URL.
	 * @return bool
	 */
	public static function same_url( $a, $b ) {
		$n = static function ( $u ) {
			$u = strtolower( trim( (string) $u ) );
			$u = preg_replace( '#^https?://(www\.)?#', '', $u );
			return rtrim( $u, '/' );
		};
		return $n( $a ) === $n( $b );
	}
}
