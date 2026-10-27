import { createElement, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, RadioControl } from '@wordpress/components';
import { useCurriculum } from './context';

/**
 * Inline, two-step deletion. Linked courses, skills, question banks and exams are never deleted,
 * only their place in the structure; an item with children needs an explicit choice for them.
 * @param root0
 * @param root0.item
 * @param root0.dependents
 */
export function DeleteItem( { item, dependents } ) {
	const c = useCurriculum();
	const [ confirming, setConfirming ] = useState( false );
	const [ strategy, setStrategy ] = useState( 'promote' );
	const info = dependents || {
		children: 0,
		descendants: 0,
		links: 0,
		own_links: 0,
		bundles: 0,
		own_bundles: 0,
		groups: 0,
		own_groups: 0,
	};
	const branch = info.children > 0 && strategy === 'delete';
	const links = branch ? info.links : info.own_links;
	const bundles = branch ? info.bundles : info.own_bundles;
	const groups = ( branch ? info.groups : info.own_groups ) || 0;
	async function remove() {
		const ok = await c.actions.remove( item, {
			children: info.children ? strategy : '',
			confirm: true,
		} );
		if ( ! ok ) {
			setConfirming( true );
		}
	}
	if ( ! confirming ) {
		return (
			<div className="ohmylms-cur-delete">
				<Button
					variant="secondary"
					isDestructive
					onClick={ () => setConfirming( true ) }
					aria-label={ sprintf(
						/* translators: %s: curriculum item name. */ __(
							'Delete %s…',
							'ohmylms'
						),
						item.name
					) }
				>
					{ __( 'Delete…', 'ohmylms' ) }
				</Button>
			</div>
		);
	}
	return (
		<div
			className="ohmylms-cur-delete is-confirming"
			role="group"
			aria-label={ sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Confirm deleting %s',
					'ohmylms'
				),
				item.name
			) }
		>
			<p>
				<strong>
					{ sprintf(
						/* translators: %s: curriculum item name. */ __(
							'Delete “%s”?',
							'ohmylms'
						),
						item.name
					) }
				</strong>
			</p>
			{ info.children > 0 && (
				<RadioControl
					label={ sprintf(
						/* translators: %d: number of child curriculum items. */ _n(
							'This item has %d child.',
							'This item has %d children.',
							info.children,
							'ohmylms'
						),
						info.children
					) }
					selected={ strategy }
					options={ [
						{
							value: 'promote',
							label: sprintf(
								/* translators: %d: number of child curriculum items. */ _n(
									'Move its %d child up one level and delete only this item',
									'Move its %d children up one level and delete only this item',
									info.children,
									'ohmylms'
								),
								info.children
							),
						},
						{
							value: 'delete',
							label: sprintf(
								/* translators: %d: number of curriculum items. */ _n(
									'Delete this item and everything under it (%d item in total)',
									'Delete this item and everything under it (%d items in total)',
									info.descendants + 1,
									'ohmylms'
								),
								info.descendants + 1
							),
						},
					] }
					onChange={ setStrategy }
				/>
			) }
			<ul className="ohmylms-cur-delete-effects">
				<li>
					{ links
						? sprintf(
								/* translators: %d: number of selected questions. */ _n(
									'%d link to courses, skills, question banks or exams will be removed. The content itself is not deleted.',
									'%d links to courses, skills, question banks or exams will be removed. The content itself is not deleted.',
									links,
									'ohmylms'
								),
								links
							)
						: __( 'No content is linked here.', 'ohmylms' ) }
				</li>
				{ groups > 0 && (
					<li>
						{ sprintf(
							/* translators: %d: number of skills. */ _n(
								'%d folder will be deleted. The skills in it stay in the skill library.',
								'%d folders will be deleted. The skills in them stay in the skill library.',
								groups,
								'ohmylms'
							),
							groups
						) }
					</li>
				) }
				{ bundles > 0 && (
					<li>
						{ sprintf(
							/* translators: %d: number of course bundles. */ _n(
								'It will be removed from %d course bundle.',
								'It will be removed from %d course bundles.',
								bundles,
								'ohmylms'
							),
							bundles
						) }
					</li>
				) }
			</ul>
			<div className="ohmylms-cur-delete-actions">
				<Button
					variant="primary"
					isDestructive
					isBusy={ c.pending }
					disabled={ c.pending }
					onClick={ remove }
				>
					{ __( 'Delete item', 'ohmylms' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ () => setConfirming( false ) }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</div>
		</div>
	);
}
