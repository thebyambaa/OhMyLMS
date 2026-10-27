import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { RowMenu } from './WorkspaceParts';
import {
	contentKey,
	groupKey,
	skillKey,
	stepAmongSiblings,
} from './workspace.mjs';

/**
 * Actions for a saved spreadsheet row, operating on its existing placement.
 *
 * @param {Object}                              props          Component properties.
 * @param {Object}                              props.row      Saved row and placement IDs.
 * @param {Object}                              props.tree     Indexed syllabus tree.
 * @param {Object}                              props.actions  Existing syllabus actions.
 * @param {boolean}                             props.disabled Whether edits must be saved first.
 * @param {(key: string) => void}               props.onOpen   Open details.
 * @param {(row: Object, kind: string) => void} props.onAdd    Add inside this branch.
 * @return {Object|null} Row action menu.
 */
export function SpreadsheetActions( {
	row,
	tree,
	actions,
	disabled,
	onOpen,
	onAdd,
} ) {
	let key = contentKey( row.contentId );
	if ( row.termId ) {
		key = skillKey( row.groupId, row.termId );
	} else if ( row.groupId ) {
		key = groupKey( row.groupId );
	}
	const node = tree.index.get( key );
	if ( ! node ) {
		return null;
	}
	const step = ( direction ) => {
		if ( node.kind === 'skill' ) {
			return actions.stepSkill( row.groupId, row.termId, direction );
		}
		if ( node.kind === 'group' ) {
			return actions.stepChapter( node.group, direction );
		}
		return actions.stepTopic( node.content, direction );
	};
	const controls = [
		{
			title: __( 'Open details and resources', 'ohmylms' ),
			onClick: () => onOpen( key ),
		},
		{
			title: __( 'Add skill in this chapter', 'ohmylms' ),
			onClick: () => onAdd( row, 'skill' ),
		},
		{
			title: __( 'Add folder in this unit', 'ohmylms' ),
			onClick: () => onAdd( row, 'folder' ),
		},
		{
			title: __( 'Move up', 'ohmylms' ),
			isDisabled: stepAmongSiblings( tree, node, -1 ) === null,
			onClick: () => step( -1 ),
		},
		{
			title: __( 'Move down', 'ohmylms' ),
			isDisabled: stepAmongSiblings( tree, node, 1 ) === null,
			onClick: () => step( 1 ),
		},
		{
			title: __( 'Move to another folder', 'ohmylms' ),
			isDisabled: ! node.parentKey,
			onClick: () => onOpen( key ),
		},
	];
	if ( row.termId ) {
		controls.push( {
			title: __( 'Remove skill from this chapter', 'ohmylms' ),
			onClick: () => actions.removeSkill( row.groupId, row.termId ),
		} );
	}
	return (
		<RowMenu
			label={ __( 'Row actions', 'ohmylms' ) }
			controls={ controls }
			disabled={ disabled }
		/>
	);
}
