import type { ReactNode, TableHTMLAttributes } from 'react';

/**
 * A WordPress `table.widefat` (striped by default) for data that is not a list screen: key/value
 * summaries, ordered editors with their own controls, comparisons. The caption is for screen readers,
 * as WordPress tables are introduced by a heading. Lists use `ListTable`.
 *
 * @param props           Table properties.
 * @param props.caption   Accessible table name.
 * @param props.striped   Alternate row backgrounds (`striped`).
 * @param props.fixed     Equal column widths (`fixed`).
 * @param props.className Extra classes.
 * @param props.children  `thead`/`tbody`/`tfoot`.
 */
export function WidefatTable( {
	caption,
	striped = true,
	fixed = false,
	className = '',
	children,
	...rest
}: {
	caption: string;
	striped?: boolean;
	fixed?: boolean;
	className?: string;
	children: ReactNode;
} & Omit< TableHTMLAttributes< HTMLTableElement >, 'className' > ) {
	return (
		<table
			{ ...rest }
			className={ [
				'widefat',
				fixed && 'fixed',
				striped && 'striped',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<caption className="screen-reader-text">{ caption }</caption>
			{ children }
		</table>
	);
}
