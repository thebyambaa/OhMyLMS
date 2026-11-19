import { Button, SearchControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

interface ToolbarProps {
	searchLabel: string;
	search: string;
	onSearchChange: ( value: string ) => void;
	onSearch: () => void;
	status: string;
	statusOptions: { value: string; label: string }[];
	onStatusChange: ( value: string ) => void;
	count: string;
	children?: ReactNode;
}

/**
 * Search, filter and primary actions above the certificate and automation cards.
 *
 * @param props Toolbar controls and actions.
 */
export function EngagementToolbar( props: ToolbarProps ) {
	return (
		<div className="oml-engagement-toolbar">
			<form
				className="oml-engagement-search"
				role="search"
				onSubmit={ ( event ) => {
					event.preventDefault();
					props.onSearch();
				} }
			>
				<SearchControl
					label={ props.searchLabel }
					placeholder={ props.searchLabel }
					value={ props.search }
					onChange={ props.onSearchChange }
					__nextHasNoMarginBottom
				/>
				<Button type="submit" variant="secondary">
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			<SelectControl
				label={ __( 'Status', 'ohmylms' ) }
				hideLabelFromVision
				value={ props.status }
				options={ props.statusOptions }
				onChange={ props.onStatusChange }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<span className="oml-engagement-count" aria-live="polite">
				{ props.count }
			</span>
			{ props.children }
		</div>
	);
}
