/**
 * Admin UI i18n helper (Free).
 * Keys are English source strings (WordPress gettext style).
 *
 * @param {string} text English source / lookup key.
 * @param {string} [fallback] Optional fallback if map missing (defaults to text).
 * @return {string}
 */
export function t( text, fallback ) {
	const map = ( typeof window !== 'undefined' && window.aio_login__app_object && window.aio_login__app_object.i18n ) || {};
	if ( Object.prototype.hasOwnProperty.call( map, text ) && map[ text ] ) {
		return map[ text ];
	}
	return ( typeof fallback === 'string' && fallback !== '' ) ? fallback : text;
}

export default t;
