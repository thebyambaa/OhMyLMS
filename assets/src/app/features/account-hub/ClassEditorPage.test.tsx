import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import ClassEditorPage from './ClassEditorPage';
import {
	classWork,
	member,
	page,
	person,
	schoolClass,
	submission,
} from './fixtures';
import * as operations from './operationsApi';

vi.mock( './operationsApi', () => ( {
	listClassWork: vi.fn(),
	assignClassWork: vi.fn(),
	listClassCourses: vi.fn(),
	listCourseActivities: vi.fn(),
	listSubmissions: vi.fn(),
	gradeSubmission: vi.fn(),
} ) );

vi.mock( './api', () => ( {
	getClass: vi.fn(),
	updateClass: vi.fn(),
	archiveClass: vi.fn(),
	restoreClass: vi.fn(),
	listMembers: vi.fn(),
	listCandidates: vi.fn(),
	addMember: vi.fn(),
	removeMember: vi.fn(),
} ) );

const teacherMember = {
	...member,
	id: 41,
	user_id: 12,
	display_name: 'Tess Teacher',
	role: 'teacher' as const,
};

function renderEditor() {
	return render(
		<MemoryRouter initialEntries={ [ '/account-hub/classes/9' ] }>
			<Routes>
				<Route
					path="/account-hub/classes/:id"
					element={ <ClassEditorPage /> }
				/>
			</Routes>
		</MemoryRouter>
	);
}

const notice = { selector: '.components-notice__content' };

beforeEach( () => {
	vi.resetAllMocks();
	vi.mocked( operations.listClassWork ).mockResolvedValue(
		page( [ classWork ] )
	);
	vi.mocked( operations.listSubmissions ).mockResolvedValue( {
		items: [ submission ],
		has_more: false,
	} );
	vi.mocked( operations.listClassCourses ).mockResolvedValue(
		page( [ { id: 15, title: 'Maths 5' } ] )
	);
	vi.mocked( operations.listCourseActivities ).mockResolvedValue( [
		{ id: 16, title: 'Essay' },
	] );
	vi.mocked( api.getClass ).mockResolvedValue( schoolClass );
	vi.mocked( api.listMembers ).mockResolvedValue(
		page( [ teacherMember, member ] )
	);
} );

describe( 'ClassEditorPage', () => {
	it( 'shows the class, its school link and roster', async () => {
		renderEditor();
		expect(
			await screen.findByRole( 'heading', { name: 'Algebra' } )
		).toBeVisible();
		expect(
			screen.getByRole( 'link', { name: '← North School' } )
		).toHaveAttribute( 'href', '/account-hub/schools/3' );
		const table = await screen.findByRole( 'table', {
			name: 'Class roster',
		} );
		const rows = within( table ).getAllByRole( 'row' );
		expect( within( rows[ 1 ]! ).getByText( 'Teacher' ) ).toBeVisible();
		expect( within( rows[ 2 ]! ).getByText( 'Ana Student' ) ).toBeVisible();
	} );
	it( 'removes a member and reloads the roster', async () => {
		vi.mocked( api.removeMember ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderEditor();
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Remove Ana Student from the class',
			} )
		);
		await waitFor( () =>
			expect( api.removeMember ).toHaveBeenCalledWith(
				9,
				21,
				'student',
				expect.any( AbortSignal )
			)
		);
		await waitFor( () =>
			expect( api.listMembers ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'searches the school roster and adds a teacher', async () => {
		vi.mocked( api.listCandidates ).mockResolvedValue( page( [ person ] ) );
		vi.mocked( api.addMember ).mockResolvedValue( member );
		const user = userEvent.setup();
		renderEditor();
		const search = await screen.findByRole( 'search', {
			name: 'Search the school roster',
		} );
		await user.selectOptions(
			within( search ).getByRole( 'combobox', { name: 'Add as' } ),
			'teacher'
		);
		await user.type(
			within( search ).getByRole( 'textbox', { name: 'Name' } ),
			'Bo'
		);
		await user.click(
			within( search ).getByRole( 'button', { name: 'Search roster' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Add Bo Student as teacher',
			} )
		);
		await waitFor( () =>
			expect( api.addMember ).toHaveBeenCalledWith(
				9,
				22,
				'teacher',
				expect.any( AbortSignal )
			)
		);
		expect( api.listCandidates ).toHaveBeenCalledWith(
			9,
			'teacher',
			'Bo',
			expect.any( AbortSignal )
		);
		await waitFor( () =>
			expect( api.listMembers ).toHaveBeenCalledTimes( 2 )
		);
	} );
	it( 'shows a roster error from the server', async () => {
		vi.mocked( api.listCandidates ).mockResolvedValue( page( [ person ] ) );
		vi.mocked( api.addMember ).mockRejectedValue(
			new ApiError( {
				status: 422,
				code: 'ohmylms_account_hub_not_on_roster',
				message: 'Only people on this school’s roster can join.',
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		await user.click(
			await screen.findByRole( 'button', { name: 'Search roster' } )
		);
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Add Bo Student as student',
			} )
		);
		expect(
			await screen.findByText(
				'Only people on this school’s roster can join.',
				notice
			)
		).toBeVisible();
	} );
	it( 'saves with the version and reports a stale save', async () => {
		vi.mocked( api.updateClass ).mockRejectedValueOnce(
			new ApiError( {
				status: 409,
				code: 'ohmylms_account_hub_conflict',
				message: 'This class was changed by someone else.',
			} )
		);
		const user = userEvent.setup();
		renderEditor();
		const grade = await screen.findByRole( 'textbox', { name: 'Grade' } );
		await user.clear( grade );
		await user.type( grade, '6' );
		await user.click(
			screen.getByRole( 'button', { name: 'Save class' } )
		);
		expect(
			await screen.findByText(
				/Someone else changed this record/,
				notice
			)
		).toBeVisible();
		expect( api.updateClass ).toHaveBeenCalledWith(
			9,
			{ name: 'Algebra', subject: 'Math', grade: '6' },
			schoolClass.version,
			expect.any( AbortSignal )
		);
		vi.mocked( api.updateClass ).mockResolvedValueOnce( {
			...schoolClass,
			grade: '6',
			version: 'cccccccccccccccc',
		} );
		await user.click(
			screen.getByRole( 'button', { name: 'Save class' } )
		);
		expect(
			await screen.findByText( 'Class saved.', notice )
		).toBeVisible();
		expect(
			screen.queryByText( /Someone else changed this record/, notice )
		).toBeNull();
	} );
	it( 'archives and restores instead of deleting', async () => {
		vi.mocked( api.archiveClass ).mockResolvedValue( {
			...schoolClass,
			status: 'archived',
		} );
		vi.mocked( api.restoreClass ).mockResolvedValue( schoolClass );
		const user = userEvent.setup();
		renderEditor();
		await user.click(
			await screen.findByRole( 'button', { name: 'Archive class' } )
		);
		expect(
			await screen.findByText( /Class archived/, notice )
		).toBeVisible();
		expect(
			screen.getByRole( 'textbox', { name: 'Class name' } )
		).toBeDisabled();
		expect(
			screen.queryByRole( 'search', { name: 'Search the school roster' } )
		).toBeNull();
		await user.click(
			screen.getByRole( 'button', { name: 'Restore class' } )
		);
		expect(
			await screen.findByText( 'Class restored.', notice )
		).toBeVisible();
		expect(
			screen.getByRole( 'textbox', { name: 'Class name' } )
		).toBeEnabled();
	} );
	it( 'explains how independent classes get students', async () => {
		vi.mocked( api.getClass ).mockResolvedValue( {
			...schoolClass,
			school_id: 0,
			school_name: null,
			academic_year_id: 0,
			academic_year_label: null,
		} );
		renderEditor();
		expect(
			await screen.findByText( /through its teacher’s invitation link/ )
		).toBeVisible();
		expect(
			screen.getByRole( 'link', { name: '← All classes' } )
		).toHaveAttribute( 'href', '/account-hub?tab=classes' );
		expect(
			screen.queryByRole( 'region', { name: 'Class work' } )
		).toBeNull();
	} );
	it( 'lists class work and assigns a course to the current students', async () => {
		vi.mocked( operations.assignClassWork ).mockResolvedValue( {
			id: 91,
			recipients: 2,
		} );
		const user = userEvent.setup();
		renderEditor();
		const table = await screen.findByRole( 'table', {
			name: 'Work assigned to this class',
		} );
		expect(
			within( table ).getByText( 'Fractions homework' )
		).toBeVisible();
		expect( within( table ).getByText( '1 of 2' ) ).toBeVisible();
		expect(
			screen.getByRole( 'link', { name: 'Guardians of this class' } )
		).toHaveAttribute(
			'href',
			'/account-hub/schools/3?panel=guardians&class_id=9'
		);
		await user.type(
			screen.getByRole( 'textbox', { name: 'Course title' } ),
			'Maths'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Find course' } )
		);
		const form = await screen.findByRole( 'form', { name: 'Assign work' } );
		await waitFor( () =>
			expect(
				within( form ).getByRole( 'option', { name: 'Essay' } )
			).toBeInTheDocument()
		);
		await user.selectOptions(
			within( form ).getByRole( 'combobox', { name: 'Activity' } ),
			'16'
		);
		await user.click(
			within( form ).getByRole( 'button', {
				name: 'Assign to current students',
			} )
		);
		expect(
			await screen.findByText( 'Assigned to 2 students.', notice )
		).toBeVisible();
		expect( operations.assignClassWork ).toHaveBeenCalledWith(
			9,
			{
				course_id: 15,
				content_id: 16,
				title: '',
				due_at: '',
				prior_completion: false,
			},
			expect.any( AbortSignal )
		);
		expect( operations.listClassCourses ).toHaveBeenCalledWith(
			9,
			'Maths',
			expect.any( AbortSignal )
		);
	} );
	it( 'grades a written submission within its points', async () => {
		vi.mocked( operations.gradeSubmission ).mockResolvedValue( undefined );
		const user = userEvent.setup();
		renderEditor();
		const table = await screen.findByRole( 'table', {
			name: 'Written submissions',
		} );
		await user.click(
			within( table ).getByRole( 'button', { name: 'Review' } )
		);
		const form = screen.getByRole( 'form', { name: 'Grade submission' } );
		const score = within( form ).getByRole( 'spinbutton', {
			name: 'Score (0 to 10)',
		} );
		await user.type( score, '11' );
		expect(
			within( form ).getByRole( 'button', { name: 'Save grade' } )
		).toBeDisabled();
		await user.clear( score );
		await user.type( score, '8' );
		await user.type(
			within( form ).getByRole( 'textbox', { name: 'Feedback' } ),
			'Good'
		);
		await user.click(
			within( form ).getByRole( 'button', { name: 'Save grade' } )
		);
		await waitFor( () =>
			expect( operations.gradeSubmission ).toHaveBeenCalledWith(
				9,
				95,
				90,
				8,
				'Good',
				expect.any( AbortSignal )
			)
		);
	} );
} );
