import { createElement } from '@wordpress/element';

export function ListTableFrame( { readRuntime, toolbar, children } ) {
	const { I: Controls, Ea: Card } = readRuntime();
	return (
		<Card isBorderless minHeight="calc(100vh - 200px)">
			<Controls.SpacerWP padding={ 5 }>
				{ toolbar }
				{ children }
			</Controls.SpacerWP>
		</Card>
	);
}
