<?php
/**
 * Markdown copies of pages for AI assistants, and /llms-full.txt.
 *
 * Every public post can be read as clean Markdown at its own address plus
 * ?format=markdown. It is the same content people see (no cloaking): only the
 * theme, menus and scripts are left out. The copy carries a canonical Link
 * header to the real page and X-Robots-Tag: noindex, so it never competes with
 * the HTML page in search.
 *
 * @package OmniGEO_Ai_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML to Markdown, per-page Markdown and llms-full.txt.
 */
class AIGS_Markdown {

	const FULL_CACHE = 'aigs_llms_full';
	const FULL_MAX   = 400000;

	/**
	 * Register hooks.
	 */
	public function hooks() {
		if ( ! aigs_get( 'tb_markdown', 1 ) ) {
			return;
		}
		add_action( 'template_redirect', aigs_guard( array( $this, 'maybe_serve' ) ), 2 );
		add_action( 'wp_head', aigs_guard( array( $this, 'head_link' ) ), 3 );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Forget the cached llms-full.txt.
	 */
	public static function flush() {
		delete_transient( self::FULL_CACHE );
	}

	/**
	 * Can this post be offered as Markdown?
	 *
	 * @param WP_Post|null $post Post.
	 * @return bool
	 */
	public static function eligible( $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return false;
		}
		if ( ! is_post_type_viewable( $post->post_type ) || 'attachment' === $post->post_type ) {
			return false;
		}
		return ! AIGS_SEO::is_noindex( $post->ID );
	}

	/**
	 * Markdown address of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function url( $post_id ) {
		return add_query_arg( 'format', 'markdown', get_permalink( $post_id ) );
	}

	/**
	 * Advertise the Markdown copy in the page head.
	 */
	public function head_link() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( self::eligible( $post ) ) {
			echo '<link rel="alternate" type="text/markdown" title="Markdown" href="' . esc_url( self::url( $post->ID ) ) . '" />' . "\n";
		}
	}

	/**
	 * Serve the Markdown copy.
	 */
	public function maybe_serve() {
		if ( ! isset( $_GET['format'] ) || 'markdown' !== $_GET['format'] || ! is_singular() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only view.
			return;
		}
		$post = get_queried_object();
		if ( ! self::eligible( $post ) ) {
			return;
		}
		$md = self::for_post( $post );
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		header( 'Link: <' . esc_url_raw( get_permalink( $post ) ) . '>; rel="canonical"' );
		echo $md; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/markdown body built from the post's own rendered content.
		exit;
	}

	/**
	 * Markdown for one post, with a small front matter block.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function for_post( $post ) {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the_content filters expect the global post.
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		wp_reset_postdata();

		$desc = AIGS_Head::description( $post );
		$out  = "---\n";
		$out .= 'title: ' . self::yaml( get_the_title( $post ) ) . "\n";
		$out .= 'url: ' . get_permalink( $post ) . "\n";
		if ( '' !== $desc ) {
			$out .= 'description: ' . self::yaml( $desc ) . "\n";
		}
		$out .= 'published: ' . get_post_time( 'Y-m-d', true, $post ) . "\n";
		$out .= 'updated: ' . get_post_modified_time( 'Y-m-d', true, $post ) . "\n";
		$author = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( $author && 'post' === $post->post_type ) {
			$out .= 'author: ' . self::yaml( $author ) . "\n";
		}
		$out .= 'site: ' . self::yaml( aigs_business_name() ) . "\n";
		$out .= "---\n\n";
		$out .= '# ' . self::inline_text( get_the_title( $post ) ) . "\n\n";
		$out .= self::from_html( $html );
		return rtrim( $out ) . "\n";
	}

	/**
	 * Quote a YAML scalar.
	 *
	 * @param string $s Value.
	 * @return string
	 */
	private static function yaml( $s ) {
		$s = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) ) );
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $s ) . '"';
	}

	/**
	 * Decode entities and collapse whitespace.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function inline_text( $s ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Convert rendered HTML to Markdown (pure; tested).
	 *
	 * Handles headings, paragraphs, lists (nested), links, emphasis, code,
	 * blockquotes, images (alt text), tables and line breaks. Scripts, styles,
	 * forms and navigation are dropped.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function from_html( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="aigs-root">' . $html . '</div>' );
		libxml_clear_errors();
		$root = $dom->getElementById( 'aigs-root' );
		if ( ! $root ) {
			return '';
		}
		$md = self::blocks( $root, 0 );
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );
		return trim( $md ) . "\n";
	}

	/**
	 * Render the children of a block container.
	 *
	 * @param DOMNode $node  Node.
	 * @param int     $depth List depth.
	 * @return string
	 */
	private static function blocks( $node, $depth ) {
		$out    = '';
		$inline = '';
		foreach ( $node->childNodes as $child ) {
			$tag = XML_ELEMENT_NODE === $child->nodeType ? strtolower( $child->nodeName ) : '#text';
			if ( self::is_block( $tag ) ) {
				if ( '' !== trim( $inline ) ) {
					$out   .= trim( $inline ) . "\n\n";
					$inline = '';
				}
				$out .= self::block( $child, $tag, $depth );
			} else {
				$inline .= self::inline( $child );
			}
		}
		if ( '' !== trim( $inline ) ) {
			$out .= trim( $inline ) . "\n\n";
		}
		return $out;
	}

	/**
	 * Is the tag a block-level element we lay out on its own lines?
	 *
	 * @param string $tag Tag.
	 * @return bool
	 */
	private static function is_block( $tag ) {
		return in_array( $tag, array( 'p', 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'pre', 'table', 'hr', 'details', 'summary', 'dl', 'nav', 'form', 'script', 'style', 'noscript', 'iframe', 'svg', 'button', 'select', 'textarea' ), true );
	}

	/**
	 * Render one block element.
	 *
	 * @param DOMElement $el    Element.
	 * @param string     $tag   Tag.
	 * @param int        $depth List depth.
	 * @return string
	 */
	private static function block( $el, $tag, $depth ) {
		switch ( $tag ) {
			case 'script':
			case 'style':
			case 'noscript':
			case 'nav':
			case 'form':
			case 'svg':
			case 'button':
			case 'select':
			case 'textarea':
				return '';
			case 'iframe':
				$src = $el->getAttribute( 'src' );
				return $src && preg_match( '#^https?://#', $src ) ? '[' . __( 'Embedded content', 'omnigeo-ai-seo' ) . '](' . $src . ")\n\n" : '';
			case 'hr':
				return "---\n\n";
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = max( 2, (int) substr( $tag, 1 ) );
				$text  = self::clean_inline( self::inline_children( $el ) );
				return '' === $text ? '' : str_repeat( '#', $level ) . ' ' . $text . "\n\n";
			case 'p':
			case 'summary':
			case 'figcaption':
				$text = self::clean_inline( self::inline_children( $el ) );
				if ( 'summary' === $tag && '' !== $text ) {
					$text = '**' . $text . '**';
				}
				return '' === $text ? '' : $text . "\n\n";
			case 'ul':
			case 'ol':
				return self::list_md( $el, 'ol' === $tag, $depth ) . ( 0 === $depth ? "\n" : '' );
			case 'blockquote':
				$inner = trim( self::blocks( $el, $depth ) );
				return '' === $inner ? '' : '> ' . str_replace( "\n", "\n> ", $inner ) . "\n\n";
			case 'pre':
				return "```\n" . rtrim( $el->textContent ) . "\n```\n\n";
			case 'table':
				return self::table_md( $el );
			case 'dl':
				$out = '';
				foreach ( $el->childNodes as $c ) {
					if ( XML_ELEMENT_NODE !== $c->nodeType ) {
						continue;
					}
					$t = self::clean_inline( self::inline_children( $c ) );
					if ( '' === $t ) {
						continue;
					}
					$out .= 'dt' === strtolower( $c->nodeName ) ? '**' . $t . "**\n" : ': ' . $t . "\n";
				}
				return '' === $out ? '' : $out . "\n";
			default:
				return self::blocks( $el, $depth );
		}
	}

	/**
	 * Render a list.
	 *
	 * @param DOMElement $el      List element.
	 * @param bool       $ordered Ordered?
	 * @param int        $depth   Depth.
	 * @return string
	 */
	private static function list_md( $el, $ordered, $depth ) {
		$out = '';
		$n   = 1;
		$pad = str_repeat( '  ', $depth );
		foreach ( $el->childNodes as $li ) {
			if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
				continue;
			}
			$text   = '';
			$nested = '';
			foreach ( $li->childNodes as $c ) {
				$ctag = XML_ELEMENT_NODE === $c->nodeType ? strtolower( $c->nodeName ) : '';
				if ( 'ul' === $ctag || 'ol' === $ctag ) {
					$nested .= self::list_md( $c, 'ol' === $ctag, $depth + 1 );
				} elseif ( 'p' === $ctag || 'div' === $ctag ) {
					$text .= ' ' . self::inline_children( $c );
				} else {
					$text .= self::inline( $c );
				}
			}
			$text = self::clean_inline( $text );
			if ( '' === $text && '' === $nested ) {
				continue;
			}
			$out .= $pad . ( $ordered ? $n . '. ' : '- ' ) . $text . "\n" . $nested;
			$n++;
		}
		return $out;
	}

	/**
	 * Render a table as a pipe table (first row is the header).
	 *
	 * @param DOMElement $el Table.
	 * @return string
	 */
	private static function table_md( $el ) {
		$rows = array();
		foreach ( $el->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $td ) {
				if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
					$cells[] = str_replace( '|', '\\|', self::clean_inline( self::inline_children( $td ) ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return '';
		}
		$cols = max( array_map( 'count', $rows ) );
		$out  = '';
		foreach ( $rows as $i => $cells ) {
			$cells = array_pad( $cells, $cols, '' );
			$out  .= '| ' . implode( ' | ', $cells ) . " |\n";
			if ( 0 === $i ) {
				$out .= '|' . str_repeat( ' --- |', $cols ) . "\n";
			}
		}
		return $out . "\n";
	}

	/**
	 * Inline Markdown of an element's children.
	 *
	 * @param DOMNode $el Element.
	 * @return string
	 */
	private static function inline_children( $el ) {
		$s = '';
		foreach ( $el->childNodes as $c ) {
			$s .= self::inline( $c );
		}
		return $s;
	}

	/**
	 * Inline Markdown of a node.
	 *
	 * @param DOMNode $n Node.
	 * @return string
	 */
	private static function inline( $n ) {
		if ( XML_TEXT_NODE === $n->nodeType || XML_CDATA_SECTION_NODE === $n->nodeType ) {
			return preg_replace( '/\s+/u', ' ', $n->nodeValue );
		}
		if ( XML_ELEMENT_NODE !== $n->nodeType ) {
			return '';
		}
		$tag = strtolower( $n->nodeName );
		switch ( $tag ) {
			case 'br':
				return "  \n";
			case 'strong':
			case 'b':
				$t = trim( self::inline_children( $n ) );
				return '' === $t ? '' : ' **' . $t . '** ';
			case 'em':
			case 'i':
				$t = trim( self::inline_children( $n ) );
				return '' === $t ? '' : ' *' . $t . '* ';
			case 'code':
				return '`' . str_replace( '`', '', $n->textContent ) . '`';
			case 'a':
				$t    = trim( self::inline_children( $n ) );
				$href = trim( $n->getAttribute( 'href' ) );
				if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) ) {
					return $t;
				}
				if ( '/' === $href[0] && '/' !== substr( $href, 1, 1 ) ) {
					$href = home_url( $href );
				}
				return '' === $t ? '' : '[' . $t . '](' . str_replace( array( ' ', ')' ), array( '%20', '%29' ), $href ) . ')';
			case 'img':
				$alt = trim( $n->getAttribute( 'alt' ) );
				$src = trim( $n->getAttribute( 'src' ) );
				return ( '' === $alt || '' === $src ) ? '' : '![' . $alt . '](' . str_replace( ' ', '%20', $src ) . ')';
			case 'script':
			case 'style':
			case 'noscript':
			case 'svg':
			case 'button':
				return '';
			default:
				if ( self::is_block( $tag ) ) {
					return ' ' . trim( self::blocks( $n, 0 ) ) . ' ';
				}
				return self::inline_children( $n );
		}
	}

	/**
	 * Tidy inline Markdown (spaces around emphasis, double spaces).
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function clean_inline( $s ) {
		$s = preg_replace( '/[ \t]+/u', ' ', (string) $s );
		$s = preg_replace( '/ ([.,;:!?)\]])/u', '$1', $s );
		$s = preg_replace( '/([(\[]) /u', '$1', $s );
		return trim( str_replace( "\xc2\xa0", ' ', $s ) );
	}

	/**
	 * Build /llms-full.txt: the llms.txt header followed by the full Markdown of
	 * the key pages and recent posts, capped to stay light for crawlers.
	 *
	 * @param AIGS_LLMS $llms llms.txt module.
	 * @return string
	 */
	public static function full( $llms ) {
		$cached = get_transient( self::FULL_CACHE );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$name = aigs_business_name();
		$out  = '# ' . $name . "\n\n";
		$desc = trim( (string) get_bloginfo( 'description' ) );
		if ( '' !== $desc ) {
			$out .= '> ' . $desc . "\n\n";
		}
		/* translators: %s: site address. */
		$out .= sprintf( __( 'Full text of the main pages of %s, generated for AI assistants. Each section links to the original page.', 'omnigeo-ai-seo' ), home_url( '/' ) ) . "\n\n";

		$ids = array();
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$ids[] = $front;
		}
		foreach ( get_pages( array( 'sort_column' => 'menu_order', 'number' => 25 ) ) as $p ) {
			$ids[] = (int) $p->ID;
		}
		foreach ( get_posts( array( 'numberposts' => 25, 'post_status' => 'publish', 'fields' => 'ids' ) ) as $id ) {
			$ids[] = (int) $id;
		}
		/**
		 * Filter the posts included in llms-full.txt.
		 *
		 * @param int[] $ids Post IDs in order.
		 */
		$ids = array_unique( array_map( 'intval', (array) apply_filters( 'aigs_llms_full_ids', $ids ) ) );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! self::eligible( $post ) ) {
				continue;
			}
			$md = self::for_post( $post );
			// Drop the per-page front matter; keep a source line instead.
			$md    = preg_replace( '/\A---\n.*?\n---\n\n/s', '', $md );
			$chunk = "\n---\n\n" . $md . "\n" . __( 'Source:', 'omnigeo-ai-seo' ) . ' ' . get_permalink( $post ) . "\n";
			if ( strlen( $out ) + strlen( $chunk ) > self::FULL_MAX ) {
				break;
			}
			$out .= $chunk;
		}
		set_transient( self::FULL_CACHE, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}
}
