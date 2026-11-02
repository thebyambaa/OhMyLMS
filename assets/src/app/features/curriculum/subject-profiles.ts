import { __ } from '@wordpress/i18n';
import { get, post } from '../../lib/api';
import { invalid, record, text } from '../../lib/authoring-validation';

export interface Profile {
	key: string;
	label: string;
	language: string;
	domains: { id: string; label: string }[];
	levels: string[];
	ai: { role: string; context: string; level_hints: string };
	builtin: boolean;
	customized: boolean;
}
export interface ProfilesPayload {
	profiles: Profile[];
	default: string;
	subjects: { subject: string; skills: number; profile: string }[];
	revision: string;
}
export interface ProfileForm {
	key: string;
	label: string;
	language: string;
	domains: { id: string; label: string }[];
	levels: string;
	role: string;
	context: string;
	level_hints: string;
}
const BASE = '/ohmylms/v1/syllabus-comparison/subject-profiles';
function bool( value: unknown ) {
	return typeof value === 'boolean' ? value : invalid();
}
function strings( value: unknown ) {
	return Array.isArray( value ) ? value.map( text ) : invalid();
}
export function parseProfiles( value: unknown ): ProfilesPayload {
	const row = record( value );
	const revision = text( row.revision );
	if ( revision.length !== 16 || ! Array.isArray( row.profiles ) ) {
		return invalid();
	}
	return {
		revision,
		default: text( row.default ),
		profiles: row.profiles.map( ( item ) => {
			const profile = record( item );
			const ai = record( profile.ai );
			return {
				key: text( profile.key ),
				label: text( profile.label ),
				language: text( profile.language ),
				domains: Array.isArray( profile.domains )
					? profile.domains.map( ( entry ) => {
							const domain = record( entry );
							return {
								id: text( domain.id ),
								label: text( domain.label ),
							};
						} )
					: invalid(),
				levels: strings( profile.levels ),
				ai: {
					role: text( ai.role ),
					context: text( ai.context ),
					level_hints: text( ai.level_hints ),
				},
				builtin: bool( profile.builtin ),
				customized: bool( profile.customized ),
			};
		} ),
		subjects: Array.isArray( row.subjects )
			? row.subjects.map( ( item ) => {
					const subject = record( item );
					return {
						subject: text( subject.subject ),
						skills: Number.isSafeInteger( subject.skills )
							? Number( subject.skills )
							: invalid(),
						profile: text( subject.profile ),
					};
				} )
			: invalid(),
	};
}
export function formOf( profile: Profile | null ): ProfileForm {
	return {
		key: profile?.key ?? '',
		label: profile?.label ?? '',
		language: profile?.language ?? '',
		domains: ( profile?.domains ?? [] ).map( ( domain ) => ( {
			...domain,
		} ) ),
		levels: ( profile?.levels ?? [] ).join( '\n' ),
		role: profile?.ai.role ?? '',
		context: profile?.ai.context ?? '',
		level_hints: profile?.ai.level_hints ?? '',
	};
}
/**
 * A domain id from its name: lower-case Latin letters, digits and hyphens, starting with a letter.
 * @param label
 */
export function domainId( label: string ) {
	return label
		.normalize( 'NFKD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^[^a-z]+/, '' )
		.slice( 0, 40 )
		.replace( /-+$/, '' );
}
export function payloadOf( form: ProfileForm ) {
	const levels = form.levels
		.split( /\r?\n/ )
		.map( ( level ) => level.trim() )
		.filter( Boolean );
	if ( ! form.label.trim() ) {
		throw new Error( __( 'Enter a profile name.', 'ohmylms' ) );
	}
	if ( ! levels.length ) {
		throw new Error( __( 'Enter at least one level.', 'ohmylms' ) );
	}
	return {
		...( form.key ? { key: form.key } : {} ),
		label: form.label.trim(),
		language: form.language.trim(),
		domains: form.domains
			.filter( ( domain ) => domain.label.trim() || domain.id.trim() )
			.map( ( domain ) => ( {
				id: domain.id.trim() || domainId( domain.label ),
				label: domain.label.trim(),
			} ) ),
		levels,
		ai: {
			role: form.role.trim(),
			context: form.context.trim(),
			level_hints: form.level_hints.trim(),
		},
	};
}
export async function readProfiles( signal?: AbortSignal ) {
	return parseProfiles( await get< unknown >( BASE, { signal } ) );
}
export async function saveProfile(
	form: ProfileForm,
	revision: string,
	signal?: AbortSignal
) {
	return parseProfiles(
		await post< unknown >(
			`${ BASE }/save`,
			{ ...payloadOf( form ), expected_revision: revision },
			{ signal }
		)
	);
}
export async function profileCommand(
	action: 'remove' | 'default',
	key: string,
	revision: string,
	signal?: AbortSignal
) {
	return parseProfiles(
		await post< unknown >(
			`${ BASE }/${ action }`,
			{ key, expected_revision: revision },
			{ signal }
		)
	);
}
