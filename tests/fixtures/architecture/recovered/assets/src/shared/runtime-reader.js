export function useStore( readRuntime ) {
	const { T } = readRuntime();
	return T;
}
