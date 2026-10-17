/**
 * Walk parents to aio-login-app.current_is_pro (router children sit under wrapper divs).
 *
 * @param {import('vue').ComponentPublicInstance} vm
 * @returns {boolean}
 */
export default function resolveParentCurrentIsPro(vm) {
	let p = vm.$parent;
	while (p) {
		if ('current_is_pro' in p) {
			return p.current_is_pro === true || p.current_is_pro === 'true';
		}
		p = p.$parent;
	}
	return false;
}
