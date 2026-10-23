/**
 * Pure helpers for the site-search combobox. Kept free of DOM access so
 * they can be exercised under `node --test`.
 */
( function ( root, factory ) {
	'use strict';
	var api = factory();
	if ( typeof module === 'object' && module.exports ) {
		module.exports = api;
	}
	root.cdmComboboxLogic = api;
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	/**
	 * Index of the option that should become active after a key press,
	 * following the APG editable-combobox pattern (arrows wrap).
	 */
	function nextIndex( key, current, count ) {
		if ( count <= 0 ) {
			return -1;
		}
		switch ( key ) {
			case 'ArrowDown':
				return current < 0 || current >= count - 1 ? 0 : current + 1;
			case 'ArrowUp':
				return current <= 0 ? count - 1 : current - 1;
			case 'Home':
				return 0;
			case 'End':
				return count - 1;
			default:
				return current;
		}
	}

	function buildSearchUrl( base, term ) {
		var joiner = base.indexOf( '?' ) === -1 ? '?' : '&';
		return base + joiner + 'search=' + encodeURIComponent( term );
	}

	function optionLabel( site ) {
		var tail = site.address + ' (site ' + site.id + ')';
		return site.name ? site.name + ' — ' + tail : tail;
	}

	function describeResults( count, term ) {
		if ( count === 0 ) {
			return 'No sites match “' + term + '”.';
		}
		return count + ( count === 1 ? ' site' : ' sites' ) +
			' found. Use up and down arrow keys to review, Enter to select.';
	}

	return {
		nextIndex: nextIndex,
		buildSearchUrl: buildSearchUrl,
		optionLabel: optionLabel,
		describeResults: describeResults
	};
} ) );
