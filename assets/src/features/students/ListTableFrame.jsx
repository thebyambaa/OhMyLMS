import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';

export function ListTableFrame( { readRuntime, toolbar, children } ) {
	const { Ea: Card } = readRuntime();
	return (
		<Card isBorderless minHeight="calc(100vh - 200px)">
			<Controls.SpacerWP padding={ 5 }>
				{ toolbar }
				{ children }
			</Controls.SpacerWP>
		</Card>
	);
}
