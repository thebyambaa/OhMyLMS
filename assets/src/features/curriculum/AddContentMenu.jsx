import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Dashicon,
	Dropdown,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { KindIcon } from './WorkspaceParts';

const ICONS = {
	skill: 'awards',
	library: 'book',
	attach: 'media-document',
	chapter: 'category',
	topic: 'category',
};

/**
 * The "Add Content" button of the right pane, like the course builder's. `items` are
 * `{key, title, info, onClick}`; picking one closes the menu and runs it.
 * @param root0
 * @param root0.label
 * @param root0.items
 * @param root0.disabled
 * @param root0.buttonLabel
 * @param root0.className
 * @param root0.compact
 */
export function AddContentMenu( {
	label,
	items,
	disabled,
	buttonLabel = __( 'Add Content', 'ohmylms' ),
	className = '',
	compact = false,
} ) {
	return (
		<Dropdown
			className={ `ohmylms-ws-add-content-wrap ${ className }`.trim() }
			popoverProps={ {
				placement: 'bottom-end',
				className: `ohmylms-ws-content-popover${ compact ? ' is-compact' : '' }`,
			} }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="secondary"
					className={ `ohmylms-ws-add-content${ isOpen ? ' is-open' : '' }${ compact ? ' is-compact' : '' }` }
					onClick={ onToggle }
					aria-expanded={ isOpen }
					aria-haspopup="menu"
					disabled={ disabled }
				>
					<Dashicon icon="plus-alt2" aria-hidden="true" />
					{ buttonLabel }
					<Dashicon
						icon="arrow-down-alt2"
						className="ohmylms-ws-add-chevron"
						aria-hidden="true"
					/>
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<MenuGroup label={ compact ? undefined : label }>
					{ items.map( ( item ) => (
						<MenuItem
							key={ item.key }
							className="ohmylms-ws-content-option"
							onClick={ () => {
								onClose();
								item.onClick();
							} }
						>
							<span
								className={ `ohmylms-ws-content-icon is-${ item.key }` }
								aria-hidden="true"
							>
								{ item.key === 'topic' ||
								item.key === 'chapter' ? (
									<KindIcon kind={ item.key } />
								) : (
									<Dashicon
										icon={
											ICONS[ item.key ] || 'plus-alt2'
										}
									/>
								) }
							</span>
							<span className="ohmylms-ws-content-copy">
								<span className="ohmylms-ws-content-title">
									{ item.title }
								</span>
								{ ! compact && (
									<span className="ohmylms-ws-content-info">
										{ item.info }
									</span>
								) }
							</span>
							{ ! compact && (
								<Dashicon
									icon="arrow-right-alt2"
									className="ohmylms-ws-content-arrow"
									aria-hidden="true"
								/>
							) }
						</MenuItem>
					) ) }
				</MenuGroup>
			) }
		/>
	);
}
