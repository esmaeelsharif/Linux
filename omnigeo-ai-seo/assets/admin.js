/* global AIGS, jQuery */
( function ( $ ) {
	'use strict';

	/* ---------- helpers ---------- */
	function post( action, data ) {
		data = data || {};
		data.action = action;
		data.nonce = AIGS.nonce;
		return $.post( AIGS.ajax, data );
	}

	var ICON = {
		check: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
		x: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>'
	};

	function esc( s ) {
		return $( '<div>' ).text( s == null ? '' : s ).html();
	}

	function skeleton( rows ) {
		var h = '<div class="aigs-card">';
		for ( var i = 0; i < ( rows || 4 ); i++ ) {
			h += '<div class="aigs-skel aigs-skel-row" style="width:' + ( 60 + Math.round( Math.random() * 35 ) ) + '%"></div>';
		}
		return h + '</div>';
	}

	/* ---------- toasts ---------- */
	function toast( msg, isErr ) {
		var $wrap = $( '.aigs-toasts' );
		if ( ! $wrap.length ) {
			$wrap = $( '<div class="aigs-toasts"></div>' ).appendTo( 'body' );
		}
		var ico = isErr ? ICON.x : ICON.check;
		var $t = $( '<div class="aigs-toast' + ( isErr ? ' aigs-toast--err' : '' ) + '">' + ico + '<span></span></div>' );
		$t.find( 'span' ).text( msg );
		$wrap.append( $t );
		setTimeout( function () {
			$t.css( { transition: 'opacity .3s, transform .3s', opacity: 0, transform: 'translateY(8px)' } );
			setTimeout( function () { $t.remove(); }, 320 );
		}, 3200 );
	}

	/* ---------- score ring ---------- */
	function ringColor( s ) {
		if ( s >= 75 ) { return '#18794e'; }
		if ( s >= 50 ) { return '#9a6700'; }
		return '#c4332e';
	}
	function scoreRing( score, grade ) {
		var r = 46, c = 2 * Math.PI * r, off = c * ( 1 - score / 100 ), col = ringColor( score );
		return '<div class="aigs-ring">'
			+ '<svg width="104" height="104" viewBox="0 0 104 104">'
			+ '<circle class="track" cx="52" cy="52" r="' + r + '" fill="none" stroke-width="8"/>'
			+ '<circle class="meter" cx="52" cy="52" r="' + r + '" fill="none" stroke="' + col + '" stroke-width="8" stroke-dasharray="' + c + '" stroke-dashoffset="' + c + '"/>'
			+ '</svg>'
			+ '<div class="aigs-ring__txt"><span class="aigs-ring__num">' + score + '</span><span class="aigs-ring__grade">' + ( grade || '' ) + '</span></div>'
			+ '</div>';
	}
	function animateRing( $ctx, score ) {
		var r = 46, c = 2 * Math.PI * r, off = c * ( 1 - score / 100 );
		// next frame so the transition fires.
		window.requestAnimationFrame( function () {
			$ctx.find( '.meter' ).css( 'stroke-dashoffset', off );
		} );
	}

	function pill( status ) {
		if ( 2 === status ) { return '<span class="aigs-pill aigs-pill--pass"><span class="dot"></span>Pass</span>'; }
		if ( 1 === status ) { return '<span class="aigs-pill aigs-pill--warn"><span class="dot"></span>Partial</span>'; }
		return '<span class="aigs-pill aigs-pill--fail"><span class="dot"></span>Fail</span>';
	}

	function reportCard( d, metaLabel ) {
		var html = '<div class="aigs-card">'
			+ '<div class="aigs-scorewrap">' + scoreRing( d.score, d.grade )
			+ '<div class="aigs-scoremeta"><strong>' + ( metaLabel || 'GEO score' ) + '</strong><p>' + esc( d.url ) + '</p></div></div>'
			+ '<div class="aigs-checks">';
		d.checks.forEach( function ( c ) {
			html += '<div class="aigs-check"><div>' + pill( c.status ) + '</div>'
				+ '<div class="aigs-check__b"><strong>' + esc( c.label ) + '</strong><span>' + esc( c.remark ) + '</span></div></div>';
		} );
		return html + '</div></div>';
	}

	/* ---------- Audit ---------- */
	$( '#aigs-run-audit' ).on( 'click', function () {
		var $btn = $( this ), url = $( '#aigs-audit-url' ).val(), $out = $( '#aigs-audit-result' );
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 6 ) );
		post( 'aigs_audit', { url: url } ).done( function ( res ) {
			if ( ! res.success ) { $out.html( '' ); toast( 'Audit failed.', true ); return; }
			var d = res.data;
			if ( d.error ) { $out.html( '' ); toast( d.error, true ); return; }
			$out.attr( 'aria-busy', 'false' ).html( reportCard( d, 'GEO score' ) );
			animateRing( $out, d.score );
		} ).fail( function () { $out.html( '' ); toast( 'Request failed.', true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- Health ---------- */
	$( '#aigs-run-health' ).on( 'click', function () {
		var $btn = $( this ), $out = $( '#aigs-health-result' );
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 6 ) );
		post( 'aigs_health' ).done( function ( res ) {
			if ( ! res.success ) { toast( 'Failed.', true ); return; }
			var html = '<div class="aigs-card"><div class="aigs-checks">';
			res.data.forEach( function ( c ) {
				var p = c.pass ? '<span class="aigs-pill aigs-pill--pass"><span class="dot"></span>OK</span>' : '<span class="aigs-pill aigs-pill--fail"><span class="dot"></span>Fix</span>';
				html += '<div class="aigs-check"><div>' + p + '</div><div class="aigs-check__b"><strong>' + esc( c.label ) + '</strong><span>' + esc( c.remark ) + '</span></div></div>';
			} );
			html += '</div></div>';
			$out.attr( 'aria-busy', 'false' ).html( html );
		} ).always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- IndexNow ---------- */
	$( '#aigs-submit-indexnow' ).on( 'click', function () {
		var $btn = $( this ), url = $( '#aigs-indexnow-url' ).val();
		$btn.prop( 'disabled', true );
		post( 'aigs_indexnow', { url: url } ).done( function ( res ) {
			if ( res.success ) {
				var code = res.data.code;
				toast( ( 200 === code || 202 === code ) ? 'Submitted to IndexNow (HTTP ' + code + ')' : 'IndexNow responded HTTP ' + code, ! ( 200 === code || 202 === code ) );
			} else {
				toast( ( res.data && res.data.message ) ? res.data.message : 'Failed', true );
			}
		} ).fail( function () { toast( 'Request failed.', true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- Prompt suggestions ---------- */
	$( '#aigs-suggest-prompts' ).on( 'click', function () {
		var $out = $( '#aigs-prompt-list' );
		post( 'aigs_prompt_suggest' ).done( function ( res ) {
			if ( ! res.success ) { return; }
			var html = '';
			res.data.prompts.forEach( function ( p ) {
				html += '<span class="aigs-chip"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M5 12H1M12 7l1.6 3.4L17 12l-3.4 1.6L12 17l-1.6-3.4L7 12z"/></svg>' + esc( p ) + '</span>';
			} );
			$out.html( html );
		} );
	} );
	$( document ).on( 'click', '.aigs-chip', function () {
		$( '#aigs-prompt' ).val( $( this ).text() ).focus();
	} );

	/* ---------- AI visibility scan ---------- */
	$( '#aigs-run-scan' ).on( 'click', function () {
		var $btn = $( this ), prompt = $( '#aigs-prompt' ).val(), $out = $( '#aigs-scan-result' );
		if ( ! prompt ) { toast( 'Write or pick a prompt first.', true ); return; }
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 3 ) );
		post( 'aigs_ai_scan', { prompt: prompt } ).done( function ( res ) {
			if ( ! res.success ) {
				$out.html( '' );
				toast( ( res.data && res.data.message ) ? res.data.message : 'Failed', true );
				return;
			}
			var d = res.data;
			var m = d.mentioned
				? '<span class="aigs-tag aigs-tag--yes">' + ICON.check + 'Brand mentioned</span>'
				: '<span class="aigs-tag aigs-tag--no">' + ICON.x + 'Not mentioned</span>';
			var c = d.cited
				? '<span class="aigs-tag aigs-tag--yes">' + ICON.check + 'Domain cited</span>'
				: '<span class="aigs-tag aigs-tag--no">' + ICON.x + 'Not cited</span>';
			var html = '<div class="aigs-card"><div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">' + m + ' ' + c
				+ ' <span class="aigs-muted" style="align-self:center;font-size:12.5px">via ' + esc( d.provider ) + '</span></div>'
				+ '<p class="aigs-muted" style="font-size:13px;line-height:1.6"><em>Answer excerpt</em><br>' + esc( d.answer ) + '</p></div>';
			$out.attr( 'aria-busy', 'false' ).html( html );
		} ).fail( function () { $out.html( '' ); toast( 'Request failed.', true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- Content extractability (live, in editor) ---------- */
	( function () {
		var $erun = $( '#aigs-extract-run' );
		if ( ! $erun.length ) { return; }

		var ePid    = $erun.data( 'post' );
		var $eout   = $( '#aigs-extract-result' );
		var eBusy   = false;
		var eSig    = null;
		var eTimer  = null;
		var eQueued = false;

		// Read the current (unsaved) content from whichever editor is active.
		function getContent() {
			try {
				if ( window.wp && wp.data && typeof wp.data.select === 'function' ) {
					var ed = wp.data.select( 'core/editor' );
					if ( ed && typeof ed.getEditedPostContent === 'function' ) {
						var c = ed.getEditedPostContent();
						if ( typeof c === 'string' ) {
							var ti = '';
							try { ti = ed.getEditedPostAttribute( 'title' ) || ''; } catch ( e ) {}
							return { content: c, title: ti };
						}
					}
				}
			} catch ( e ) {}
			if ( window.tinymce && window.tinymce.get ) {
				var t = window.tinymce.get( 'content' );
				if ( t && ! t.isHidden() ) {
					return { content: t.getContent(), title: ( document.getElementById( 'title' ) || {} ).value || '' };
				}
			}
			var ta = document.getElementById( 'content' );
			if ( ta ) { return { content: ta.value, title: ( document.getElementById( 'title' ) || {} ).value || '' }; }
			return null;
		}

		function sig( c ) { return c.length + '~' + c.slice( 0, 160 ) + '~' + c.slice( -160 ); }

		function run( manual ) {
			if ( eBusy ) { return; }
			var ec = getContent();
			var plain = ec && ec.content ? ec.content.replace( /<[^>]*>/g, '' ).trim() : '';
			if ( ! plain ) {
				if ( manual ) { toast( 'Add some content first.', true ); }
				return;
			}
			eBusy = true;
			eSig = sig( ec.content );
			if ( manual ) { $erun.prop( 'disabled', true ); }
			$eout.attr( 'aria-busy', 'true' ).html( skeleton( 5 ) );
			post( 'aigs_extract', { post_id: ePid, content: ec.content, title: ec.title } ).done( function ( res ) {
				if ( ! res.success ) {
					$eout.html( '' );
					toast( ( res.data && res.data.message ) ? res.data.message : 'Analysis failed.', true );
					return;
				}
				$eout.attr( 'aria-busy', 'false' ).html( reportCard( res.data, 'Extractability score' ) );
				animateRing( $eout, res.data.score );
			} ).fail( function () { $eout.html( '' ); toast( 'Request failed.', true ); } )
				.always( function () { eBusy = false; if ( manual ) { $erun.prop( 'disabled', false ); } } );
		}

		$erun.on( 'click', function () { run( true ); } );

		// Debounced auto-update.
		function check() {
			eQueued = false;
			if ( ! $( '#aigs-extract-auto' ).is( ':checked' ) ) { return; }
			var ec = getContent();
			if ( ! ec ) { return; }
			if ( sig( ec.content ) === eSig ) { return; }
			run( false );
		}
		function schedule() {
			if ( eQueued ) { return; }
			eQueued = true;
			clearTimeout( eTimer );
			eTimer = setTimeout( check, 1400 );
		}

		if ( window.wp && wp.data && typeof wp.data.subscribe === 'function' ) {
			wp.data.subscribe( schedule );
		}
		$( document ).on( 'input keyup', '#content, #title', schedule );
		$( window ).on( 'load', function () {
			if ( window.tinymce && window.tinymce.get ) {
				var ed = window.tinymce.get( 'content' );
				if ( ed ) { ed.on( 'keyup change', schedule ); }
			}
		} );
	} )();

	/* ---------- Copy to clipboard (any [data-aigs-copy] button) ---------- */
	function aigsLegacyCopy( txt ) {
		var ta = document.createElement( 'textarea' );
		ta.value = txt;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.top = '0';
		ta.style.left = '0';
		ta.style.width = '1px';
		ta.style.height = '1px';
		ta.style.padding = '0';
		ta.style.border = '0';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		var selection = document.getSelection();
		var saved = ( selection && selection.rangeCount ) ? selection.getRangeAt( 0 ) : null;
		ta.focus();
		ta.select();
		try { ta.setSelectionRange( 0, ta.value.length ); } catch ( e ) {}
		var done = false;
		try { done = document.execCommand( 'copy' ); } catch ( e ) { done = false; }
		document.body.removeChild( ta );
		if ( saved && selection ) { selection.removeAllRanges(); selection.addRange( saved ); }
		return done;
	}

	$( document ).on( 'click', '[data-aigs-copy]', function () {
		var sel = $( this ).attr( 'data-aigs-copy' );
		var node = document.querySelector( sel );
		if ( ! node ) { return; }
		var txt = node.innerText || node.textContent || '';
		function ok() { toast( 'Copied to clipboard' ); }
		function fail() { toast( 'Copy failed. Select the text and press Ctrl+C or Cmd+C.', true ); }
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( txt ).then( ok, function () {
				if ( aigsLegacyCopy( txt ) ) { ok(); } else { fail(); }
			} );
		} else if ( aigsLegacyCopy( txt ) ) {
			ok();
		} else {
			fail();
		}
	} );

	/* ---------- Schema meta box FAQ repeater (moved from inline) ---------- */
	( function () {
		var sel = document.getElementById( 'aigs_schema_type' );
		var wrap = document.getElementById( 'aigs-faq-wrap' );
		if ( sel && wrap ) {
			sel.addEventListener( 'change', function () {
				wrap.style.display = ( 'FAQPage' === this.value ) ? '' : 'none';
			} );
		}
		var l10n = ( window.AIGS && AIGS.i18n ) ? AIGS.i18n : { question: 'Question', answer: 'Answer', remove: 'Remove' };
		var add = document.getElementById( 'aigs-add-faq' );
		if ( add ) {
			add.addEventListener( 'click', function () {
				var list = document.getElementById( 'aigs-faq-list' );
				var i = list.querySelectorAll( '.aigs-faq-item' ).length;
				var d = document.createElement( 'div' );
				d.className = 'aigs-faq-item';
				var q = document.createElement( 'input' );
				q.type = 'text';
				q.name = 'aigs_faqs[' + i + '][q]';
				q.placeholder = l10n.question;
				var a = document.createElement( 'textarea' );
				a.name = 'aigs_faqs[' + i + '][a]';
				a.rows = 2;
				a.placeholder = l10n.answer;
				var b = document.createElement( 'button' );
				b.type = 'button';
				b.className = 'button aigs-remove-faq';
				b.textContent = l10n.remove;
				d.appendChild( q );
				d.appendChild( a );
				d.appendChild( b );
				list.appendChild( d );
			} );
		}
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'aigs-remove-faq' ) ) {
				var item = e.target.closest( '.aigs-faq-item' );
				if ( item ) { item.remove(); }
			}
		} );
	} )();

} )( jQuery );
