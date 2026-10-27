/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createEmailMobileIcon( readRuntime ) {
	return function EmailMobileIcon() {
		const { React } = readRuntime();
		return (
			<React.Fragment>
				<svg
					fill={ 'none' }
					width={ '14' }
					height={ '20' }
					viewBox={ '0 0 14 20' }
					xmlns={ 'http://www.w3.org/2000/svg' }
				>
					<path
						fill={ 'currentColor' }
						stroke={ 'currentColor' }
						strokeWidth={ '.3' }
						d={
							'M10.882 1H3.118C1.948 1 1 1.863 1 2.929V17.07C1 18.137 1.948 19 3.118 19h7.764c1.17 0 2.118-.863 2.118-1.929V2.93C13 1.863 12.052 1 10.882 1zm.706 16.071c0 .355-.316.643-.706.643H3.118c-.39 0-.706-.287-.706-.643V2.93c0-.355.316-.643.706-.643h.706c0 .355.316.643.705.643h4.942c.39 0 .706-.288.706-.643h.705c.39 0 .706.288.706.643v14.14z'
						}
					/>
				</svg>
			</React.Fragment>
		);
	};
}
