import { __ } from '@wordpress/i18n';
import { asText, TextField } from './fields';
import { FormFrame } from './FormFrame';
import type { SettingsGroupState } from './hooks';
import type { FieldName } from './types';

const BASES: ReadonlyArray< {
	name: FieldName< 'permalink' >;
	label: string;
} > = [
	{ name: 'course_base', label: __( 'Course base', 'ohmylms' ) },
	{ name: 'lesson_base', label: __( 'Lesson base', 'ohmylms' ) },
	{ name: 'quiz_base', label: __( 'Quiz base', 'ohmylms' ) },
	{ name: 'category_base', label: __( 'Course category base', 'ohmylms' ) },
	{ name: 'tag_base', label: __( 'Course tag base', 'ohmylms' ) },
	{ name: 'membership_base', label: __( 'Membership base', 'ohmylms' ) },
];

/**
 * Permalink tab: URL bases. Changed bases take effect after WordPress rebuilds its rewrite rules,
 * which happens once on the next page load (never during the save itself).
 *
 * @param props       Component props.
 * @param props.state State of the `permalink` group.
 */
export function PermalinkForm( {
	state,
}: {
	state: SettingsGroupState< 'permalink' >;
} ) {
	const draft = state.draft;
	return (
		<FormFrame
			title={ __( 'Permalinks', 'ohmylms' ) }
			description={ __(
				'URL bases of courses and their content. Use lower-case letters, numbers, hyphens and underscores; separate parts with a slash.',
				'ohmylms'
			) }
			state={ state }
			savedNote={ __(
				'URLs refresh the next time a page loads on your site.',
				'ohmylms'
			) }
		>
			{ draft &&
				BASES.map( ( base ) => (
					<TextField
						key={ base.name }
						label={ base.label }
						value={ asText( draft[ base.name ] ) }
						error={ state.errors[ base.name ] }
						maxLength={ 60 }
						onChange={ ( value ) =>
							state.setField( base.name, value )
						}
					/>
				) ) }
		</FormFrame>
	);
}
