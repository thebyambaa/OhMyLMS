import type { ComponentType } from '@wordpress/element';
import type { WidgetContext } from '../shell/model';

/** Grid width of a widget: the whole row, about 7/12 or about 5/12. Narrow screens stack every widget. */
export type WidgetSize = 'full' | 'wide' | 'narrow';

export interface DashboardWidgetProps {
	/** Scope, period and audience the widget is rendered for. */
	readonly context: WidgetContext;
}

/**
 * One dashboard widget. Each lives in its own component file under `widgets/` and is listed in
 * `widgets/index.ts`; the page only renders what the registry returns.
 */
export interface DashboardWidgetDefinition {
	/** Unique kebab-case id; also the id of its server endpoint when it has one. */
	readonly id: string;
	/** Translated title, used for the loading placeholder and error messages. */
	readonly title: string;
	/** Position, ascending. Built-ins use multiples of 10. */
	readonly order: number;
	/** Grid width; `wide` by default. */
	readonly size?: WidgetSize;
	/**
	 * Whether the widget applies to a context, for example only where `context.scope.commerce` is true.
	 * Presentation only: the widget's endpoint enforces access itself.
	 */
	readonly isAvailable?: ( context: WidgetContext ) => boolean;
	/** The widget; usually `lazy( () => import( './MyWidget' ) )`. */
	readonly component: ComponentType< DashboardWidgetProps >;
}

const ID = /^[a-z][a-z0-9-]*$/;

export interface WidgetRegistry {
	/**
	 * Add a widget. Ignored (returns false) when the id is malformed or already registered.
	 *
	 * @param widget Definition.
	 */
	register: ( widget: DashboardWidgetDefinition ) => boolean;
	/**
	 * Widgets that apply to a context, in order.
	 *
	 * @param context Scope, period and audience.
	 */
	list: ( context: WidgetContext ) => DashboardWidgetDefinition[];
	/** Every registered widget, in order. */
	all: () => DashboardWidgetDefinition[];
}

/**
 * Create a widget registry.
 *
 * @param initial Widgets to register first.
 * @return The registry.
 */
export function createWidgetRegistry(
	initial: readonly DashboardWidgetDefinition[] = []
): WidgetRegistry {
	const widgets = new Map< string, DashboardWidgetDefinition >();
	const sorted = () =>
		[ ...widgets.values() ].sort(
			( a, b ) => a.order - b.order || a.id.localeCompare( b.id )
		);
	const registry: WidgetRegistry = {
		register( widget ) {
			if ( ! ID.test( widget.id ) || widgets.has( widget.id ) ) {
				return false;
			}
			widgets.set( widget.id, widget );
			return true;
		},
		list( context ) {
			return sorted().filter(
				( widget ) => widget.isAvailable?.( context ) ?? true
			);
		},
		all: sorted,
	};
	initial.forEach( ( widget ) => registry.register( widget ) );
	return registry;
}
