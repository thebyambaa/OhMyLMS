/**
 * Bootstrap data handed over by PHP (`window.ohmylmsApp`, printed by includes/Assets/AdminAppAssets.php).
 *
 * The raw value crosses a trust boundary, so it is validated here once and the rest of the app only sees
 * the typed {@link AppBootstrap}.
 */

/** Validated bootstrap data. */
export interface AppBootstrap {
	/** REST root URL, for example `https://example.test/wp-json/`. */
	readonly restRoot: string;
	/** `wp_rest` nonce for the current user. */
	readonly restNonce: string;
	/** Locale such as `en_US`. */
	readonly locale: string;
	/** `admin_url()` of the site. */
	readonly adminUrl: string;
	/** Plugin version. */
	readonly version: string;
	/** Current user. */
	readonly user: {
		readonly id: number;
		/** Capabilities the server chose to expose, by name. Only hides UI; the server enforces access. */
		readonly capabilities: Readonly< Record< string, boolean > >;
	};
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return 'object' === typeof value && null !== value;
}

function readCapabilities( value: unknown ): Record< string, boolean > {
	const capabilities: Record< string, boolean > = {};
	if ( isRecord( value ) ) {
		for ( const [ name, granted ] of Object.entries( value ) ) {
			capabilities[ name ] = true === granted;
		}
	}
	return capabilities;
}

/**
 * Validate the raw bootstrap value.
 *
 * @param raw Value of `window.ohmylmsApp`.
 * @return Typed data, or `null` when required fields are missing.
 */
export function parseBootstrap( raw: unknown ): AppBootstrap | null {
	if ( ! isRecord( raw ) || ! isRecord( raw.user ) ) {
		return null;
	}
	const { restRoot, restNonce, locale, adminUrl, version, user } = raw;
	if (
		'string' !== typeof restRoot ||
		'string' !== typeof restNonce ||
		'number' !== typeof user.id
	) {
		return null;
	}
	return {
		restRoot,
		restNonce,
		locale: 'string' === typeof locale ? locale : 'en_US',
		adminUrl: 'string' === typeof adminUrl ? adminUrl : '',
		version: 'string' === typeof version ? version : '',
		user: {
			id: user.id,
			capabilities: readCapabilities( user.capabilities ),
		},
	};
}

/**
 * Whether the user holds a capability the server exposed.
 *
 * @param capabilities Capability map from the bootstrap data.
 * @param capability   Required capability; none means everyone.
 * @return True when access is not restricted or the capability is granted.
 */
export function hasCapability(
	capabilities: Readonly< Record< string, boolean > >,
	capability?: string
): boolean {
	return undefined === capability || true === capabilities[ capability ];
}
