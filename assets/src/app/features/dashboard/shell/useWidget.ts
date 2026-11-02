import { useRequest } from '../../../hooks/useRequest';
import { getWidget } from './api';
import type { WidgetContext } from './model';

/**
 * Data of a widget's endpoint (`GET /dashboard/widgets/<id>`) for the current scope and period. A new
 * scope or period cancels the running request; the last good data stays visible while the next loads.
 *
 * @param widget  Widget id.
 * @param context Scope and period.
 * @param parse   Validates the widget's `data`.
 * @return `data` (the widget's validated data), `envelope` (with scope, period and `generatedAt`),
 *         `loading`, `error` and `reload`.
 */
export function useWidget< T >(
	widget: string,
	context: WidgetContext,
	parse: ( data: unknown ) => T
) {
	const request = useRequest(
		( signal ) =>
			getWidget(
				widget,
				context.scope.id,
				context.period,
				parse,
				signal
			),
		{
			key: `${ widget }|${ context.scope.id }|${ context.period }`,
			keepData: true,
			errorShape: 'object',
		}
	);
	return {
		...request,
		data: request.data ? request.data.data : null,
		envelope: request.data,
	};
}
