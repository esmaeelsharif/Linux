<?php
/**
 * Logic tests for OmniGEO AI SEO. Run: php tests/run.php
 */

require __DIR__ . '/bootstrap.php';

/* ============================================================
 * AIGS_Text: Persian normalization and matching
 * ============================================================ */
same( 'normalize arabic yeh/kaf', 'کیک', AIGS_Text::normalize( 'كيك' ) );
same( 'normalize persian digits', 'قیمت 1403', AIGS_Text::normalize( 'قیمت ۱۴۰۳' ) );
same( 'normalize zwnj to space', 'می خواهم', AIGS_Text::normalize( "می\u{200C}خواهم" ) );
same( 'normalize diacritics', 'کتاب', AIGS_Text::normalize( 'کِتاب' ) );

check( 'brand match across zwnj/space', AIGS_Text::contains_term( "بهترین فروشگاه دیجی\u{200C}کالا است", 'دیجی کالا' ) );
check( 'brand match glued spelling', AIGS_Text::contains_term( 'خرید از دیجیکالا', 'دیجی کالا' ) );
check( 'brand match arabic yeh variant', AIGS_Text::contains_term( 'خريد از ديجي كالا', 'دیجی‌کالا' ) );
check( 'brand match persian ezafe suffix', AIGS_Text::contains_term( 'محصولات دیجی‌کالای اصلی', 'دیجی‌کالا' ) );
check( 'latin brand bounded', ! AIGS_Text::contains_term( 'I like bananas', 'Ana' ) );
check( 'latin brand case-insensitive', AIGS_Text::contains_term( 'Try ACME tools today', 'acme' ) );
check( 'latin possessive', AIGS_Text::contains_term( "Acme's shop", 'Acme' ) );
same( 'term offset', 4, AIGS_Text::term_offset( 'try acme now', 'Acme' ) );

same( 'word count persian with zwnj', 3, AIGS_Text::word_count( "من می\u{200C}خواهم بخرم" ) );
same( 'word count ignores punctuation', 2, AIGS_Text::word_count( 'سلام - دنیا' ) );
same( 'word count html', 3, AIGS_Text::word_count( '<p>one <b>two</b> three</p>' ) );

check( 'question persian mark', AIGS_Text::is_question( 'بهترین گوشی چیست؟' ) );
check( 'question persian word', AIGS_Text::is_question( 'چطور وردپرس نصب کنیم' ) );
check( 'question persian chist ending', AIGS_Text::is_question( 'سئو چیست' ) );
check( 'question english', AIGS_Text::is_question( 'How do I install WordPress' ) );
check( 'not a question', ! AIGS_Text::is_question( 'Installing WordPress on shared hosting' ) );
check( 'not a question persian', ! AIGS_Text::is_question( 'آموزش نصب وردپرس' ) );

check( 'filler fa', AIGS_Text::is_filler_opening( 'در این مقاله می‌خواهیم درباره سئو صحبت کنیم.' ) );
check( 'filler fa 2', AIGS_Text::is_filler_opening( 'امروز می‌خواهیم یاد بگیریم.' ) );
check( 'filler en', AIGS_Text::is_filler_opening( 'In this article we will look at SEO.' ) );
check( 'not filler', ! AIGS_Text::is_filler_opening( 'SEO is the practice of improving visibility in search results.' ) );

check( 'overlap high', AIGS_Text::overlap( 'نرم‌افزار حسابداری فروشگاهی', 'نرم افزار حسابداری فروشگاهی ابزاری برای ثبت فروش است' ) >= 0.99 );
check( 'overlap prefix stem', AIGS_Text::overlap( 'حسابداری', 'حسابدار خوب' ) > 0 );
check( 'overlap low', AIGS_Text::overlap( 'laptop battery', 'Our company history began in 1990' ) < 0.34 );

list( $gy, $jy ) = AIGS_Text::current_years();
same( 'outdated gregorian year', $gy - 2, AIGS_Text::outdated_year( 'Best laptops ' . ( $gy - 2 ) ) );
same( 'current year not outdated', 0, AIGS_Text::outdated_year( 'Best laptops ' . $gy ) );
$fa_digits = strtr( (string) ( $jy - 1 ), array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
same( 'outdated jalali year in persian digits', $jy - 1, AIGS_Text::outdated_year( 'قیمت گوشی ' . $fa_digits ) );
same( 'non-year number ignored', 0, AIGS_Text::outdated_year( 'Top 1000 tips' ) );

$ex = AIGS_Text::excerpt_sentences( 'First sentence here. Second one is here too. Third sentence follows.', 8 );
same( 'excerpt sentences boundary', 'First sentence here. Second one is here too.', $ex );

/* ============================================================
 * AIGS_Answer: structure + analysis
 * ============================================================ */
$html = '<p>در این مقاله درباره انتخاب نرم‌افزار حسابداری صحبت می‌کنیم.</p>'
	. '<h2>نرم‌افزار حسابداری چیست؟</h2><p>نرم‌افزار حسابداری ابزاری است که خرید، فروش، انبار و حساب‌های مشتریان را ثبت می‌کند و گزارش مالی می‌سازد.</p>'
	. '<h2>چطور نصب کنیم؟</h2><h2>قیمت</h2><p>قیمت‌ها متفاوت است.</p>'
	. '<ul><li><p>nested p in li</p></li></ul><img src="a.jpg"><img src="b.jpg" alt="x">'
	. '<a href="/about/">about</a><a href="#top">top</a><a href="mailto:a@b.c">m</a>';
$s = AIGS_Answer::structure( $html );
same( 'structure paragraphs skip li>p', 3, count( $s['paragraphs'] ) );
same( 'structure headings', 3, count( $s['headings'] ) );
same( 'structure heading answer captured', 'نرم‌افزار حسابداری ابزاری است که خرید، فروش، انبار و حساب‌های مشتریان را ثبت می‌کند و گزارش مالی می‌سازد.', $s['headings'][0]['answer'] );
same( 'structure links filtered', array( '/about/' ), $s['links'] );
same( 'structure images', 2, count( $s['images'] ) );

$a = AIGS_Answer::analyze( $s, 'راهنمای انتخاب نرم‌افزار حسابداری فروشگاهی', '' );
same( 'answer filler detected', 'filler', $a['reason'] );
same( 'answer direct status 0', 0, $a['direct'] );
check( 'answer inferred howto question fa', $a['question']['inferred'] && false !== strpos( $a['question']['text'], 'چگونه' ), $a['question']['text'] );
// "چطور نصب کنیم؟" is followed directly by another heading: unanswered.
check( 'answer unanswered heading', in_array( 'چطور نصب کنیم؟', $a['unanswered'], true ), wp_json_encode( $a['unanswered'] ) );
same( 'answer faq candidate', 1, count( $a['faq'] ) );

$good = AIGS_Answer::structure( '<p>WordPress hosting is a web hosting plan tuned for WordPress sites, with one-click installs, automatic updates, caching and daily backups, so your site loads fast and stays secure without manual server work.</p><h2>Why it matters</h2><p>' . str_repeat( 'word ', 80 ) . '</p>' );
$ga   = AIGS_Answer::analyze( $good, 'What is WordPress hosting?', '' );
same( 'answer ok reason', 'ok', $ga['reason'] );
same( 'answer question from title', false, $ga['question']['inferred'] );

$cmp = AIGS_Answer::analyze( AIGS_Answer::structure( '<p>x</p>' ), 'بهترین گوشی سامسونگ', '' );
check( 'comparison intent fa', $cmp['intent']['comparison'] );
same( 'comparison inferred question', 'بهترین گوشی سامسونگ کدام است؟', $cmp['question']['text'] );
$def = AIGS_Answer::main_question( 'سئو', '' );
same( 'definition inferred fa', 'سئو چیست؟', $def['text'] );
$en = AIGS_Answer::main_question( 'Best running shoes for flat feet', '' );
same( 'comparison inferred en', 'What is the best running shoes for flat feet?', $en['text'] );

$rows = AIGS_Answer::check_rows( $a );
same( 'check rows first is direct answer fail', 0, $rows[0]['status'] );

/* ============================================================
 * AIGS_Rules: page evaluation
 * ============================================================ */
function ctx( $over ) {
	$s = AIGS_Answer::structure( isset( $over['html'] ) ? $over['html'] : '' );
	$t = isset( $over['title'] ) ? $over['title'] : 'Sample';
	unset( $over['html'] );
	return array_merge(
		array(
			'id'              => 5,
			'type'            => 'post',
			'title'           => $t,
			'url'             => 'https://example.ir/sample/',
			'structure'       => $s,
			'answer'          => AIGS_Answer::analyze( $s, $t, '' ),
			'keyword'         => '',
			'has_seo_plugin'  => true,
			'seo_title'       => 'A perfectly reasonable SEO title here',
			'meta_desc'       => str_repeat( 'd', 120 ),
			'seo_auto_desc'   => false,
			'noindex'         => false,
			'canonical'       => '',
			'low_priority'    => false,
			'is_product'      => false,
			'product'         => array(),
			'modified'        => time(),
			'author_id'       => 1,
			'author_complete' => true,
			'thumb_id'        => 9,
			'schema_type'     => '',
			'faq_count'       => 0,
		),
		$over
	);
}

$f = AIGS_Rules::evaluate_page( ctx( array( 'html' => '<p>short</p>' ) ) );
check( 'rule thin content', isset( $f['thin_content'] ) );
check( 'rule no direct answer not fired for thin', ! isset( $f['no_direct_answer'] ) );

$long = '<p>In this article we explore everything.</p>' . str_repeat( '<p>' . str_repeat( 'lorem ipsum ', 70 ) . '</p>', 4 );
$f    = AIGS_Rules::evaluate_page( ctx( array( 'html' => $long, 'title' => 'Best CRM tools 2019 compared' ) ) );
check( 'rule filler opening', isset( $f['filler_opening'] ) );
check( 'rule no structure', isset( $f['no_structure'] ) );
check( 'rule wall of text', isset( $f['wall_of_text'] ) );
check( 'rule comparison no table', isset( $f['comparison_no_table'] ) );
check( 'rule outdated year', isset( $f['outdated_year'] ) && 2019 === $f['outdated_year']['year'] );

$f = AIGS_Rules::evaluate_page( ctx( array( 'noindex' => true, 'canonical' => 'https://example.ir/other/', 'seo_title' => 'Hi', 'meta_desc' => '' ) ) );
check( 'rule noindex', isset( $f['noindex'] ) );
check( 'rule canonical elsewhere', isset( $f['canonical_elsewhere'] ) );
check( 'rule title length', isset( $f['title_length'] ) );
check( 'rule meta missing', isset( $f['meta_desc_missing'] ) );

$f = AIGS_Rules::evaluate_page( ctx( array( 'canonical' => 'http://www.example.ir/sample' ) ) );
check( 'rule canonical same url variants ignored', ! isset( $f['canonical_elsewhere'] ) );

$f = AIGS_Rules::evaluate_page( ctx( array( 'meta_desc' => '', 'seo_auto_desc' => true ) ) );
check( 'rule meta missing skipped when plugin auto-generates', ! isset( $f['meta_desc_missing'] ) );

$f = AIGS_Rules::evaluate_page( ctx( array( 'low_priority' => true, 'html' => '<p>Contact us</p>', 'type' => 'page' ) ) );
check( 'rule low priority skips content', ! isset( $f['thin_content'] ) );

$f = AIGS_Rules::evaluate_page( ctx( array( 'schema_type' => 'FAQPage', 'faq_count' => 0 ) ) );
check( 'rule faq empty', isset( $f['faq_empty'] ) );
$f = AIGS_Rules::evaluate_page( ctx( array( 'schema_type' => 'FAQPage', 'faq_count' => 2, 'html' => '<div class="schema-faq">x</div>' ) ) );
check( 'rule faq duplicate', isset( $f['faq_duplicate'] ) );

$f = AIGS_Rules::evaluate_page( ctx( array( 'author_complete' => false, 'thumb_id' => 0, 'html' => '<img src="x.png">' ) ) );
check( 'rule author profile', isset( $f['author_profile'] ) );
check( 'rule featured image', isset( $f['no_featured_image'] ) );
check( 'rule img alt', isset( $f['img_alt_missing'] ) && 1 === $f['img_alt_missing']['count'] );

$prod = array(
	'short_words'    => 0,
	'long_words'     => 20,
	'long_hash'      => '',
	'attributes'     => 1,
	'image'          => 3,
	'image_alt'      => false,
	'categorized'    => false,
	'has_price'      => false,
	'gtin_supported' => true,
	'gtin'           => false,
);
$f = AIGS_Rules::evaluate_page( ctx( array( 'type' => 'product', 'is_product' => true, 'product' => $prod, 'html' => '<p>x</p>' ) ) );
foreach ( array( 'product_short_desc', 'product_thin_desc', 'product_attributes', 'product_image_alt', 'product_no_category', 'product_no_price', 'product_no_gtin' ) as $r ) {
	check( 'rule ' . $r, isset( $f[ $r ] ) );
}
check( 'product skips article content rules', ! isset( $f['thin_content'] ) );

same( 'page score perfect', 100, AIGS_Rules::page_score( array() ) );
same( 'page score mixed', 100 - 18 - 9 - 3, AIGS_Rules::page_score( array( 'noindex', 'thin_content', 'img_alt_missing' ) ) );
same( 'page score ignores vis', 100, AIGS_Rules::page_score( array( 'vis_gap_abc' ) ) );
check( 'every rule has 7 fields', count( array_filter( AIGS_Rules::catalog(), function ( $r ) { return 7 !== count( $r ); } ) ) === 0 );
check( 'every rule id fits column', count( array_filter( array_keys( AIGS_Rules::catalog() ), function ( $k ) { return strlen( $k ) > 64; } ) ) === 0 );
same( 'priority range high', 150, AIGS_Findings::priority( 4, 100 ) );
same( 'priority range low', 13, AIGS_Findings::priority( 1, 0 ) );

/* ============================================================
 * Tracker
 * ============================================================ */
check( 'cidr v4 in', AIGS_Tracker::ip_in_cidr( '20.171.206.5', '20.171.206.0/24' ) );
check( 'cidr v4 out', ! AIGS_Tracker::ip_in_cidr( '20.171.207.5', '20.171.206.0/24' ) );
check( 'cidr v4 /28 edge', AIGS_Tracker::ip_in_cidr( '52.230.152.15', '52.230.152.0/28' ) && ! AIGS_Tracker::ip_in_cidr( '52.230.152.16', '52.230.152.0/28' ) );
check( 'cidr v6', AIGS_Tracker::ip_in_cidr( '2a02:6ea0:c020::5', '2a02:6ea0:c020::/48' ) );
check( 'cidr mixed family', ! AIGS_Tracker::ip_in_cidr( '1.2.3.4', '2a02::/16' ) );
$m = AIGS_Tracker::match_bot( 'Mozilla/5.0 AppleWebKit/537.36; compatible; ChatGPT-User/1.0; +https://openai.com/bot' );
same( 'bot match chatgpt-user class', 'fetch', $m[1][2] );
$m = AIGS_Tracker::match_bot( 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)' );
same( 'bot match gptbot class', 'training', $m[1][2] );
$m = AIGS_Tracker::match_bot( 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0)' );
same( 'bot match oai-searchbot class', 'search', $m[1][2] );
same( 'no bot for browser', null, AIGS_Tracker::match_bot( 'Mozilla/5.0 (Windows NT 10.0) Chrome/120' ) );
$r = AIGS_Tracker::match_referral( '', 'chatgpt.com' );
same( 'utm chatgpt referral', array( 'ChatGPT', 'utm' ), $r );
$r = AIGS_Tracker::match_referral( 'https://www.perplexity.ai/search?q=x', '' );
same( 'referrer perplexity', array( 'Perplexity', 'referrer' ), $r );
same( 'no referral', null, AIGS_Tracker::match_referral( 'https://google.com/', 'newsletter' ) );

/* ============================================================
 * robots.txt modes
 * ============================================================ */
same( 'allow mode blocks nothing', array(), AIGS_LLMS::blocked_tokens( 'allow' ) );
$so = AIGS_LLMS::blocked_tokens( 'search_only' );
check( 'search_only blocks GPTBot', in_array( 'GPTBot', $so, true ) );
check( 'search_only blocks Google-Extended', in_array( 'Google-Extended', $so, true ) );
check( 'search_only keeps OAI-SearchBot', ! in_array( 'OAI-SearchBot', $so, true ) );
check( 'search_only keeps ChatGPT-User', ! in_array( 'ChatGPT-User', $so, true ) );
$dis = AIGS_LLMS::blocked_tokens( 'disallow' );
check( 'disallow blocks PerplexityBot', in_array( 'PerplexityBot', $dis, true ) );
check( 'disallow never blocks bingbot', ! in_array( 'bingbot', $dis, true ) );
check( 'disallow never blocks Applebot', ! in_array( 'Applebot', $dis, true ) );

update_option( 'blog_public', '1' );
update_option( 'aigs_settings', array( 'ai_access' => 'search_only', 'enable_llms' => 1 ) );
$llms = new AIGS_LLMS();
$out  = $llms->robots( "User-agent: *\nDisallow: /wp-admin/\n" );
check( 'robots output grouped single Disallow', 1 === substr_count( $out, 'Disallow: /' . "\n" ) && false !== strpos( $out, "User-agent: GPTBot\n" ) );
check( 'robots keeps default group', 0 === strpos( $out, "User-agent: *\nDisallow: /wp-admin/" ) );
update_option( 'blog_public', '0' );
same( 'robots untouched when site discourages indexing', "User-agent: *\nDisallow: /\n", $llms->robots( "User-agent: *\nDisallow: /\n" ) );
update_option( 'blog_public', '1' );

// Audit robots parser (private): AI search bots blocked by a wildcard rule.
$audit = new AIGS_Audit();
$rm    = new ReflectionMethod( 'AIGS_Audit', 'robots_blocked' );
$rm->setAccessible( true );
$body  = "User-agent: *\nDisallow: /\n\nUser-agent: PerplexityBot\nAllow: /\n";
same( 'robots parser', array( 'OAI-SearchBot', 'ChatGPT-User' ), $rm->invoke( $audit, $body, array( 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot' ) ) );
$body2 = "User-agent: GPTBot\nUser-agent: OAI-SearchBot\nDisallow: /\n\nUser-agent: *\nDisallow: /wp-admin/\n";
same( 'robots parser grouped agents', array( 'OAI-SearchBot' ), $rm->invoke( $audit, $body2, array( 'OAI-SearchBot', 'PerplexityBot' ) ) );

/* ============================================================
 * AI client helpers
 * ============================================================ */
same( 'host strip www', 'example.ir', AIGS_AI_Client::host( 'https://www.example.ir/x' ) );
check( 'host matches subdomain', AIGS_AI_Client::host_matches( 'https://shop.example.ir/a', 'example.ir' ) );
check( 'host no partial match', ! AIGS_AI_Client::host_matches( 'https://notexample.ir/a', 'example.ir' ) );
same( 'urls in text', array( 'https://a.com/x', 'https://b.ir/' ), AIGS_AI_Client::urls_in_text( 'See https://a.com/x, and (https://b.ir/).' ) );
update_option( 'aigs_settings', array( 'ai_provider' => 'evil' ) );
same( 'provider whitelist', 'openai', AIGS_AI_Client::provider() );
update_option( 'aigs_settings', array( 'ai_provider' => 'anthropic' ) );
same( 'anthropic default model', 'claude-haiku-5-5', AIGS_AI_Client::model() );
update_option( 'aigs_settings', array( 'ai_monthly_cap' => 5 ) );
update_option( 'aigs_ai_usage', array( gmdate( 'Y-m' ) => 5 ) );
$err = AIGS_AI_Client::ask( 'x' );
check( 'cap blocks before key check order', is_wp_error( $err ) );
update_option( 'aigs_settings', array( 'ai_monthly_cap' => 5, 'ai_api_key' => 'k' ) );
$err = AIGS_AI_Client::ask( 'x' );
same( 'monthly cap enforced', 'aigs_cap', $err->get_error_code() );

/* ============================================================
 * AI visibility analysis
 * ============================================================ */
update_option(
	'aigs_settings',
	array(
		'business_name' => 'دیجی‌کالا',
		'brand_aliases' => "Digikala\nDK",
		'competitors'   => "Torob | torob.com\nBasalam | basalam.com | باسلام",
	)
);
$GLOBALS['__home'] = 'https://www.digikala.com';
$ans = "بهترین فروشگاه‌های آنلاین:\n1. باسلام برای محصولات دست‌ساز\n2. ديجي كالا با تنوع بالا\n\nمنبع: https://www.digikala.com/help";
$v   = AIGS_AI_Visibility::analyze( $ans, array( 'https://torob.com/x' ) );
check( 'vis mentioned (arabic spelling)', $v['mentioned'] );
check( 'vis recommended (list item)', $v['recommended'] );
check( 'vis cited (url in text)', $v['cited'] );
same( 'vis position second after Basalam', 2, $v['position'] );
check( 'vis competitor via alias', true === $v['competitors']['Basalam'] );
check( 'vis competitor via citation only', true === $v['competitors']['Torob'] );
$v2 = AIGS_AI_Visibility::analyze( 'Torob and Basalam are popular.', array() );
check( 'vis absent', ! $v2['mentioned'] && 0 === $v2['position'] );
$sum = AIGS_AI_Visibility::summarize(
	array(
		array( 'mentioned' => 1, 'cited' => 1, 'recommended' => 1, 'competitors' => '{"Torob":true,"Basalam":false}' ),
		array( 'mentioned' => 0, 'cited' => 0, 'recommended' => 0, 'competitors' => '{"Torob":true,"Basalam":true}' ),
	)
);
same( 'vis mention rate', 50, $sum['mention'] );
same( 'vis share of voice', 25, $sum['share']['__brand'] );
same( 'vis competitors parsed', 2, count( AIGS_AI_Visibility::competitors() ) );
same( 'vis competitor extra spelling', array( 'Basalam', 'باسلام' ), AIGS_AI_Visibility::competitors()[1]['terms'] );

/* ============================================================
 * Settings sanitization
 * ============================================================ */
update_option( 'aigs_settings', array( 'ai_api_key' => 'secret', 'llms_txt' => 'keep me', 'indexnow_key' => 'abc' ) );
$admin = new AIGS_Admin( array() );
$out   = $admin->sanitize_settings( array( 'section_settings' => 1, 'ai_api_key' => '', 'ai_provider' => 'perplexity', 'ai_access' => 'search_only', 'ai_monthly_cap' => '99999', 'retention_days' => '1' ) );
same( 'empty key keeps saved key', 'secret', $out['ai_api_key'] );
same( 'other section untouched', 'keep me', $out['llms_txt'] );
same( 'provider saved', 'perplexity', $out['ai_provider'] );
same( 'access mode saved', 'search_only', $out['ai_access'] );
same( 'cap clamped', 5000, $out['ai_monthly_cap'] );
same( 'retention clamped', 7, $out['retention_days'] );
check( 'marker not stored', ! isset( $out['section_settings'] ) );
$out = $admin->sanitize_settings( array( 'section_settings' => 1, 'ai_remove_key' => 1, 'ai_access' => 'bogus' ) );
same( 'remove key', '', $out['ai_api_key'] );
same( 'invalid access falls back', 'allow', $out['ai_access'] );
$out = $admin->sanitize_settings( array( 'meta_fallback' => 1 ) );
same( 'programmatic merge', 1, $out['meta_fallback'] );
same( 'programmatic merge keeps key', 'secret', $out['ai_api_key'] );
$out = $admin->sanitize_settings( array( 'section_visibility' => 1, 'vis_prompts' => "a\nb", 'vis_schedule' => 'hourly' ) );
same( 'vis schedule whitelist', 'off', $out['vis_schedule'] );

echo "\n{$GLOBALS['__pass']} passed, {$GLOBALS['__fail']} failed\n";
exit( $GLOBALS['__fail'] ? 1 : 0 );
