import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ApiError } from '../../lib/api';
import * as api from './api';
import ClassEditorPage from './ClassEditorPage';
import { member, page, person, schoolClass } from './fixtures';

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
	} );
} );
