// @ts-check
/**
 * Learner language switcher (`ohmylms/language`): pure helpers and the store definitions.
 *
 * The switcher is a GET form of `ohmylms_locale` buttons rendered by OhMyLMS\I18n\Switcher, so it
 * works without JavaScript. The store posts the choice to `POST ohmylms/v1/learner/locale` and
 * reloads the page (translations are rendered on the server); if the request fails it follows the
 * form's own GET URL, which the server applies the same way.
 *
 * `parseAcceptLanguage()` and `matchLocale()` follow the same rules as OhMyLMS\I18n\AcceptLanguage;
 * both are checked against tests/fixtures/accept-language.json.
 */

/** @typedef {(event: Event) => void} EventHandler */
/** @typedef {{tag: string, q: number}} LanguageRange */

export const QUERY_VAR = 'ohmylms_locale';
export const ROUTE = 'ohmylms/v1/learner/locale';

const MAX_LENGTH = 1024;
const MAX_RANGES = 32;
const TAG = /^[a-z]{1,8}(-[a-z0-9]{1,8})*$/;
const QUALITY = /^q\s*=\s*(0(\.\d{0,3})?|1(\.0{0,3})?)$/;

/**
 * Lower-case BCP 47 tag of a WordPress locale (`en_US` is `en-us`).
 *
 * @param {string} locale WordPress locale.
 * @return {string} Tag.
 */
export function localeTag( locale ) {
	return String( locale ).replace( /_/g, '-' ).toLowerCase();
}

/**
 * Language ranges of an `Accept-Language` header, best first. Tags are lower case (`_` read as
 * `-`); ranges with `q=0`, a malformed quality or a malformed tag are dropped; equal qualities keep
 * their order.
 *
 * @param {unknown} header Header value.
 * @return {LanguageRange[]} Ranges.
 */
export function parseAcceptLanguage( header ) {
	if ( 'string' !== typeof header || '' === header.trim() ) {
		return [];
	}
	const ranges = [];
	header
		.slice( 0, MAX_LENGTH )
		.split( ',' )
		.slice( 0, MAX_RANGES )
		.forEach( ( part, position ) => {
			const [ first, ...params ] = part.split( ';' );
			const tag = first.trim().toLowerCase().replace( /_/g, '-' );
			if ( '*' !== tag && ! TAG.test( tag ) ) {
				return;
			}
			let q = 1;
			for ( const raw of params ) {
				const param = raw.trim().toLowerCase();
				if ( param.startsWith( 'q' ) ) {
					const match = QUALITY.exec( param );
					q = match ? Number( match[ 1 ] ) : 0;
				}
			}
			if ( q > 0 ) {
				ranges.push( { tag, q, position } );
			}
		} );
	return ranges
		.sort( ( a, b ) => b.q - a.q || a.position - b.position )
		.map( ( { tag, q } ) => ( { tag, q } ) );
}

/**
 * The locale the best range asks for: by whole tag (`en-us` is `en_US`) or by language (`mn-MN` is
 * `mn`, `en-GB` is `en_US`); `*` takes the fallback. Ranges no locale serves are skipped.
 *
 * @param {LanguageRange[]} ranges   Output of parseAcceptLanguage().
 * @param {string[]}        locales  WordPress locales, in preference order.
 * @param {string}          fallback Returned when nothing matches.
 * @return {string} Locale.
 */
export function matchLocale( ranges, locales, fallback ) {
	for ( const { tag } of ranges ) {
		if ( '*' === tag ) {
			return fallback;
		}
		const exact = locales.find( ( locale ) => localeTag( locale ) === tag );
		if ( exact ) {
			return exact;
		}
		const language = tag.split( '-' )[ 0 ];
		const near = locales.find(
			( locale ) => localeTag( locale ).split( '-' )[ 0 ] === language
		);
		if ( near ) {
			return near;
		}
	}
	return fallback;
}

/**
 * Parse a header and match it.
 *
 * @param {unknown}  header   Header value.
 * @param {string[]} locales  WordPress locales.
 * @param {string}   fallback Returned when nothing matches.
 * @return {string} Locale.
 */
export function negotiateLocale( header, locales, fallback ) {
	return matchLocale( parseAcceptLanguage( header ), locales, fallback );
}

/**
 * The locale a switcher button submits, or '' for any other submitter.
 *
 * @param {unknown} submitter `SubmitEvent.submitter`.
 * @return {string} Locale.
 */
export function chosenLocale( submitter ) {
	const button = /** @type {{name?: unknown, value?: unknown}|null} */ (
		submitter
	);
	return button && QUERY_VAR === button.name && 'string' === typeof button.value
		? button.value
		: '';
}

/**
 * The GET URL the form would load: its action with the submitted fields as the query.
 *
 * @param {string}          action Absolute form action.
 * @param {URLSearchParams} params Submitted fields, the chosen button included.
 * @return {string} URL.
 */
export function switchUrl( action, params ) {
	const url = new URL( action );
	url.search = params.toString();
	url.hash = '';
	return url.toString();
}

/**
 * Store definitions for `ohmylms/language`. Form context: `{ current, busy }`.
 *
 * @param {Object}                                                                                api               Runtime, SDK and page navigation.
 * @param {() => Object}                                                                          api.getContext    `@wordpress/interactivity` getContext.
 * @param {(callback: EventHandler) => EventHandler}                                              api.withSyncEvent `@wordpress/interactivity` withSyncEvent.
 * @param {(options: Object) => Promise<{ok: boolean, data: any, error: any}>}                   api.request       SDK `request()`.
 * @param {(target: EventTarget, name: string, detail?: Object, cancelable?: boolean) => boolean} api.emit          SDK `emit()`.
 * @param {(form: HTMLFormElement, options: Object) => any}                                       api.serializeForm SDK `serializeForm()`.
 * @param {() => void}                                                                            api.reload        Reload the page.
 * @param {(url: string) => void}                                                                 api.navigate      Load a URL.
 * @return {{actions: Object}} Definitions.
 */
export function createLanguage( {
	getContext,
	withSyncEvent,
	request,
	emit,
	serializeForm,
	reload,
	navigate,
} ) {
	return {
		actions: {
			choose: withSyncEvent( ( event ) => {
				const submit = /** @type {SubmitEvent} */ ( event );
				const form = /** @type {HTMLFormElement} */ ( submit.target );
				const locale = chosenLocale( submit.submitter );
				if ( ! locale ) {
					return;
				}
				submit.preventDefault();
				const context = getContext();
				const previous = context.current;
				if ( context.busy || locale === previous ) {
					return;
				}
				if (
					! emit(
						form,
						'language-before-change',
						{ locale, previous },
						true
					)
				) {
					return;
				}
				context.busy = true;
				const fallback = switchUrl(
					form.action,
					serializeForm( form, {
						submitter: submit.submitter,
						as: 'params',
					} )
				);
				return request( {
					path: ROUTE,
					method: 'POST',
					data: { locale },
				} ).then( ( result ) => {
					if ( result.ok ) {
						emit( form, 'language-changed', {
							locale,
							previous,
							source: result.data?.source ?? '',
						} );
						reload();
						return;
					}
					emit( form, 'language-error', {
						locale,
						code: result.error?.code ?? '',
						message: result.error?.message ?? '',
					} );
					navigate( fallback );
				} );
			} ),
			reset() {
				getContext().busy = false;
			},
		},
	};
}
