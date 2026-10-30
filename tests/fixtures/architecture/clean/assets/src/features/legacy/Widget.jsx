// Recovered component that the app does not import.
export function createWidget( readRuntime ) {
	return function Widget() {
		const { React } = readRuntime();
		return React.createElement( 'div' );
	};
}
