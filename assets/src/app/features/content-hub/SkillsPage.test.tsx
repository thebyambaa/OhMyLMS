import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as api from './api';
import { contentHub } from './index';
import SkillsPage from './SkillsPage';

vi.mock( './api', async ( original ) => ( {
	...( await original< typeof import( './api' ) >() ),
	listSkills: vi.fn(),
	getSkillSummary: vi.fn(),
	saveSkill: vi.fn(),
	readSkill: vi.fn(),
	updateSkill: vi.fn(),
	suggestTranslation: vi.fn(),
	changeArchive: vi.fn(),
	listTargets: vi.fn(),
	changeLink: vi.fn(),
} ) );
const skill = {
	id: 7,
	name: 'Algebra',
	code: 'M1',
	description: 'Notes',
	parent: 0,
	prerequisites: [],
	archived: false,
};
const feature = { ...contentHub, component: SkillsPage };
function open( capabilities = { edit_posts: true, manage_options: true } ) {
	return renderShell( [ feature ], {
		route: '/content-hub/skills',
		capabilities,
	} );
}
beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( api.listSkills ).mockResolvedValue( {
		items: [ skill ],
		total: 21,
		totalPages: 2,
	} );
	vi.mocked( api.getSkillSummary ).mockResolvedValue( skill );
	vi.mocked( api.listTargets ).mockResolvedValue( {
		items: [ { id: 9, title: 'Lesson A', linked: true } ],
		total: 1,
		totalPages: 1,
	} );
} );
it( 'hides Skills and sends no requests without author capability', () => {
	open( { edit_posts: false, manage_options: false } );
	expect( api.listSkills ).not.toHaveBeenCalled();
	expect(
		screen.queryByRole( 'link', { name: 'Skills' } )
	).not.toBeInTheDocument();
} );
it( 'shows the shared accessible table, pages and repeats a search', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	expect(
		screen.getByRole( 'table', { name: 'Skills library' } )
	).toHaveClass( 'wp-list-table' );
	await user.click(
		screen.getAllByRole( 'link', { name: 'Next page' } )[ 0 ]!
	);
	await waitFor( () =>
		expect( api.listSkills ).toHaveBeenLastCalledWith(
			2,
			'',
			expect.any( AbortSignal )
		)
	);
	await user.type(
		screen.getByLabelText( 'Search skills by name' ),
		'Algebra'
	);
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await waitFor( () =>
		expect( api.listSkills ).toHaveBeenLastCalledWith(
			1,
			'Algebra',
			expect.any( AbortSignal )
		)
	);
	const count = vi.mocked( api.listSkills ).mock.calls.length;
	await user.click( screen.getByRole( 'button', { name: 'Search' } ) );
	await waitFor( () =>
		expect( vi.mocked( api.listSkills ).mock.calls.length ).toBeGreaterThan(
			count
		)
	);
} );
it( 'preserves edits when save fails and retries without closing the form', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	vi.mocked( api.saveSkill )
		.mockRejectedValueOnce( new Error( 'Cycle detected' ) )
		.mockResolvedValueOnce( 8 );
	await user.click( screen.getByRole( 'button', { name: 'Add skill' } ) );
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Add skill' } )
	);
	await user.type(
		dialog.getByLabelText( 'Name', { exact: true } ),
		'Geometry'
	);
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await dialog.findByText( 'Cycle detected' );
	expect( dialog.getByLabelText( 'Name', { exact: true } ) ).toHaveValue(
		'Geometry'
	);
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await within( screen.getByRole( 'main' ) ).findByText( 'Skill saved.' );
	expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
} );
it( 'confirms before archiving', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	await user.click( screen.getByRole( 'button', { name: 'Archive' } ) );
	expect( api.changeArchive ).not.toHaveBeenCalled();
	await user.click(
		within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
			name: 'Confirm',
		} )
	);
	await waitFor( () =>
		expect( api.changeArchive ).toHaveBeenCalledWith(
			7,
			true,
			expect.any( AbortSignal )
		)
	);
} );
it( 'authors can edit skills without archive controls', async () => {
	open( { edit_posts: true, manage_options: false } );
	await screen.findByText( 'Algebra' );
	expect(
		screen.getByRole( 'button', { name: 'Edit skill: Algebra' } )
	).toBeEnabled();
	expect(
		screen.queryByRole( 'button', { name: 'Archive' } )
	).not.toBeInTheDocument();
} );
it( 'updates one relationship and retains errors in the links dialog', async () => {
	const user = userEvent.setup();
	open();
	await screen.findByText( 'Algebra' );
	await user.click( screen.getByRole( 'button', { name: 'Links' } ) );
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Lesson and course links' } )
	);
	await dialog.findByText( 'Lesson A' );
	vi.mocked( api.changeLink ).mockRejectedValueOnce(
		new Error( 'Permission changed' )
	);
	await user.click( dialog.getByRole( 'button', { name: 'Unlink' } ) );
	await dialog.findByText( 'Permission changed' );
	expect( api.changeLink ).toHaveBeenCalledWith(
		7,
		'lessons',
		{ id: 9, title: 'Lesson A', linked: true },
		expect.any( AbortSignal )
	);
} );
function detail( overrides: Partial< api.SkillDetail[ 'skill' ] > = {} ) {
	return {
		skill: {
			...skill,
			objective: '',
			effective_objective: 'Notes',
			public_practice: false,
			translations: {
				en: { name: '', objective: '', description: '' },
				mn: { name: '', objective: '', description: '' },
			},
			...overrides,
		},
		revision: 'a'.repeat( 64 ),
		resources: { lessons: 2, courses: 1, questions: 4 },
		canPublishPractice: true,
	};
}
it( 'edits objectives and translations against the read revision', async () => {
	const user = userEvent.setup();
	vi.mocked( api.readSkill ).mockResolvedValue( detail() );
	vi.mocked( api.updateSkill ).mockResolvedValue( detail() );
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit skill: Algebra' } )
	);
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Edit skill' } )
	);
	await dialog.findByText( 'Linked lessons: 2' );
	await user.type(
		dialog.getByLabelText( 'Full learning objective' ),
		'Solve x < 2'
	);
	await user.type( dialog.getByLabelText( 'Name (Mongolian)' ), 'Алгебр' );
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await within( screen.getByRole( 'main' ) ).findByText( 'Skill saved.' );
	const [ id, draft, baseline, revision ] = vi.mocked( api.updateSkill ).mock
		.calls[ 0 ]!;
	expect( id ).toBe( 7 );
	expect( revision ).toBe( 'a'.repeat( 64 ) );
	expect( api.skillPatch( draft, baseline ) ).toEqual( {
		objective: 'Solve x < 2',
		translations: { mn: { name: 'Алгебр' } },
	} );
} );
it( 'keeps edits after a stale save and reloads before saving again', async () => {
	const user = userEvent.setup();
	vi.mocked( api.readSkill )
		.mockResolvedValueOnce( detail() )
		.mockResolvedValueOnce( detail( { code: 'M2' } ) );
	vi.mocked( api.updateSkill ).mockRejectedValueOnce(
		new ApiError( {
			status: 409,
			code: 'ohmylms_skill_stale',
			message: 'This skill changed since you opened it.',
		} )
	);
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit skill: Algebra' } )
	);
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Edit skill' } )
	);
	await user.type(
		await dialog.findByLabelText( 'Full learning objective' ),
		'Mine'
	);
	await user.click( dialog.getByRole( 'button', { name: 'Save skill' } ) );
	await dialog.findByText( /This skill changed/ );
	expect(
		dialog.getByRole( 'button', { name: 'Save skill' } )
	).toBeDisabled();
	await user.click(
		dialog.getByRole( 'button', {
			name: 'Reload latest and keep my edits',
		} )
	);
	await dialog.findByText( /Your unsaved edits were kept/ );
	expect( dialog.getByLabelText( 'Full learning objective' ) ).toHaveValue(
		'Mine'
	);
	expect( dialog.getByLabelText( 'Code' ) ).toHaveValue( 'M2' );
	expect(
		dialog.getByRole( 'button', { name: 'Save skill' } )
	).toBeEnabled();
} );
it( 'offers public practice only to administrators', async () => {
	const user = userEvent.setup();
	vi.mocked( api.readSkill ).mockResolvedValue( {
		...detail(),
		canPublishPractice: false,
	} );
	open( { edit_posts: true, manage_options: false } );
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit skill: Algebra' } )
	);
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Edit skill' } )
	);
	expect(
		await dialog.findByRole( 'checkbox', {
			name: /Allow public catalogue practice/,
		} )
	).toBeDisabled();
} );
it( 'asks before discarding unsaved skill edits', async () => {
	const user = userEvent.setup();
	vi.mocked( api.readSkill ).mockResolvedValue( detail() );
	open();
	await user.click(
		await screen.findByRole( 'button', { name: 'Edit skill: Algebra' } )
	);
	const dialog = within(
		screen.getByRole( 'dialog', { name: 'Edit skill' } )
	);
	await user.type( await dialog.findByLabelText( 'Code' ), 'X' );
	await user.click( dialog.getByRole( 'button', { name: 'Cancel' } ) );
	expect( dialog.getByText( 'Discard unsaved skill edits?' ) ).toBeVisible();
	await user.click( dialog.getByRole( 'button', { name: 'Discard edits' } ) );
	expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
} );
it( 'opens the create dialog from ?add=1', async () => {
	renderShell( [ feature ], {
		route: '/content-hub/skills?add=1',
		capabilities: { edit_posts: true, manage_options: true },
	} );
	expect(
		await screen.findByRole( 'dialog', { name: 'Add skill' } )
	).toBeVisible();
	expect( api.listSkills ).toHaveBeenCalled();
} );
it( 'aborts obsolete catalogue requests on navigation away', async () => {
	const view = open();
	await screen.findByText( 'Algebra' );
	const signal = vi.mocked( api.listSkills ).mock.calls[ 0 ]?.[ 2 ];
	view.unmount();
	expect( signal?.aborted ).toBe( true );
} );
