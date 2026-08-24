/**
 * Theme functions file
 *
 * Contains handlers for navigation, accessibility, header sizing
 * footer widgets and Featured Content slider
 *
 */
var menu_breakpoint = 1000;
( function( $ ) {
	var body    = $( 'body' ),
		_window = $( window );
	_window.on( 'hashchange.isotype', function() {
		var hash = location.hash.substring( 1 ), element;

		if ( ! hash ) {
			return;
		}

		element = document.getElementById( hash );

		if ( element ) {
			if ( ! /^(?:a|select|input|button|textarea)$/i.test( element.tagName ) ) {
				element.tabIndex = -1;
			}

			element.focus();

			// Repositions the window on jump-to-anchor to account for header height.
			window.scrollBy( 0, -80 );
		}
	} );

$( function() {
/*
	By Osvaldas Valutis, www.osvaldas.info
	Available for use under the MIT License
*/
	$.fn.doubleTapToGo = function( params )
	{
		if( !( 'ontouchstart' in window ) &&
			!navigator.msMaxTouchPoints &&
			!navigator.userAgent.toLowerCase().match( /windows phone os 7/i ) ) return false;

		this.each( function()
		{
			var curItem = false;

			$( this ).on( 'click', function( e )
			{
				var item = $( this );
				if( item[ 0 ] != curItem[ 0 ] )
				{
					e.preventDefault();
					curItem = item;
				}
			});

			$( document ).on( 'click touchstart MSPointerDown', function( e )
			{
				var resetItem = true,
					parents	  = $( e.target ).parents();

				for( var i = 0; i < parents.length; i++ )
					if( parents[ i ] == curItem[ 0 ] )
						resetItem = false;

				if( resetItem )
					curItem = false;
			});
		});
		return this;
	};
	function isotype_toggle(slug){
		var toggler = $('.'+slug+'-toggle');
//f		toggler.css("pointer-events","none");		
		var inSameGroup = $('[data-toggle-group="'+toggler.attr('data-toggle-group')+'"]');
		toggler.on( 'click.isotype', function( event ) {
					var that = $( this ),
						wrapper = $( '.'+slug+'-box-wrapper' );
					that.toggleClass( 'active' );
					inSameGroup.each(function(){
						if(!$(this).hasClass(slug+'-toggle') && $(this).hasClass('active')){
							$(this).toggleClass('active');
							var siblings = $(this).attr('class').split(/\s+/),re=/-toggle$/i;
							var siblingSlug = siblings[0].replace('-toggle','');
							$( '.'+siblingSlug+'-box-wrapper' ).addClass( 'hide' );
						}
					});
					wrapper.toggleClass( 'hide' );
					if ( that.is( '.active' ) || $( '.'+slug+'-toggle' )[0] === event.target ) {
						wrapper.find( '.'+slug+'-field' ).focus();
					}
				} );			
	}
		// Togglers
		isotype_toggle('follow');
		isotype_toggle('search');
		isotype_toggle('languages');
		isotype_toggle('login');
		isotype_toggle('menu');
		$(window).resize(function(){
			if ( _window.width() >= menu_breakpoint ) {
				$('#primary-navigation li:has(ul)').doubleTapToGo();
			}
		});
		$('nav.double-tap li:has(ul)').doubleTapToGo();

		// Initialize Featured Content slider.
		if ( body.is( '.slider' ) ) {
			$( '.featured-content' ).featuredslider( {
				selector: '.featured-content-inner > article',
				controlsContainer: '.featured-content'
			} );
		}
	} );
} )( jQuery );