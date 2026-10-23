'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const logic = require( '../../assets/combobox-logic.js' );

test( 'ArrowDown moves to the first option when nothing is active', () => {
	assert.equal( logic.nextIndex( 'ArrowDown', -1, 5 ), 0 );
} );

test( 'ArrowDown advances and wraps to the start', () => {
	assert.equal( logic.nextIndex( 'ArrowDown', 1, 5 ), 2 );
	assert.equal( logic.nextIndex( 'ArrowDown', 4, 5 ), 0 );
} );

test( 'ArrowUp moves to the last option when nothing is active, then backwards with wrap', () => {
	assert.equal( logic.nextIndex( 'ArrowUp', -1, 5 ), 4 );
	assert.equal( logic.nextIndex( 'ArrowUp', 2, 5 ), 1 );
	assert.equal( logic.nextIndex( 'ArrowUp', 0, 5 ), 4 );
} );

test( 'Home and End jump to the ends', () => {
	assert.equal( logic.nextIndex( 'Home', 3, 5 ), 0 );
	assert.equal( logic.nextIndex( 'End', 3, 5 ), 4 );
} );

test( 'navigation with no options leaves nothing active', () => {
	assert.equal( logic.nextIndex( 'ArrowDown', -1, 0 ), -1 );
	assert.equal( logic.nextIndex( 'End', -1, 0 ), -1 );
} );

test( 'other keys do not change the active option', () => {
	assert.equal( logic.nextIndex( 'a', 2, 5 ), 2 );
	assert.equal( logic.nextIndex( 'Tab', 2, 5 ), 2 );
} );

test( 'buildSearchUrl appends an encoded search term to a pretty REST URL', () => {
	assert.equal(
		logic.buildSearchUrl( 'https://hcommons.org/wp-json/commons-domain-mapping/v1/sites', 'poetry rcah' ),
		'https://hcommons.org/wp-json/commons-domain-mapping/v1/sites?search=poetry%20rcah'
	);
} );

test( 'buildSearchUrl uses & when the REST URL already has a query string', () => {
	assert.equal(
		logic.buildSearchUrl( 'https://hcommons.org/?rest_route=/commons-domain-mapping/v1/sites', 'x' ),
		'https://hcommons.org/?rest_route=/commons-domain-mapping/v1/sites&search=x'
	);
} );

test( 'optionLabel shows name, address and id, and copes with an unnamed site', () => {
	assert.equal(
		logic.optionLabel( { id: 42, name: 'Poetry at RCAH', address: 'poetry.hcommons.org/' } ),
		'Poetry at RCAH — poetry.hcommons.org/ (site 42)'
	);
	assert.equal(
		logic.optionLabel( { id: 7, name: '', address: 'x.hcommons.org/' } ),
		'x.hcommons.org/ (site 7)'
	);
} );

test( 'describeResults produces a screen-reader announcement', () => {
	assert.equal( logic.describeResults( 0, 'zzz' ), 'No sites match “zzz”.' );
	assert.equal( logic.describeResults( 1, 'poetry' ), '1 site found. Use up and down arrow keys to review, Enter to select.' );
	assert.equal( logic.describeResults( 10, 'h' ), '10 sites found. Use up and down arrow keys to review, Enter to select.' );
} );
