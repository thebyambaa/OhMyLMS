import { render, screen } from '@testing-library/react';
import { lazy, Suspense } from '@wordpress/element';
import { MemoryRouter } from 'react-router-dom';
import * as api from './coverage-api';

vi.mock( './coverage-api', () => ( { getSkillCoverage: vi.fn() } ) );
// The outline editor and the publication panel are not under test; stubbing them keeps this load fast.
vi.mock( './CourseEditorPage', () => ( { CourseOutline: () => null } ) );
vi.mock( './PublicationPanel', () => ( { PublicationPanel: () => null } ) );

/**
 * The courses feature loads this module lazily and renders the step it takes from it. This test loads the
 * real entry file the same way, not a stand-in, so a lazy component nested in another lazy one fails here
 * (React error 306, "Did you wrap a component in React.lazy() more than once?").
 */
const Step = lazy( () =>
	import( './public' ).then( ( module ) => ( {
		default: module.SkillCoverage,
	} ) )
);

// The first import of the real view pulls in @wordpress/components, which is slow when cold or under load.
beforeAll( async () => {
	await import( './SkillCoverage' );
}, 120000 );

describe( 'course structure public entry', () => {
	it( 'renders the skill coverage step when it is loaded lazily by the courses feature', async () => {
		vi.mocked( api.getSkillCoverage ).mockResolvedValue( {
			courseId: 7,
			summary: {
				skills: 1,
				required: 0,
				covered: 1,
				needsTeaching: 0,
				needsQuestions: 0,
				needsBoth: 0,
				objectives: 0,
				objectivesTaught: 0,
				requiredNotReady: 0,
			},
			skills: [
				{
					id: 1,
					name: 'Add halves',
					code: '',
					outcome: false,
					required: false,
					target: '',
					objective: false,
					practice: false,
					teaching: [],
					assessing: [],
					questions: 3,
					questionsNeed: 3,
					families: 2,
					familiesNeed: 2,
					needBasis: 'default',
					teachingOk: false,
					questionsOk: true,
					status: 'needs-teaching',
				},
			],
		} );
		render(
			<MemoryRouter>
				<Suspense fallback={ <p>Loading the step</p> }>
					<Step course={ 7 } unsaved={ false } />
				</Suspense>
			</MemoryRouter>
		);
		expect( await screen.findByText( 'Add halves' ) ).toBeInTheDocument();
		expect( api.getSkillCoverage ).toHaveBeenCalledWith(
			7,
			expect.any( AbortSignal )
		);
	} );
} );
