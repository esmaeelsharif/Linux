/* global AIGS, jQuery */
( function ( $ ) {
	'use strict';

	/* ---------- helpers ---------- */
	var I18N = ( window.AIGS && AIGS.i18n ) ? AIGS.i18n : {};

	function t( key, fallback ) {
		return ( I18N[ key ] !== undefined ) ? I18N[ key ] : ( fallback || key );
	}

	// Minimal sprintf for %d / %s / %1$d style placeholders.
	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return String( str ).replace( /%(\d+\$)?[ds]/g, function ( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
			return v === undefined ? '' : v;
		} );
	}

	function post( action, data ) {
		data = data || {};
		data.action = action;
		data.nonce = AIGS.nonce;
		return $.post( AIGS.ajax, data );
	}

	function errMsg( res, fallback ) {
		return ( res && res.data && res.data.message ) ? res.data.message : ( fallback || t( 'failed' ) );
	}

	var ICON = {
		check: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>',
		x: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>',
		spark: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v4M5 12H1M12 7l1.6 3.4L17 12l-3.4 1.6L12 17l-1.6-3.4L7 12z"/></svg>'
	};

	function esc( s ) {
		return $( '<div>' ).text( s == null ? '' : s ).html();
	}

	function skeleton( rows ) {
		var h = '<div class="aigs-card">';
		for ( var i = 0; i < ( rows || 4 ); i++ ) {
			h += '<div class="aigs-skel aigs-skel-row" style="width:' + ( 60 + ( ( i * 17 ) % 35 ) ) + '%"></div>';
		}
		return h + '</div>';
	}

	/* ---------- toasts ---------- */
	function toast( msg, isErr ) {
		var $wrap = $( '.aigs-toasts' );
		if ( ! $wrap.length ) {
			$wrap = $( '<div class="aigs-toasts" role="status" aria-live="polite"></div>' ).appendTo( 'body' );
		}
		var $t = $( '<div class="aigs-toast' + ( isErr ? ' aigs-toast--err' : '' ) + '">' + ( isErr ? ICON.x : ICON.check ) + '<span></span></div>' );
		$t.find( 'span' ).text( msg );
		$wrap.append( $t );
		setTimeout( function () {
			$t.css( { transition: 'opacity .3s, transform .3s', opacity: 0, transform: 'translateY(8px)' } );
			setTimeout( function () { $t.remove(); }, 320 );
		}, isErr ? 5200 : 3400 );
	}

	/* ---------- score ring + report ---------- */
	function ringColor( s ) {
		if ( s >= 75 ) { return '#18794e'; }
		if ( s >= 50 ) { return '#9a6700'; }
		return '#c4332e';
	}
	function scoreRing( score, grade ) {
		var r = 46, c = 2 * Math.PI * r, col = ringColor( score );
		return '<div class="aigs-ring">'
			+ '<svg width="104" height="104" viewBox="0 0 104 104" aria-hidden="true">'
			+ '<circle class="track" cx="52" cy="52" r="' + r + '" fill="none" stroke-width="8"/>'
			+ '<circle class="meter" cx="52" cy="52" r="' + r + '" fill="none" stroke="' + col + '" stroke-width="8" stroke-dasharray="' + c + '" stroke-dashoffset="' + c + '"/>'
			+ '</svg>'
			+ '<div class="aigs-ring__txt"><span class="aigs-ring__num">' + parseInt( score, 10 ) + '</span><span class="aigs-ring__grade">' + esc( grade || '' ) + '</span></div>'
			+ '</div>';
	}
	function animateRing( $ctx, score ) {
		var r = 46, c = 2 * Math.PI * r, off = c * ( 1 - score / 100 );
		window.requestAnimationFrame( function () {
			$ctx.find( '.meter' ).css( 'stroke-dashoffset', off );
		} );
	}
	function pill( status, info ) {
		if ( info ) { return '<span class="aigs-pill aigs-pill--info"><span class="dot"></span>' + esc( t( 'optional' ) ) + '</span>'; }
		if ( 2 === status ) { return '<span class="aigs-pill aigs-pill--pass"><span class="dot"></span>' + esc( t( 'pass' ) ) + '</span>'; }
		if ( 1 === status ) { return '<span class="aigs-pill aigs-pill--warn"><span class="dot"></span>' + esc( t( 'partial' ) ) + '</span>'; }
		return '<span class="aigs-pill aigs-pill--fail"><span class="dot"></span>' + esc( t( 'fail' ) ) + '</span>';
	}
	function reportCard( d, metaLabel ) {
		var html = '<div class="aigs-card">'
			+ '<div class="aigs-scorewrap">' + scoreRing( d.score, d.grade )
			+ '<div class="aigs-scoremeta"><strong>' + esc( metaLabel ) + '</strong><p>' + esc( d.url ) + '</p></div></div>'
			+ '<div class="aigs-checks">';
		( d.checks || [] ).forEach( function ( c ) {
			html += '<div class="aigs-check"><div>' + pill( c.status, c.info ) + '</div>'
				+ '<div class="aigs-check__b"><strong>' + esc( c.label ) + '</strong><span>' + esc( c.remark ) + '</span></div></div>';
		} );
		return html + '</div></div>';
	}

	/* ---------- generic: [data-aigs-trigger] clicks another element ---------- */
	$( document ).on( 'click', '[data-aigs-trigger]', function ( e ) {
		e.preventDefault();
		$( $( this ).attr( 'data-aigs-trigger' ) ).first().trigger( 'click' );
	} );

	/* ---------- Site scan (batched; resumes after reload) ---------- */
	( function () {
		var $btn = $( '#aigs-scan-start' ), $box = $( '#aigs-scan-progress' ), busy = false;
		if ( ! $btn.length && ! $box.length ) { return; }

		function show( s ) {
			$box.prop( 'hidden', false );
			$box.find( '.aigs-progress__bar span' ).css( 'width', ( s.pct || 0 ) + '%' );
			var txt = ( 'running' === s.status && s.done >= s.total ) ? t( 'finalizing' ) : fmt( t( 'scanProgress' ), s.done || 0, s.total || 0 );
			$box.find( '.aigs-progress__txt' ).text( txt );
		}

		function step() {
			post( 'aigs_scan_step' ).done( function ( res ) {
				if ( ! res.success ) { busy = false; $btn.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
				var s = res.data;
				show( s );
				if ( 'running' === s.status ) {
					step();
				} else {
					$box.find( '.aigs-progress__bar span' ).css( 'width', '100%' );
					$box.find( '.aigs-progress__txt' ).text( t( 'scanDone' ) );
					toast( t( 'scanDone' ) );
					setTimeout( function () { window.location.reload(); }, 900 );
				}
			} ).fail( function () {
				// Transient failure (timeout on slow hosts): retry once after a pause.
				setTimeout( step, 3000 );
			} );
		}

		$btn.on( 'click', function () {
			if ( busy ) { return; }
			busy = true;
			$btn.prop( 'disabled', true );
			$box.prop( 'hidden', false ).find( '.aigs-progress__txt' ).text( t( 'scanning' ) );
			post( 'aigs_scan_start' ).done( function ( res ) {
				if ( ! res.success ) { busy = false; $btn.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
				show( res.data );
				step();
			} ).fail( function () { busy = false; $btn.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
		} );

		if ( '1' === String( $box.attr( 'data-running' ) ) ) {
			busy = true;
			$btn.prop( 'disabled', true );
			$box.prop( 'hidden', false ).find( '.aigs-progress__txt' ).text( t( 'scanning' ) );
			step();
		}
	} )();

	/* ---------- Action Center ---------- */
	function rowDone( $row, msg ) {
		$row.addClass( 'is-done' ).find( '.aigs-row__acts' ).html( '<span class="aigs-tag aigs-tag--yes">' + ICON.check + esc( msg ) + '</span>' );
	}

	$( document ).on( 'click', '[data-aigs-recheck]', function () {
		var $b = $( this ), id = $b.attr( 'data-aigs-recheck' ), $row = $b.closest( '.aigs-row' );
		var html = $b.html();
		$b.prop( 'disabled', true ).text( t( 'rechecking' ) );
		post( 'aigs_scan_post', { post_id: id } ).done( function ( res ) {
			$b.prop( 'disabled', false ).html( html );
			if ( ! res.success ) { toast( errMsg( res ), true ); return; }
			var params = new URLSearchParams( window.location.search );
			var current = params.get( 'rule' );
			if ( current && res.data.open.indexOf( current ) === -1 ) {
				rowDone( $row, t( 'fixedNow' ) );
				toast( t( 'fixedNow' ) );
			} else if ( res.data.resolved && res.data.resolved.length ) {
				toast( t( 'fixedNow' ) + ': ' + res.data.resolved.join( ', ' ) );
			} else {
				toast( t( 'stillOpen' ), true );
			}
		} ).fail( function () { $b.prop( 'disabled', false ).html( html ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-status]', function () {
		var $b = $( this ), status = $b.attr( 'data-aigs-status' ), $row = $b.closest( '.aigs-row' );
		$b.prop( 'disabled', true );
		post( 'aigs_finding_status', { id: $b.attr( 'data-id' ), status: status } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			var msg = 'ignored' === status ? t( 'ignored' ) : t( 'reopened' );
			rowDone( $row, msg );
			toast( msg );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-ignore-rule]', function () {
		var $b = $( this );
		if ( ! window.confirm( t( 'confirmIgnore' ) ) ) { return; }
		$b.prop( 'disabled', true );
		post( 'aigs_rule_ignore', { rule: $b.attr( 'data-aigs-ignore-rule' ) } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			window.location.href = AIGS.actions;
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-autofix]', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_autofix', { fix: $b.attr( 'data-aigs-autofix' ) } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			rowDone( $b.closest( '.aigs-row' ), t( 'fixedNow' ) );
			toast( t( 'fixedNow' ) );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-important]', function () {
		var $b = $( this );
		post( 'aigs_important', { post_id: $b.attr( 'data-aigs-important' ) } ).done( function ( res ) {
			if ( ! res.success ) { toast( errMsg( res ), true ); return; }
			$b.toggleClass( 'is-on', !! res.data.important );
			toast( res.data.important ? t( 'important' ) : t( 'notImportant' ) );
		} );
	} );

	$( document ).on( 'click', '[data-aigs-vis-draft]', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_vis_draft', { finding: $b.attr( 'data-aigs-vis-draft' ) } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			toast( t( 'draftCreated' ) );
			window.location.href = res.data.edit;
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	/* ---------- One-click internal links ---------- */
	$( document ).on( 'click', '[data-aigs-links]', function () {
		var $b = $( this ), $row = $b.closest( '.aigs-row' ), $box = $row.find( '.aigs-linkbox' );
		$b.prop( 'disabled', true );
		$box.prop( 'hidden', false ).html( '<p class="aigs-muted aigs-small">' + esc( t( 'findingLinks' ) ) + '</p>' );
		post( 'aigs_links_preview', { post_id: $b.attr( 'data-post' ), mode: $b.attr( 'data-aigs-links' ) } ).done( function ( res ) {
			if ( ! res.success ) { $box.empty().prop( 'hidden', true ); toast( errMsg( res ), true ); return; }
			var items = res.data.items || [];
			if ( ! items.length ) { $box.html( '<p class="aigs-muted aigs-small">' + esc( t( 'noLinkSpots' ) ) + '</p>' ); return; }
			var h = '<ul class="aigs-linklist">';
			items.forEach( function ( it, i ) {
				h += '<li><label><input type="checkbox" class="aigs-link-pick" checked data-i="' + i + '"> '
					+ '<span class="aigs-linklist__meta">' + esc( t( 'linkFrom' ) ) + ' <strong dir="auto">' + esc( it.source_title ) + '</strong> ' + esc( t( 'linkTo' ) ) + ' <strong dir="auto">' + esc( it.target_title ) + '</strong></span>'
					+ '<span class="aigs-linklist__snip" dir="auto">' + esc( it.snippet[ 0 ] ) + ' <mark>' + esc( it.snippet[ 1 ] ) + '</mark> ' + esc( it.snippet[ 2 ] ) + '</span></label></li>';
			} );
			h += '</ul><p><button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm aigs-links-apply">' + esc( t( 'insertLinks' ) ) + '</button></p>';
			$box.html( h ).data( 'items', items );
		} ).fail( function () { $box.empty().prop( 'hidden', true ); toast( t( 'requestFailed' ), true ); } )
			.always( function () { $b.prop( 'disabled', false ); } );
	} );

	$( document ).on( 'click', '.aigs-links-apply', function () {
		var $b = $( this ), $box = $b.closest( '.aigs-linkbox' ), all = $box.data( 'items' ) || [], items = [];
		$box.find( '.aigs-link-pick:checked' ).each( function () {
			var it = all[ parseInt( $( this ).attr( 'data-i' ), 10 ) ];
			items.push( { source: it.source, target: it.target, anchor: it.anchor } );
		} );
		if ( ! items.length ) { return; }
		$b.prop( 'disabled', true );
		post( 'aigs_links_apply', { items: items } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			var msg = fmt( t( 'linksAdded' ), res.data.applied );
			$box.html( '<p><span class="aigs-tag aigs-tag--yes">' + ICON.check + esc( msg ) + '</span> '
				+ ( res.data.op ? '<button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" data-aigs-links-undo="' + esc( res.data.op ) + '">' + esc( t( 'undo' ) ) + '</button>' : '' ) + '</p>' );
			toast( msg );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-links-undo]', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_links_undo', { op: $b.attr( 'data-aigs-links-undo' ) } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			toast( t( 'undone' ) );
			$b.replaceWith( '<span class="aigs-muted aigs-small">' + esc( t( 'undone' ) ) + '</span>' );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	/* ---------- Brand facts page draft ---------- */
	$( document ).on( 'click', '#aigs-facts-page, [data-aigs-trigger-facts]', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_facts_page' ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			toast( t( 'draftCreated' ) );
			window.location.href = res.data.edit;
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	/* ---------- AI revenue refresh ---------- */
	$( document ).on( 'click', '#aigs-revenue-refresh', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_revenue_refresh' ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			window.location.reload();
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( '#aigs-onboard-dismiss' ).on( 'click', function () {
		post( 'aigs_onboarding_dismiss' );
		$( '#aigs-onboard' ).slideUp( 180 );
	} );

	/* ---------- URL audit ---------- */
	$( '#aigs-run-audit' ).on( 'click', function () {
		var $btn = $( this ), url = $( '#aigs-audit-url' ).val(), $out = $( '#aigs-audit-result' );
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 6 ) );
		post( 'aigs_audit', { url: url } ).done( function ( res ) {
			if ( ! res.success ) { $out.html( '' ); toast( errMsg( res, t( 'auditFailed' ) ), true ); return; }
			var d = res.data;
			if ( d.error ) { $out.html( '' ); toast( d.error, true ); return; }
			$out.attr( 'aria-busy', 'false' ).html( reportCard( d, t( 'geoScore' ) ) );
			animateRing( $out, d.score );
		} ).fail( function () { $out.html( '' ); toast( t( 'requestFailed' ), true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- Site health ---------- */
	$( '#aigs-run-health' ).on( 'click', function () {
		var $btn = $( this ), $out = $( '#aigs-health-result' );
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 6 ) );
		post( 'aigs_health' ).done( function ( res ) {
			if ( ! res.success ) { toast( t( 'failed' ), true ); return; }
			var html = '<div class="aigs-card"><div class="aigs-checks">';
			res.data.forEach( function ( c ) {
				var p = c.pass ? '<span class="aigs-pill aigs-pill--pass"><span class="dot"></span>' + esc( t( 'ok' ) ) + '</span>' : '<span class="aigs-pill aigs-pill--fail"><span class="dot"></span>' + esc( t( 'fix' ) ) + '</span>';
				html += '<div class="aigs-check"><div>' + p + '</div><div class="aigs-check__b"><strong>' + esc( c.label ) + '</strong><span>' + esc( c.remark ) + '</span></div></div>';
			} );
			html += '</div></div>';
			$out.attr( 'aria-busy', 'false' ).html( html );
		} ).fail( function () { toast( t( 'requestFailed' ), true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- IndexNow ---------- */
	$( '#aigs-submit-indexnow' ).on( 'click', function () {
		var $btn = $( this ), url = $( '#aigs-indexnow-url' ).val();
		$btn.prop( 'disabled', true );
		post( 'aigs_indexnow', { url: url } ).done( function ( res ) {
			if ( res.success ) {
				var code = res.data.code, ok = ( 200 === code || 202 === code );
				toast( ( ok ? t( 'submitted' ) : t( 'responded' ) ) + ' (HTTP ' + code + ')', ! ok );
			} else {
				toast( errMsg( res ), true );
			}
		} ).fail( function () { toast( t( 'requestFailed' ), true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	/* ---------- AI visibility ---------- */
	$( '#aigs-suggest-prompts' ).on( 'click', function () {
		var $out = $( '#aigs-prompt-list' );
		post( 'aigs_prompt_suggest' ).done( function ( res ) {
			if ( ! res.success ) { return; }
			var html = '';
			res.data.prompts.forEach( function ( p ) {
				html += '<button type="button" class="aigs-chip">' + ICON.spark + esc( p ) + '</button>';
			} );
			$out.html( html );
		} );
	} );
	$( document ).on( 'click', '.aigs-chip', function () {
		var text = $( this ).text(), $ta = $( '#aigs_prompts' );
		if ( $ta.length ) {
			var cur = $.trim( $ta.val() );
			if ( cur.split( /\n/ ).indexOf( text ) === -1 ) {
				$ta.val( cur ? cur + '\n' + text : text );
			}
			$( this ).addClass( 'is-added' ).prop( 'disabled', true );
		} else {
			$( '#aigs-prompt' ).val( text ).trigger( 'focus' );
		}
	} );

	function visResult( d ) {
		var m = d.mentioned
			? '<span class="aigs-tag aigs-tag--yes">' + ICON.check + esc( d.recommended ? t( 'recommended' ) : t( 'mentioned' ) ) + '</span>'
			: '<span class="aigs-tag aigs-tag--no">' + ICON.x + esc( t( 'notMentioned' ) ) + '</span>';
		var c = d.cited
			? '<span class="aigs-tag aigs-tag--yes">' + ICON.check + esc( t( 'cited' ) ) + '</span>'
			: '<span class="aigs-tag aigs-tag--no">' + ICON.x + esc( t( 'notCited' ) ) + '</span>';
		var html = '<div class="aigs-card aigs-visres"><div class="aigs-visres__tags">' + m + ' ' + c;
		if ( d.position ) { html += ' <span class="aigs-badge">' + esc( t( 'position' ) ) + ' #' + parseInt( d.position, 10 ) + '</span>'; }
		html += ' <span class="aigs-muted aigs-small">' + esc( t( 'via' ) ) + ' ' + esc( d.provider ) + ' · ' + esc( d.model ) + ' · ' + esc( d.grounded ? t( 'webSearch' ) : t( 'modelOnly' ) ) + '</span></div>';
		var comps = [];
		$.each( d.competitors || {}, function ( name, hit ) { if ( hit ) { comps.push( name ); } } );
		if ( comps.length ) {
			html += '<p class="aigs-small"><strong>' + esc( t( 'competitors' ) ) + ':</strong> ' + esc( comps.join( ', ' ) ) + '</p>';
		}
		if ( d.issues && d.issues.length ) {
			html += '<div class="aigs-callout aigs-callout--warn"><p><strong>' + esc( t( 'wrongFacts' ) ) + '</strong><br>' + d.issues.map( esc ).join( '<br>' ) + '</p></div>';
		}
		html += '<div class="aigs-answertext" dir="auto">' + esc( d.answer ).replace( /\n/g, '<br>' ) + '</div>';
		if ( d.citations && d.citations.length ) {
			html += '<p class="aigs-small"><strong>' + esc( t( 'sources' ) ) + ':</strong></p><ul class="aigs-sources">';
			d.citations.slice( 0, 12 ).forEach( function ( u ) {
				html += '<li><a href="' + esc( u ) + '" target="_blank" rel="noopener noreferrer">' + esc( u ) + '</a></li>';
			} );
			html += '</ul>';
		}
		return html + '</div>';
	}

	$( '#aigs-run-scan' ).on( 'click', function () {
		var $btn = $( this ), prompt = $( '#aigs-prompt' ).val(), $out = $( '#aigs-scan-result' );
		if ( ! prompt ) { toast( t( 'writePrompt' ), true ); return; }
		$btn.prop( 'disabled', true );
		$out.attr( 'aria-busy', 'true' ).html( skeleton( 3 ) );
		post( 'aigs_ai_scan', { prompt: prompt } ).done( function ( res ) {
			if ( ! res.success ) { $out.html( '' ); toast( errMsg( res ), true ); return; }
			$out.attr( 'aria-busy', 'false' ).html( visResult( res.data ) );
		} ).fail( function () { $out.html( '' ); toast( t( 'requestFailed' ), true ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	} );

	$( '#aigs-vis-run' ).on( 'click', function () {
		var $btn = $( this ), $box = $( '#aigs-vis-progress' );
		$btn.prop( 'disabled', true );
		$box.prop( 'hidden', false );
		function show( i, n ) {
			$box.find( '.aigs-progress__bar span' ).css( 'width', ( n ? Math.round( 100 * i / n ) : 0 ) + '%' );
			$box.find( '.aigs-progress__txt' ).text( fmt( t( 'visProgress' ), i, n ) );
		}
		function step() {
			post( 'aigs_vis_step' ).done( function ( res ) {
				if ( ! res.success ) { $btn.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
				var d = res.data;
				if ( d.error ) { toast( d.error, true ); }
				show( d.index, d.total );
				if ( 'running' === d.status ) {
					step();
				} else {
					$box.find( '.aigs-progress__txt' ).text( t( 'visDone' ) );
					setTimeout( function () { window.location.reload(); }, 900 );
				}
			} ).fail( function () { setTimeout( step, 3000 ); } );
		}
		post( 'aigs_vis_start' ).done( function ( res ) {
			if ( ! res.success ) { $btn.prop( 'disabled', false ); $box.prop( 'hidden', true ); toast( errMsg( res ), true ); return; }
			show( 0, res.data.total );
			step();
		} ).fail( function () { $btn.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	$( document ).on( 'click', '[data-aigs-answer]', function () {
		var $out = $( '#aigs-answer-view' );
		$out.html( skeleton( 4 ) );
		post( 'aigs_vis_answer', { id: $( this ).attr( 'data-aigs-answer' ) } ).done( function ( res ) {
			if ( ! res.success ) { $out.html( '' ); toast( errMsg( res ), true ); return; }
			var d = res.data;
			$out.html( '<div class="aigs-answerview"><div class="aigs-answerview__head"><strong dir="auto">' + esc( d.prompt ) + '</strong><button type="button" class="aigs-btn aigs-btn--ghost aigs-btn--sm" id="aigs-answer-close">' + esc( t( 'close' ) ) + '</button></div>' + visResult( d ) + '</div>' );
			$out[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
	} );
	$( document ).on( 'click', '#aigs-answer-close', function () { $( '#aigs-answer-view' ).empty(); } );

	/* ---------- WooCommerce bulk fixes ---------- */
	$( document ).on( 'click', '[data-aigs-woo-preview]', function () {
		var $b = $( this ), fix = $b.attr( 'data-aigs-woo-preview' ), $out = $( '#aigs-woo-preview' );
		$b.prop( 'disabled', true );
		$out.html( skeleton( 5 ) );
		post( 'aigs_woo_preview', { fix: fix } ).done( function ( res ) {
			if ( ! res.success ) { $out.html( '' ); toast( errMsg( res ), true ); return; }
			var d = res.data;
			if ( d.error ) { toast( d.error, true ); }
			if ( ! d.items.length ) { $out.html( '<div class="aigs-empty"><p>' + esc( t( 'noItems' ) ) + '</p></div>' ); return; }
			var html = '<div class="aigs-wooprev" data-fix="' + esc( fix ) + '"><div class="aigs-tablewrap"><table class="aigs-table"><thead><tr><th><input type="checkbox" class="aigs-woo-all" checked aria-label="all"></th><th>' + esc( t( 'product' ) ) + '</th><th>' + esc( t( 'proposed' ) ) + '</th></tr></thead><tbody>';
			d.items.forEach( function ( it ) {
				html += '<tr data-id="' + parseInt( it.id, 10 ) + '"><td><input type="checkbox" class="aigs-woo-pick" checked></td>'
					+ '<td><a href="' + esc( it.edit ) + '" target="_blank" rel="noopener">' + esc( it.name ) + '</a></td>'
					+ '<td><textarea rows="2" class="aigs-woo-val">' + esc( it.value ) + '</textarea></td></tr>';
			} );
			html += '</tbody></table></div><p><button type="button" class="aigs-btn aigs-btn--primary" id="aigs-woo-apply">' + esc( t( 'applySelected' ) ) + '</button> '
				+ '<button type="button" class="aigs-btn aigs-btn--ghost" id="aigs-woo-cancel">' + esc( t( 'cancel' ) ) + '</button></p></div>';
			$out.html( html );
		} ).fail( function () { $out.html( '' ); toast( t( 'requestFailed' ), true ); } )
			.always( function () { $b.prop( 'disabled', false ); } );
	} );
	$( document ).on( 'change', '.aigs-woo-all', function () {
		$( this ).closest( 'table' ).find( '.aigs-woo-pick' ).prop( 'checked', this.checked );
	} );
	$( document ).on( 'click', '#aigs-woo-cancel', function () { $( '#aigs-woo-preview' ).empty(); } );
	$( document ).on( 'click', '#aigs-woo-apply', function () {
		var $b = $( this ), $wrap = $b.closest( '.aigs-wooprev' ), items = [];
		$wrap.find( 'tbody tr' ).each( function () {
			var $tr = $( this );
			if ( $tr.find( '.aigs-woo-pick' ).is( ':checked' ) ) {
				items.push( { id: $tr.attr( 'data-id' ), value: $tr.find( '.aigs-woo-val' ).val() } );
			}
		} );
		if ( ! items.length ) { return; }
		$b.prop( 'disabled', true );
		post( 'aigs_woo_apply', { fix: $wrap.attr( 'data-fix' ), items: items } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			toast( fmt( t( 'applied' ), res.data.applied ) );
			setTimeout( function () { window.location.reload(); }, 1200 );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );
	$( document ).on( 'click', '[data-aigs-woo-undo]', function () {
		var $b = $( this );
		$b.prop( 'disabled', true );
		post( 'aigs_woo_undo', { op: $b.attr( 'data-aigs-woo-undo' ) } ).done( function ( res ) {
			if ( ! res.success ) { $b.prop( 'disabled', false ); toast( errMsg( res ), true ); return; }
			toast( fmt( t( 'restored' ), res.data.restored ) );
			$b.closest( 'tr' ).fadeOut( 200 );
		} ).fail( function () { $b.prop( 'disabled', false ); toast( t( 'requestFailed' ), true ); } );
	} );

	/* ---------- Schema meta box FAQ repeater ---------- */
	function addFaqItem( q, a ) {
		var list = document.getElementById( 'aigs-faq-list' );
		if ( ! list ) { return false; }
		// Reuse a single empty row if present.
		var rows = list.querySelectorAll( '.aigs-faq-item' );
		if ( 1 === rows.length ) {
			var fq = rows[ 0 ].querySelector( 'input' ), fa = rows[ 0 ].querySelector( 'textarea' );
			if ( fq && fa && ! fq.value && ! fa.value ) {
				fq.value = q || '';
				fa.value = a || '';
				return true;
			}
		}
		var max = -1;
		list.querySelectorAll( 'input[name^="aigs_faqs["]' ).forEach( function ( el ) {
			var m = el.name.match( /aigs_faqs\[(\d+)\]/ );
			if ( m ) { max = Math.max( max, parseInt( m[ 1 ], 10 ) ); }
		} );
		var i = max + 1;
		var d = document.createElement( 'div' );
		d.className = 'aigs-faq-item';
		var qi = document.createElement( 'input' );
		qi.type = 'text';
		qi.name = 'aigs_faqs[' + i + '][q]';
		qi.placeholder = t( 'question' );
		qi.value = q || '';
		var ai = document.createElement( 'textarea' );
		ai.name = 'aigs_faqs[' + i + '][a]';
		ai.rows = 2;
		ai.placeholder = t( 'answer' );
		ai.value = a || '';
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = 'button aigs-remove-faq';
		b.textContent = t( 'remove' );
		d.appendChild( qi );
		d.appendChild( ai );
		d.appendChild( b );
		list.appendChild( d );
		return true;
	}

	( function () {
		var sel = document.getElementById( 'aigs_schema_type' );
		var wrap = document.getElementById( 'aigs-faq-wrap' );
		if ( sel && wrap ) {
			sel.addEventListener( 'change', function () {
				wrap.style.display = ( 'FAQPage' === this.value ) ? '' : 'none';
			} );
		}
		var add = document.getElementById( 'aigs-add-faq' );
		if ( add ) {
			add.addEventListener( 'click', function () { addFaqItem( '', '' ); } );
		}
		document.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList && e.target.classList.contains( 'aigs-remove-faq' ) ) {
				var item = e.target.closest( '.aigs-faq-item' );
				if ( item ) { item.remove(); }
			}
		} );
	} )();

	/* ---------- Editor: extractability + Answer Optimizer (live) ---------- */
	( function () {
		var $erun = $( '#aigs-extract-run' );
		if ( ! $erun.length ) { return; }

		var ePid    = $erun.data( 'post' );
		var $eout   = $( '#aigs-extract-result' );
		var $panel  = $( '#aigs-answer-panel' );
		var hasAI   = '1' === String( $panel.attr( 'data-ai' ) );
		var eBusy   = false;
		var eSig    = null;
		var eTimer  = null;
		var eQueued = false;
		var lastFaq = [];
		var lastQ   = '';

		function blockEditor() {
			return window.wp && wp.data && typeof wp.data.select === 'function' && wp.data.select( 'core/editor' ) ? wp.data.select( 'core/editor' ) : null;
		}

		// Read the current (unsaved) content from whichever editor is active.
		function getContent() {
			try {
				var ed = blockEditor();
				if ( ed && typeof ed.getEditedPostContent === 'function' ) {
					var c = ed.getEditedPostContent();
					if ( typeof c === 'string' ) {
						var ti = '';
						try { ti = ed.getEditedPostAttribute( 'title' ) || ''; } catch ( e ) {}
						return { content: c, title: ti };
					}
				}
			} catch ( e ) {}
			if ( window.tinymce && window.tinymce.get ) {
				var tm = window.tinymce.get( 'content' );
				if ( tm && ! tm.isHidden() ) {
					return { content: tm.getContent(), title: ( document.getElementById( 'title' ) || {} ).value || '' };
				}
			}
			var ta = document.getElementById( 'content' );
			if ( ta ) { return { content: ta.value, title: ( document.getElementById( 'title' ) || {} ).value || '' }; }
			return null;
		}

		function insertAtTop( text ) {
			try {
				if ( window.wp && wp.blocks && wp.data && wp.data.dispatch && blockEditor() ) {
					var block = wp.blocks.createBlock( 'core/paragraph', { content: esc( text ) } );
					wp.data.dispatch( 'core/block-editor' ).insertBlocks( block, 0 );
					return true;
				}
			} catch ( e ) {}
			if ( window.tinymce && window.tinymce.get ) {
				var tm = window.tinymce.get( 'content' );
				if ( tm && ! tm.isHidden() ) {
					tm.setContent( '<p>' + esc( text ) + '</p>' + tm.getContent() );
					return true;
				}
			}
			var ta = document.getElementById( 'content' );
			if ( ta ) {
				ta.value = text + '\n\n' + ta.value;
				return true;
			}
			return false;
		}

		function sig( c ) { return c.length + '~' + c.slice( 0, 160 ) + '~' + c.slice( -160 ); }

		function renderAnswer( d ) {
			var a = d.answer;
			if ( ! a ) { $panel.empty(); return; }
			lastFaq = a.faq || [];
			lastQ = a.question ? a.question.text : '';
			var cls = 2 === a.direct ? 'pass' : ( 1 === a.direct ? 'warn' : 'fail' );
			var lbl = 2 === a.direct ? t( 'answerOk' ) : ( 1 === a.direct ? t( 'answerWork' ) : t( 'answerMissing' ) );
			var h = '<div class="aigs-card aigs-ans">'
				+ '<div class="aigs-ans__q"><span class="aigs-ans__k">' + esc( t( 'mainQuestion' ) ) + ' · ' + esc( a.question.inferred ? t( 'inferred' ) : t( 'fromTitle' ) ) + '</span><strong dir="auto">' + esc( a.question.text ) + '</strong></div>'
				+ '<div class="aigs-ans__lead"><span class="aigs-ans__k">' + esc( t( 'opening' ) ) + '</span>'
				+ '<span class="aigs-pill aigs-pill--' + cls + '"><span class="dot"></span>' + esc( lbl ) + '</span>'
				+ ( a.lead ? '<blockquote dir="auto">' + esc( a.lead ) + '</blockquote>' : '' ) + '</div>';
			if ( hasAI && 2 !== a.direct ) {
				h += '<p><button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" id="aigs-draft-answer">' + ICON.spark + esc( t( 'draftAnswer' ) ) + '</button></p><div id="aigs-draft-out"></div>';
			}
			if ( a.tips && a.tips.length ) {
				h += '<div class="aigs-ans__tips"><span class="aigs-ans__k">' + esc( t( 'tips' ) ) + '</span><ul>';
				a.tips.forEach( function ( tip ) { h += '<li>' + esc( tip ) + '</li>'; } );
				h += '</ul></div>';
			}
			if ( a.unanswered && a.unanswered.length ) {
				h += '<div class="aigs-ans__tips"><span class="aigs-ans__k">' + esc( t( 'unanswered' ) ) + '</span><ul>';
				a.unanswered.forEach( function ( q ) { h += '<li>' + esc( q ) + '</li>'; } );
				h += '</ul></div>';
			}
			if ( lastFaq.length >= 2 && document.getElementById( 'aigs-faq-list' ) ) {
				h += '<div class="aigs-ans__tips"><span class="aigs-ans__k">' + esc( t( 'faqFound' ) ) + ' (' + lastFaq.length + ')</span>'
					+ '<p><button type="button" class="aigs-btn aigs-btn--sm" id="aigs-faq-import">' + esc( t( 'addFaq' ) ) + '</button></p></div>';
			}
			var cit = d.citability;
			if ( cit ) {
				h += '<div class="aigs-ans__tips aigs-cit"><span class="aigs-ans__k">' + esc( t( 'passages' ) ) + '</span>';
				if ( ! cit.passages || ! cit.passages.length ) {
					h += '<p class="aigs-muted aigs-small">' + esc( t( 'passagesNone' ) ) + '</p>';
				} else {
					h += '<p class="aigs-muted aigs-small" title="' + esc( t( 'passagesHelp' ) ) + '">' + esc( fmt( t( 'passagesSum' ), cit.score, cit.weak, cit.passages.length ) ) + '</p><ul class="aigs-cit__list">';
					cit.passages.forEach( function ( ps ) {
						var cls = ps.score >= 75 ? 'good' : ( ps.score >= 50 ? 'ok' : 'bad' );
						h += '<li><span class="aigs-badge aigs-badge--' + cls + '">' + parseInt( ps.score, 10 ) + '</span><span><strong dir="auto">' + esc( ps.heading ) + '</strong>'
							+ ( ps.tip ? '<em>' + esc( ps.tip ) + '</em>' : '' ) + '</span></li>';
					} );
					h += '</ul>';
				}
				h += '</div>';
			}
			if ( d.open && d.open.length ) {
				h += '<div class="aigs-ans__tips"><span class="aigs-ans__k">' + esc( t( 'openTasks' ) ) + '</span><ul>';
				d.open.forEach( function ( o ) { h += '<li>' + esc( o.title ) + '</li>'; } );
				h += '</ul></div>';
			}
			$panel.html( h + '</div>' );
		}

		function run( manual ) {
			if ( eBusy ) { return; }
			var ec = getContent();
			var plain = ec && ec.content ? ec.content.replace( /<[^>]*>/g, '' ).trim() : '';
			if ( ! plain ) {
				if ( manual ) { toast( t( 'noContent' ), true ); }
				return;
			}
			eBusy = true;
			eSig = sig( ec.content );
			if ( manual ) { $erun.prop( 'disabled', true ); }
			$eout.attr( 'aria-busy', 'true' ).html( skeleton( 5 ) );
			post( 'aigs_extract', { post_id: ePid, content: ec.content, title: ec.title } ).done( function ( res ) {
				if ( ! res.success ) {
					$eout.html( '' );
					toast( errMsg( res, t( 'analysisFail' ) ), true );
					return;
				}
				renderAnswer( res.data );
				$eout.attr( 'aria-busy', 'false' ).html( reportCard( res.data, t( 'extractScore' ) ) );
				animateRing( $eout, res.data.score );
			} ).fail( function () { $eout.html( '' ); toast( t( 'requestFailed' ), true ); } )
				.always( function () { eBusy = false; if ( manual ) { $erun.prop( 'disabled', false ); } } );
		}

		$erun.on( 'click', function () { run( true ); } );

		$( document ).on( 'click', '#aigs-faq-import', function () {
			var sel = document.getElementById( 'aigs_schema_type' );
			if ( sel ) {
				sel.value = 'FAQPage';
				sel.dispatchEvent( new Event( 'change' ) );
			}
			var existing = [];
			$( '#aigs-faq-list input[name$="[q]"]' ).each( function () { existing.push( $.trim( this.value ) ); } );
			lastFaq.forEach( function ( f ) {
				if ( existing.indexOf( f.q ) === -1 ) { addFaqItem( f.q, f.a ); }
			} );
			toast( t( 'faqAdded' ) );
			var box = document.getElementById( 'aigs_schema' );
			if ( box ) { box.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
		} );

		$( document ).on( 'click', '#aigs-draft-answer', function () {
			var $b = $( this ), ec = getContent(), $o = $( '#aigs-draft-out' );
			if ( ! ec ) { return; }
			$b.prop( 'disabled', true );
			$o.html( '<p class="aigs-muted aigs-small">' + esc( t( 'drafting' ) ) + '</p>' );
			post( 'aigs_answer_draft', { post_id: ePid, content: ec.content, title: ec.title, question: lastQ } ).done( function ( res ) {
				if ( ! res.success ) { $o.empty(); toast( errMsg( res ), true ); return; }
				var d = res.data;
				$o.html( '<div class="aigs-draft"><textarea rows="4" id="aigs-draft-text" dir="auto">' + esc( d.draft ) + '</textarea>'
					+ '<p class="aigs-muted aigs-small">' + esc( t( 'reviewDraft' ) ) + ' ' + esc( t( 'aiLeft' ) ) + ' ' + parseInt( d.left, 10 ) + '</p>'
					+ '<p><button type="button" class="aigs-btn aigs-btn--primary aigs-btn--sm" id="aigs-draft-insert">' + esc( t( 'insertTop' ) ) + '</button> '
					+ '<button type="button" class="aigs-btn aigs-btn--sm" data-aigs-copy="#aigs-draft-text">' + esc( t( 'copy' ) ) + '</button></p></div>' );
			} ).fail( function () { $o.empty(); toast( t( 'requestFailed' ), true ); } )
				.always( function () { $b.prop( 'disabled', false ); } );
		} );

		$( document ).on( 'click', '#aigs-draft-insert', function () {
			var text = $.trim( $( '#aigs-draft-text' ).val() );
			if ( text && insertAtTop( text ) ) {
				toast( t( 'inserted' ) );
				$( '#aigs-draft-out' ).empty();
				setTimeout( function () { run( false ); }, 600 );
			}
		} );

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

	/* ---------- Settings: local business card ---------- */
	( function () {
		var $t = $( '#aigs_btype' );
		if ( ! $t.length ) { return; }
		function sync() { $( '.aigs-local-only' ).toggle( '1' === String( $t.find( 'option:selected' ).attr( 'data-local' ) ) ); }
		$t.on( 'change', sync );
		sync();
	} )();

	/* ---------- Settings: AI provider ---------- */
	( function () {
		var $sel = $( '#aigs_prov' );
		if ( ! $sel.length ) { return; }
		function sync() {
			var $o = $sel.find( 'option:selected' ), v = $sel.val(), keys = $o.attr( 'data-keys' );
			$( '.aigs-custom-only' ).toggle( 'custom' === v );
			$( '.aigs-gateway-only' ).toggle( 'custom' === v || 'openrouter' === v );
			$( '#aigs_model' ).attr( 'placeholder', $o.attr( 'data-model' ) || '' );
			if ( keys ) {
				$( '#aigs-keys-link' ).attr( 'href', keys );
				$( '#aigs-keys-link-wrap' ).show();
			} else {
				$( '#aigs-keys-link-wrap' ).hide();
			}
		}
		$sel.on( 'change', sync );
		sync();
		$( '#aigs-ai-test' ).on( 'click', function () {
			var $b = $( this ), $out = $( '#aigs-ai-test-out' );
			$b.prop( 'disabled', true );
			$out.text( '…' );
			post( 'aigs_ai_test' ).done( function ( res ) {
				if ( ! res.success ) { $out.text( '' ); toast( errMsg( res ), true ); return; }
				$out.text( res.data.message );
				toast( res.data.message );
			} ).fail( function () { $out.text( '' ); toast( t( 'requestFailed' ), true ); } )
				.always( function () { $b.prop( 'disabled', false ); } );
		} );
	} )();

	/* ---------- Copy to clipboard (any [data-aigs-copy] button) ---------- */
	function legacyCopy( txt ) {
		var ta = document.createElement( 'textarea' );
		ta.value = txt;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.top = '0';
		ta.style.left = '0';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		var done = false;
		try { done = document.execCommand( 'copy' ); } catch ( e ) { done = false; }
		document.body.removeChild( ta );
		return done;
	}

	$( document ).on( 'click', '[data-aigs-copy]', function () {
		var node = document.querySelector( $( this ).attr( 'data-aigs-copy' ) );
		if ( ! node ) { return; }
		var txt = ( 'TEXTAREA' === node.tagName || 'INPUT' === node.tagName ) ? node.value : ( node.innerText || node.textContent || '' );
		function ok() { toast( t( 'copied' ) ); }
		function fail() { toast( t( 'copyFailed' ), true ); }
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( txt ).then( ok, function () {
				if ( legacyCopy( txt ) ) { ok(); } else { fail(); }
			} );
		} else if ( legacyCopy( txt ) ) {
			ok();
		} else {
			fail();
		}
	} );

} )( jQuery );
