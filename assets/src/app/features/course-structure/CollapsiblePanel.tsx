import { useId, useState } from '@wordpress/element';
import type { ReactNode } from 'react';

/**
 * A titled panel whose body folds away. The body stays mounted while folded, so a panel that holds staged,
 * unsaved edits never loses them by being closed.
 *
 * @param props             Component props.
 * @param props.title       Panel title.
 * @param props.summary     Short status shown beside the title, also while folded.
 * @param props.defaultOpen Whether it starts open when `open` is not given.
 * @param props.open        Controls whether the panel is open.
 * @param props.onToggle    Called with the new state when the header is pressed.
 * @param props.lazy        Mount the body only once the panel has been opened.
 * @param props.className   Extra class for the panel.
 * @param props.children    Panel body.
 */
export function CollapsiblePanel( {
	title,
	summary,
	defaultOpen = false,
	open,
	onToggle,
	lazy = false,
	className = '',
	children,
}: {
	title: string;
	summary?: string;
	defaultOpen?: boolean;
	open?: boolean;
	onToggle?: ( open: boolean ) => void;
	lazy?: boolean;
	className?: string;
	children: ReactNode;
} ) {
	const bodyId = useId();
	const [ inner, setInner ] = useState( defaultOpen );
	const isOpen = open ?? inner;
	const [ seen, setSeen ] = useState( false );
	if ( isOpen && ! seen ) {
		setSeen( true );
	}
	return (
		<section className={ `oml-panel ${ className }`.trim() }>
			<h2 className="oml-panel-title">
				<button
					type="button"
					className="oml-panel-toggle"
					aria-expanded={ isOpen }
					aria-controls={ bodyId }
					onClick={ () => {
						setInner( ! isOpen );
						onToggle?.( ! isOpen );
					} }
				>
					<span className="oml-panel-name">{ title }</span>
					{ summary && (
						<span className="oml-panel-summary">{ summary }</span>
					) }
					<span
						className={ `dashicons dashicons-arrow-${
							isOpen ? 'up' : 'down'
						}-alt2` }
						aria-hidden="true"
					/>
				</button>
			</h2>
			<div id={ bodyId } className="oml-panel-body" hidden={ ! isOpen }>
				{ ( ! lazy || seen || isOpen ) && children }
			</div>
		</section>
	);
}
