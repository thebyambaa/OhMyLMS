import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { groupScopes } from './model';
import type { DashboardScope } from './model';

/**
 * Audience of every widget: all schools (site administrators), a school, or a class. Hidden when the
 * user has a single scope.
 *
 * @param props          Component props.
 * @param props.scopes   Scopes the user may pick.
 * @param props.value    Current scope id.
 * @param props.onChange Called with the picked scope id.
 */
export function ScopePicker( {
	scopes,
	value,
	onChange,
}: {
	scopes: readonly DashboardScope[];
	value: string;
	onChange: ( scope: string ) => void;
} ) {
	if ( scopes.length < 2 ) {
		return null;
	}
	const { top, groups } = groupScopes( scopes );
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			className="ohmylms-dashboard-scope"
			label={ __( 'Show', 'ohmylms' ) }
			labelPosition="side"
			value={ value }
			onChange={ ( next: string ) => onChange( next ) }
		>
			{ top.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
			{ groups.map( ( group ) => (
				<optgroup key={ group.label } label={ group.label }>
					{ group.options.map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</optgroup>
			) ) }
		</SelectControl>
	);
}
