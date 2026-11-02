import { __, sprintf } from '@wordpress/i18n';
import { get, post } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	isRecord,
} from './model';
import type { FieldErrors } from './types';

const PATH = '/ohmylms/v1/ai';
const MODEL = /^[A-Za-z0-9._:/-]{1,100}$/;

export type KeySource = 'constant' | 'environment' | 'stored' | 'none';
export type PracticeStyle = 'standard' | 'lesson';

export interface AiSettings {
	enabled: boolean;
	exam_converter_enabled: boolean;
	provider: string;
	model: string;
	hints: boolean;
	explanations: boolean;
	guests: boolean;
	daily_limit: number;
	max_tokens: number;
	timeout: number;
}

/** `GET /ai`. The API key is never included: only whether one exists, its source and a hint. */
export interface AiSnapshot {
	settings: AiSettings;
	practiceStyle: PracticeStyle;
	hasKey: boolean;
	keyHint: string;
	keyFrom: KeySource;
	providers: Record< string, string >;
	stats: {
		requests: number;
		failures: number;
		cached: number;
		inputTokens: number;
		outputTokens: number;
		lastError: string;
	};
}

export interface AiDraft extends AiSettings {
	practiceStyle: PracticeStyle;
	key: string;
	clearKey: boolean;
}

/** Bounds the server clamps to (`AI\Settings::update()`). */
export const AI_LIMITS = {
	daily_limit: { min: 1, max: 500 },
	max_tokens: { min: 100, max: 1200 },
	timeout: { min: 5, max: 60 },
} as const;

const SOURCES: readonly KeySource[] = [
	'constant',
	'environment',
	'stored',
	'none',
];

function stat( source: Record< string, unknown >, name: string ): number {
	return 'number' === typeof source[ name ] ? source[ name ] : 0;
}

export function parseAi( raw: unknown ): AiSnapshot {
	const body = asRecord( raw );
	const settings = asRecord( body.settings );
	const stats = isRecord( body.stats ) ? body.stats : {};
	const providers: Record< string, string > = {};
	for ( const [ id, label ] of Object.entries(
		asRecord( settings.providers )
	) ) {
		providers[ id ] = asString( label );
	}
	const source = asString( settings.key_from ) as KeySource;
	return {
		settings: {
			enabled: asBoolean( settings.enabled ),
			exam_converter_enabled: asBoolean(
				settings.exam_converter_enabled
			),
			provider: asString( settings.provider ),
			model: asString( settings.model ),
			hints: asBoolean( settings.hints ),
			explanations: asBoolean( settings.explanations ),
			guests: asBoolean( settings.guests ),
			daily_limit: asNumber( settings.daily_limit ),
			max_tokens: asNumber( settings.max_tokens ),
			timeout: asNumber( settings.timeout ),
		},
		practiceStyle: 'lesson' === body.practice_style ? 'lesson' : 'standard',
		hasKey: asBoolean( settings.has_key ),
		keyHint: asString( settings.key_hint ),
		keyFrom: SOURCES.includes( source ) ? source : 'none',
		providers,
		stats: {
			requests: stat( stats, 'requests' ),
			failures: stat( stats, 'failures' ),
			cached: stat( stats, 'cached' ),
			inputTokens: stat( stats, 'input_tokens' ),
			outputTokens: stat( stats, 'output_tokens' ),
			lastError:
				'string' === typeof stats.last_error ? stats.last_error : '',
		},
	};
}

export async function getAi( signal?: AbortSignal ): Promise< AiSnapshot > {
	return parseAi( await get< unknown >( PATH, { signal } ) );
}

/**
 * Full body for `POST /ai`. The route replaces every policy, so all are sent; the key only when
 * typed (an empty key keeps the stored one) and `clear_key` only when removing it.
 *
 * @param draft Draft.
 * @return Request body.
 */
export function buildAiBody( draft: AiDraft ): Record< string, unknown > {
	const body: Record< string, unknown > = {
		enabled: draft.enabled,
		exam_converter_enabled: draft.exam_converter_enabled,
		provider: draft.provider,
		model: draft.model.trim(),
		hints: draft.hints,
		explanations: draft.explanations,
		guests: draft.guests,
		daily_limit: draft.daily_limit,
		max_tokens: draft.max_tokens,
		timeout: draft.timeout,
		practice_style: draft.practiceStyle,
	};
	if ( draft.clearKey ) {
		body.clear_key = true;
	} else if ( '' !== draft.key.trim() ) {
		body.key = draft.key.trim();
	}
	return body;
}

export async function saveAi(
	draft: AiDraft,
	signal?: AbortSignal
): Promise< AiSnapshot > {
	return parseAi(
		await post< unknown >( PATH, buildAiBody( draft ), { signal } )
	);
}

/**
 * Model identifiers from the saved provider, using the saved key.
 *
 * @param signal Cancels the request.
 * @return Provider, models and whether the list was cut short.
 */
export async function listAiModels( signal?: AbortSignal ): Promise< {
	provider: string;
	models: string[];
	truncated: boolean;
} > {
	const body = asRecord(
		await post< unknown >( `${ PATH }/models`, undefined, { signal } )
	);
	return {
		provider: asString( body.provider ),
		models: asArray( body.models ).map( asString ),
		truncated: asBoolean( body.truncated ),
	};
}

export async function testAi(
	signal?: AbortSignal
): Promise< { ok: boolean; message: string } > {
	const body = asRecord(
		await post< unknown >( `${ PATH }/test`, undefined, { signal } )
	);
	return { ok: asBoolean( body.ok ), message: asString( body.message ) };
}

export function aiDraft( snapshot: AiSnapshot ): AiDraft {
	return {
		...snapshot.settings,
		practiceStyle: snapshot.practiceStyle,
		key: '',
		clearKey: false,
	};
}

export function aiDirty( snapshot: AiSnapshot, draft: AiDraft ): boolean {
	const saved = snapshot.settings;
	return (
		'' !== draft.key.trim() ||
		draft.clearKey ||
		snapshot.practiceStyle !== draft.practiceStyle ||
		( Object.keys( saved ) as Array< keyof AiSettings > ).some(
			( name ) => saved[ name ] !== draft[ name ]
		)
	);
}

export function validateAi(
	draft: AiDraft,
	snapshot: AiSnapshot
): FieldErrors {
	const errors: FieldErrors = {};
	if ( ! ( draft.provider in snapshot.providers ) ) {
		errors.provider = __( 'Choose a provider.', 'ohmylms' );
	}
	const model = draft.model.trim();
	if ( '' !== model && ! MODEL.test( model.replace( /^models\//, '' ) ) ) {
		errors.model = __(
			'Use the provider’s model identifier (letters, digits and . _ : / -).',
			'ohmylms'
		);
	}
	const usable =
		! draft.clearKey && ( '' !== draft.key.trim() || snapshot.hasKey );
	if ( ( draft.enabled || draft.exam_converter_enabled ) && '' === model ) {
		errors.model = __(
			'Choose a model before turning a tool on.',
			'ohmylms'
		);
	}
	if ( ( draft.enabled || draft.exam_converter_enabled ) && ! usable ) {
		errors.key = __(
			'Save an API key before turning a tool on.',
			'ohmylms'
		);
	}
	if ( /\s/.test( draft.key.trim() ) || draft.key.length > 500 ) {
		errors.key = __( 'Paste the key exactly, without spaces.', 'ohmylms' );
	}
	for ( const name of Object.keys( AI_LIMITS ) as Array<
		keyof typeof AI_LIMITS
	> ) {
		const { min, max } = AI_LIMITS[ name ];
		const value = draft[ name ];
		if ( ! Number.isInteger( value ) || value < min || value > max ) {
			errors[ name ] = sprintf(
				/* translators: 1: minimum, 2: maximum. */
				__( 'Enter a whole number from %1$d to %2$d.', 'ohmylms' ),
				min,
				max
			);
		}
	}
	return errors;
}
