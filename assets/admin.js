/**
 * Commons Domain Mapping — progressive enhancement for the admin screen.
 *
 * 1. Site picker: WAI-ARIA editable combobox with list autocomplete.
 * 2. Confirmation before destructive row actions.
 * 3. Focus the outcome notice so assistive tech announces it.
 */
( function ( window, document ) {
	'use strict';

	var settings = window.cdmSettings || {};
	var logic = window.cdmComboboxLogic;
	var i18n = settings.i18n || {};

	function format( template, value ) {
		return String( template || '%s' ).replace( '%s', value );
	}

	/**
	 * Focus the outcome notice so assistive tech announces it. The notice
	 * carries the "inline" class so wp-admin's common.js does not relocate
	 * it (a DOM move would drop focus); jQuery's ready queue is used when
	 * present so this still runs after any admin scripts that touch notices.
	 */
	function focusNotice() {
		var notice = document.getElementById( 'cdm-notice' );
		if ( ! notice ) {
			return;
		}
		var focus = function () {
			notice.focus();
		};
		if ( window.jQuery ) {
			window.jQuery( focus );
		} else {
			window.setTimeout( focus, 0 );
		}
	}

	function confirmActions() {
		var forms = document.querySelectorAll( 'form[data-cdm-confirm]' );
		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				var kind = form.getAttribute( 'data-cdm-confirm' );
				var host = form.getAttribute( 'data-cdm-host' ) || '';
				var template = kind === 'remove' ? i18n.confirmRemove : i18n.confirmPrimary;
				if ( ! window.confirm( format( template, host ) ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	function Combobox( root ) {
		this.root = root;
		this.input = root.querySelector( '[role="combobox"]' );
		this.listbox = root.querySelector( '[role="listbox"]' );
		this.status = root.querySelector( '[aria-live]' );
		this.idField = root.querySelector( '.cdm-id-field' );
		this.idInput = root.querySelector( 'input[name="blog_id"]' );
		this.selected = root.querySelector( '.cdm-selected' );
		this.selectedName = root.querySelector( '#cdm-selected-name' );
		this.selectedMeta = root.querySelector( '#cdm-selected-meta' );
		this.changeButton = root.querySelector( '#cdm-selected-change' );
		this.toggleIdButton = root.querySelector( '#cdm-toggle-id' );
		this.results = [];
		this.activeIndex = -1;
		this.timer = null;
		this.controller = null;
		this.bind();
	}

	Combobox.prototype.bind = function () {
		var self = this;
		this.input.addEventListener( 'input', function () {
			self.clearSelection( false );
			self.scheduleSearch();
		} );
		this.input.addEventListener( 'keydown', function ( event ) {
			self.onKeydown( event );
		} );
		this.input.addEventListener( 'blur', function () {
			// Let a click on an option land before closing.
			window.setTimeout( function () {
				if ( document.activeElement !== self.input ) {
					self.close();
				}
			}, 120 );
		} );
		this.input.addEventListener( 'focus', function () {
			if ( self.results.length && ! self.idInput.value ) {
				self.open();
			}
		} );
		this.listbox.addEventListener( 'mousedown', function ( event ) {
			event.preventDefault(); // keep focus in the input
		} );
		this.listbox.addEventListener( 'click', function ( event ) {
			var option = event.target.closest( '[role="option"]' );
			if ( option && option.hasAttribute( 'data-index' ) ) {
				self.select( parseInt( option.getAttribute( 'data-index' ), 10 ) );
			}
		} );
		this.changeButton.addEventListener( 'click', function () {
			self.clearSelection( true );
			self.input.focus();
			self.input.select();
		} );
		this.toggleIdButton.addEventListener( 'click', function () {
			self.showIdField();
		} );
	};

	Combobox.prototype.showIdField = function () {
		this.idField.classList.add( 'is-visible' );
		this.toggleIdButton.parentNode.hidden = true;
		this.idInput.focus();
	};

	Combobox.prototype.announce = function ( text ) {
		// Clear then set so repeated identical messages are re-announced.
		this.status.textContent = '';
		var self = this;
		window.setTimeout( function () {
			self.status.textContent = text;
		}, 50 );
	};

	Combobox.prototype.scheduleSearch = function () {
		var self = this;
		window.clearTimeout( this.timer );
		this.timer = window.setTimeout( function () {
			self.search( self.input.value.trim() );
		}, 200 );
	};

	Combobox.prototype.search = function ( term ) {
		var self = this;
		if ( this.controller ) {
			this.controller.abort();
		}
		this.controller = new AbortController();
		this.input.setAttribute( 'aria-busy', 'true' );

		window.fetch( logic.buildSearchUrl( settings.restUrl, term ), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': settings.nonce, Accept: 'application/json' },
			signal: this.controller.signal
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} ).then( function ( sites ) {
			self.input.removeAttribute( 'aria-busy' );
			self.render( Array.isArray( sites ) ? sites : [], term );
		} ).catch( function ( error ) {
			if ( error && error.name === 'AbortError' ) {
				return;
			}
			self.input.removeAttribute( 'aria-busy' );
			self.results = [];
			self.close();
			self.announce( i18n.searchFailed );
			self.showIdField();
		} );
	};

	Combobox.prototype.render = function ( sites, term ) {
		var self = this;
		this.results = sites;
		this.activeIndex = -1;
		this.listbox.textContent = '';

		if ( ! sites.length ) {
			var empty = document.createElement( 'li' );
			empty.className = 'cdm-option cdm-option--empty';
			empty.setAttribute( 'role', 'option' );
			empty.setAttribute( 'aria-selected', 'false' );
			empty.setAttribute( 'aria-disabled', 'true' );
			empty.textContent = logic.describeResults( 0, term );
			this.listbox.appendChild( empty );
		}

		sites.forEach( function ( site, index ) {
			var li = document.createElement( 'li' );
			li.className = 'cdm-option';
			li.id = 'cdm-option-' + index;
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'aria-selected', 'false' );
			li.setAttribute( 'data-index', String( index ) );

			var name = document.createElement( 'span' );
			name.className = 'cdm-option__name';
			name.textContent = site.name || site.address;

			var meta = document.createElement( 'span' );
			meta.className = 'cdm-option__meta';
			meta.textContent = site.address + ' · site ' + site.id;

			li.appendChild( name );
			li.appendChild( meta );
			self.listbox.appendChild( li );
		} );

		this.open();
		this.announce( logic.describeResults( sites.length, term ) );
	};

	Combobox.prototype.open = function () {
		this.listbox.hidden = false;
		this.input.setAttribute( 'aria-expanded', 'true' );
	};

	Combobox.prototype.close = function () {
		this.listbox.hidden = true;
		this.input.setAttribute( 'aria-expanded', 'false' );
		this.setActive( -1 );
	};

	Combobox.prototype.isOpen = function () {
		return ! this.listbox.hidden;
	};

	Combobox.prototype.setActive = function ( index ) {
		var options = this.listbox.querySelectorAll( '[data-index]' );
		Array.prototype.forEach.call( options, function ( option ) {
			option.setAttribute( 'aria-selected', 'false' );
		} );
		this.activeIndex = index;
		if ( index < 0 || ! options[ index ] ) {
			this.input.removeAttribute( 'aria-activedescendant' );
			return;
		}
		options[ index ].setAttribute( 'aria-selected', 'true' );
		this.input.setAttribute( 'aria-activedescendant', options[ index ].id );
		if ( typeof options[ index ].scrollIntoView === 'function' ) {
			options[ index ].scrollIntoView( { block: 'nearest' } );
		}
	};

	Combobox.prototype.onKeydown = function ( event ) {
		switch ( event.key ) {
			case 'ArrowDown':
			case 'ArrowUp':
			case 'Home':
			case 'End':
				if ( ! this.isOpen() ) {
					if ( this.results.length ) {
						this.open();
					} else {
						this.search( this.input.value.trim() );
						return;
					}
				}
				if ( ( event.key === 'Home' || event.key === 'End' ) && ! event.altKey ) {
					// Let Home/End edit the text unless the list is being navigated.
					if ( this.activeIndex < 0 ) {
						return;
					}
				}
				event.preventDefault();
				this.setActive( logic.nextIndex( event.key, this.activeIndex, this.results.length ) );
				break;
			case 'Enter':
				if ( this.isOpen() && this.activeIndex >= 0 ) {
					event.preventDefault();
					this.select( this.activeIndex );
				} else if ( this.isOpen() && this.results.length === 1 ) {
					event.preventDefault();
					this.select( 0 );
				} else if ( ! this.idInput.value ) {
					// Don't submit the form with no site chosen.
					event.preventDefault();
					this.search( this.input.value.trim() );
				}
				break;
			case 'Escape':
				if ( this.isOpen() ) {
					event.preventDefault();
					this.close();
				}
				break;
			case 'Tab':
				this.close();
				break;
			default:
				break;
		}
	};

	Combobox.prototype.select = function ( index ) {
		var site = this.results[ index ];
		if ( ! site ) {
			return;
		}
		this.idInput.value = String( site.id );
		this.input.value = logic.optionLabel( site );
		this.selectedName.textContent = site.name || site.address;
		this.selectedMeta.textContent = site.address + ' · site ' + site.id;
		this.selected.hidden = false;
		this.close();
		this.announce( format( i18n.selected, logic.optionLabel( site ) ) );
	};

	Combobox.prototype.clearSelection = function ( clearText ) {
		var had = this.idInput.value !== '';
		this.idInput.value = '';
		this.selected.hidden = true;
		if ( clearText ) {
			this.input.value = '';
		}
		if ( had ) {
			this.announce( i18n.cleared );
		}
	};

	function init() {
		document.documentElement.classList.add( 'cdm-js' );
		focusNotice();
		confirmActions();
		var root = document.querySelector( '[data-cdm-combobox]' );
		if ( root && logic && settings.restUrl && window.fetch && window.AbortController ) {
			new Combobox( root );
		} else if ( root ) {
			document.documentElement.classList.remove( 'cdm-js' );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
