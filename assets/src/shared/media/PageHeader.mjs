import { createElement, Fragment, memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	FlexItemWP,
	FlexWP,
	HeadingWP,
	SpacerWP,
	TextWP,
} from '../controls/layout/index.js';
import { AddButton } from './AddButton.mjs';

const noop = () => {};

/**
 * List-screen header: heading and description on the left, children and an optional add button
 * on the right.
 *
 * @param {Object}                    props
 * @param {string}                    [props.title]               Heading, "All Courses" by default.
 * @param {string}                    [props.description]         Muted text under the heading.
 * @param {boolean}                   [props.showAddButton=false] Show the add button.
 * @param {Object}                    [props.addButtonConfig]     Props for the add button ({ label, onClick }).
 * @param {import('react').ReactNode} [props.children=null]       Extra controls before the add button.
 * @param {number}                    [props.paddingY=6.5]        Vertical padding.
 * @return {import('react').ReactElement} Header.
 */
function PageHeaderBase( {
	title = __( 'All Courses', 'ohmylms' ),
	description,
	showAddButton = false,
	addButtonConfig = {
		label: __( 'Add Course', 'ohmylms' ),
		onClick: noop,
	},
	children = null,
	paddingY = 6.5,
} ) {
	return createElement(
		Fragment,
		null,
		createElement(
			SpacerWP,
			{ paddingY, marginBottom: 0 },
			createElement(
				FlexWP,
				{ align: 'center', justify: 'space-between' },
				createElement(
					FlexItemWP,
					null,
					createElement(
						HeadingWP,
						{ level: '1', color: '#000D25', size: '20px' },
						title
					),
					description &&
						createElement(
							Fragment,
							null,
							createElement( SpacerWP, { marginBottom: 1 } ),
							createElement(
								TextWP,
								{ size: '14px' },
								description
							)
						)
				),
				createElement(
					FlexItemWP,
					null,
					createElement(
						FlexWP,
						{ align: 'center', justify: 'end', gap: '2' },
						children,
						showAddButton &&
							createElement( AddButton, addButtonConfig )
					)
				)
			)
		)
	);
}

export const PageHeader = memo( PageHeaderBase );
