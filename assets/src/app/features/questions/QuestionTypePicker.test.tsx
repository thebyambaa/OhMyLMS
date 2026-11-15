import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { groupQuestionTypes, QuestionTypePicker } from './QuestionTypePicker';
import type { QuestionType } from './types';

function type( id: string, label: string ): QuestionType {
	return {
		id,
		label,
		format: null,
		answers: null,
		manual: false,
		snapshot: false,
		builtin: true,
	};
}

const types = [
	type( 'crossword', 'Crossword' ),
	type( 'example-number', 'Example number' ),
	type( 'multiple-choice', 'Multiple Choice' ),
	type( 'numerical', 'Numerical' ),
	type( 'single-choice', 'Single Choice' ),
];

describe( 'groupQuestionTypes', () => {
	it( 'puts the most used types first, in a fixed order, and keeps unknown types', () => {
		const sections = groupQuestionTypes( types );
		expect( sections.map( ( section ) => section.id ) ).toEqual( [
			'common',
			'games',
			'more',
		] );
		expect( sections[ 0 ]?.types.map( ( item ) => item.id ) ).toEqual( [
			'single-choice',
			'multiple-choice',
			'numerical',
		] );
		expect( sections[ 2 ]?.types.map( ( item ) => item.id ) ).toEqual( [
			'example-number',
		] );
	} );

	it( 'filters by label and drops empty sections', () => {
		const sections = groupQuestionTypes( types, ' CHOICE ' );
		expect( sections ).toHaveLength( 1 );
		expect( sections[ 0 ]?.types ).toHaveLength( 2 );
		expect( groupQuestionTypes( types, 'nothing like this' ) ).toEqual(
			[]
		);
	} );
} );

describe( 'QuestionTypePicker', () => {
	it( 'shows every registered type once and reports the chosen one', async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();
		render(
			<QuestionTypePicker
				types={ types }
				value="single-choice"
				onChange={ onChange }
			/>
		);
		expect( screen.getAllByRole( 'radio' ) ).toHaveLength( types.length );
		expect(
			screen.getByRole( 'radio', { checked: true } )
		).toHaveAttribute( 'value', 'single-choice' );
		await user.click( screen.getByRole( 'radio', { name: /Crossword/ } ) );
		expect( onChange ).toHaveBeenCalledWith( 'crossword' );
	} );

	it( 'narrows the tiles with the search box', async () => {
		const user = userEvent.setup();
		render(
			<QuestionTypePicker
				types={ types }
				value="single-choice"
				onChange={ () => {} }
			/>
		);
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search question types' } ),
			'cross'
		);
		expect( screen.getAllByRole( 'radio' ) ).toHaveLength( 1 );
		await user.type(
			screen.getByRole( 'searchbox', { name: 'Search question types' } ),
			'zzz'
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'No question type matches your search.'
		);
	} );
} );
