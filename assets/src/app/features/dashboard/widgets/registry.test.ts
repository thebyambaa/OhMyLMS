import { context, allScope, northScope } from '../shell/fixtures';
import { builtInWidgets, dashboardWidgets } from './index';
import { createWidgetRegistry } from './registry';
import type { DashboardWidgetDefinition } from './registry';

const widget = (
	overrides: Partial< DashboardWidgetDefinition >
): DashboardWidgetDefinition => ( {
	id: 'one',
	title: 'One',
	order: 10,
	component: () => null,
	...overrides,
} );

describe( 'dashboard widget registry', () => {
	it( 'lists widgets by order, then id', () => {
		const registry = createWidgetRegistry( [
			widget( { id: 'late', order: 50 } ),
			widget( { id: 'b-early', order: 10 } ),
			widget( { id: 'a-early', order: 10 } ),
		] );
		expect( registry.all().map( ( item ) => item.id ) ).toEqual( [
			'a-early',
			'b-early',
			'late',
		] );
	} );

	it( 'rejects duplicate and malformed ids without throwing', () => {
		const registry = createWidgetRegistry( [ widget( {} ) ] );
		expect( registry.register( widget( { title: 'Again' } ) ) ).toBe(
			false
		);
		expect( registry.register( widget( { id: 'Bad Id' } ) ) ).toBe( false );
		expect( registry.register( widget( { id: 'two', order: 5 } ) ) ).toBe(
			true
		);
		expect( registry.all().map( ( item ) => item.title ) ).toEqual( [
			'One',
			'One',
		] );
		expect( registry.all()[ 0 ]?.id ).toBe( 'two' );
	} );

	it( 'filters by context', () => {
		const registry = createWidgetRegistry( [
			widget( { id: 'everyone' } ),
			widget( {
				id: 'admins',
				isAvailable: ( current ) => current.scope.commerce,
			} ),
		] );
		expect(
			registry.list( context( allScope ) ).map( ( item ) => item.id )
		).toEqual( [ 'admins', 'everyone' ] );
		expect(
			registry.list( context( northScope ) ).map( ( item ) => item.id )
		).toEqual( [ 'everyone' ] );
	} );

	it( 'ships needs attention, pulse and the admin overview', () => {
		expect( builtInWidgets.map( ( item ) => item.id ) ).toEqual( [
			'needs-attention',
			'pulse',
			'overview',
		] );
		expect(
			dashboardWidgets
				.list( context( northScope ) )
				.map( ( item ) => item.id )
		).toEqual( [ 'needs-attention', 'pulse' ] );
		expect(
			dashboardWidgets
				.list( context( allScope ) )
				.map( ( item ) => item.id )
		).toEqual( [ 'needs-attention', 'pulse', 'overview' ] );
	} );
} );
