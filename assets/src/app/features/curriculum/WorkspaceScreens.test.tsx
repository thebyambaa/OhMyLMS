import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '../../lib/api';
import { renderShell } from '../../test/render';
import * as bundleApi from './bundles';
import BundlesPage from './BundlesPage';
import { bundles, skillMap, subjectProfiles } from './index';
import * as mapApi from './skill-map';
import SkillMapPage from './SkillMapPage';
import * as profileApi from './subject-profiles';
import SubjectProfilesPage from './SubjectProfilesPage';

vi.mock( './skill-map', async ( original ) => ( {
	...( await original< typeof import( './skill-map' ) >() ),
	readSubjects: vi.fn(),
	readMap: vi.fn(),
} ) );
vi.mock( './subject-profiles', async ( original ) => ( {
	...( await original< typeof import( './subject-profiles' ) >() ),
	readProfiles: vi.fn(),
	saveProfile: vi.fn(),
	profileCommand: vi.fn(),
} ) );
vi.mock( './bundles', async ( original ) => ( {
	...( await original< typeof import( './bundles' ) >() ),
	listBundles: vi.fn(),
	readBundle: vi.fn(),
	createBundle: vi.fn(),
	updateBundle: vi.fn(),
	saveMembers: vi.fn(),
	publishBundle: vi.fn(),
	deleteBundle: vi.fn(),
	searchTargets: vi.fn(),
} ) );
function open(
	feature: typeof skillMap,
	component: () => JSX.Element,
	allowed = true
) {
	renderShell( [ { ...feature, component } ], {
		route: feature.path,
		capabilities: { manage_options: allowed },
	} );
}
const stale = ( code: string ) =>
	new ApiError( { status: 409, code, message: 'Changed elsewhere.' } );
beforeEach( () => vi.resetAllMocks() );

describe( 'Skill map', () => {
	const map: mapApi.SkillMap = {
		subject: 'Maths',
		domains: [ { id: 'number', label: 'Number' } ],
		grades: [
			{ value: 1, label: 'Grade 1' },
			{ value: 2, label: 'Grade 2' },
		],
		skills: [
			{
				id: 1,
				name: 'Count',
				code: 'C',
				domain: 'number',
				grade_from: 1,
				grade_to: 1,
				prerequisites: [],
			},
			{
				id: 2,
				name: 'Add',
				code: '',
				domain: 'number',
				grade_from: 2,
				grade_to: 2,
				prerequisites: [ 1 ],
			},
		],
		unplaced: [],
		issues: [ { type: 'long_gap', skill: 2, other: 1 } ],
		syllabus: {
			id: 9,
			name: 'Syllabus A',
			objectives: 3,
			unmapped: 1,
			covered: [ 2 ],
		},
	};
	it( 'keeps the screen and its requests behind administrator capability', () => {
		open( skillMap, SkillMapPage, false );
		expect( mapApi.readSubjects ).not.toHaveBeenCalled();
		expect( skillMap.extensionRoute ).toBe(
			'/content-hub/curriculum/skill-map'
		);
	} );
	it( 'shows a subject grid with syllabus coverage, checks and skill details', async () => {
		const user = userEvent.setup();
		vi.mocked( mapApi.readSubjects ).mockResolvedValue( [
			{
				subject: 'Maths',
				skills: 2,
				syllabuses: [ { id: 9, name: 'Syllabus A', code: '' } ],
			},
		] );
		vi.mocked( mapApi.readMap ).mockResolvedValue( map );
		open( skillMap, SkillMapPage );
		await user.selectOptions(
			await screen.findByLabelText( 'Subject' ),
			'Maths'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Syllabus coverage' ),
			'9'
		);
		await user.click( screen.getByRole( 'button', { name: 'Show map' } ) );
		expect( mapApi.readMap ).toHaveBeenCalledWith(
			'Maths',
			9,
			expect.any( AbortSignal )
		);
		const table = await screen.findByRole( 'table' );
		expect(
			within( table ).getByRole( 'button', {
				name: 'Add (taught by the syllabus)',
			} )
		).toBeVisible();
		expect( screen.getByText( /Add builds on Count/ ) ).toBeVisible();
		await user.click(
			within( table ).getByRole( 'button', { name: 'Count' } )
		);
		expect( screen.getByText( 'Needed by: Add' ) ).toBeVisible();
	} );
	it( 'rejects malformed map responses', () => {
		expect( () =>
			mapApi.parseMap( {
				...map,
				syllabus: null,
				issues: [ { type: 'unknown', skill: 1, other: 0 } ],
			} )
		).toThrow();
	} );
} );

describe( 'Subject profiles', () => {
	const payload: profileApi.ProfilesPayload = {
		revision: 'a'.repeat( 16 ),
		default: 'generic',
		subjects: [],
		profiles: [
			{
				key: 'generic',
				label: 'Generic',
				language: '',
				domains: [],
				levels: [ 'Level 1' ],
				ai: { role: '', context: '', level_hints: '' },
				builtin: true,
				customized: false,
			},
			{
				key: 'art',
				label: 'Art',
				language: 'en',
				domains: [ { id: 'colour', label: 'Colour' } ],
				levels: [ 'Stage 1', 'Stage 2' ],
				ai: { role: '', context: '', level_hints: '' },
				builtin: false,
				customized: true,
			},
		],
	};
	it( 'saves against the read revision and keeps edits after a conflict', async () => {
		const user = userEvent.setup();
		vi.mocked( profileApi.readProfiles )
			.mockResolvedValueOnce( payload )
			.mockResolvedValueOnce( {
				...payload,
				revision: 'b'.repeat( 16 ),
			} );
		vi.mocked( profileApi.saveProfile ).mockRejectedValueOnce(
			stale( 'ohmylms_subject_profile_conflict' )
		);
		open( subjectProfiles, SubjectProfilesPage );
		await user.click(
			await screen.findByRole( 'button', { name: 'Edit profile: Art' } )
		);
		await user.type(
			screen.getByLabelText( 'Levels, one per line, in order' ),
			'{enter}Stage 3'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save profile' } )
		);
		await screen.findAllByText( /Changed elsewhere/ );
		expect( profileApi.saveProfile ).toHaveBeenCalledWith(
			expect.objectContaining( {
				key: 'art',
				levels: 'Stage 1\nStage 2\nStage 3',
			} ),
			'a'.repeat( 16 ),
			expect.any( AbortSignal )
		);
		expect(
			screen.getByRole( 'button', { name: 'Save profile' } )
		).toBeDisabled();
		await user.click(
			screen.getByRole( 'button', {
				name: 'Reload profiles and keep my edits',
			} )
		);
		await screen.findAllByText( /Profiles reloaded/ );
		expect(
			screen.getByLabelText( 'Levels, one per line, in order' )
		).toHaveValue( 'Stage 1\nStage 2\nStage 3' );
		vi.mocked( profileApi.saveProfile ).mockResolvedValueOnce( payload );
		await user.click(
			screen.getByRole( 'button', { name: 'Save profile' } )
		);
		await waitFor( () =>
			expect( profileApi.saveProfile ).toHaveBeenLastCalledWith(
				expect.anything(),
				'b'.repeat( 16 ),
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'confirms before removing a custom profile', async () => {
		const user = userEvent.setup();
		vi.mocked( profileApi.readProfiles ).mockResolvedValue( payload );
		vi.mocked( profileApi.profileCommand ).mockResolvedValue( payload );
		open( subjectProfiles, SubjectProfilesPage );
		await user.click(
			await screen.findByRole( 'button', { name: 'Remove' } )
		);
		expect( profileApi.profileCommand ).not.toHaveBeenCalled();
		await user.click(
			within( screen.getByRole( 'dialog' ) ).getByRole( 'button', {
				name: 'Confirm',
			} )
		);
		await waitFor( () =>
			expect( profileApi.profileCommand ).toHaveBeenCalledWith(
				'remove',
				'art',
				'a'.repeat( 16 ),
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'builds domain ids and refuses profiles without levels', () => {
		const form = {
			...profileApi.formOf( null ),
			label: 'Physics',
			domains: [ { id: '', label: 'Électricité 2' } ],
			levels: ' One \n\n Two ',
		};
		expect( profileApi.payloadOf( form ) ).toEqual(
			expect.objectContaining( {
				domains: [ { id: 'electricite-2', label: 'Électricité 2' } ],
				levels: [ 'One', 'Two' ],
			} )
		);
		expect( () =>
			profileApi.payloadOf( { ...form, levels: ' ' } )
		).toThrow();
	} );
} );

describe( 'Course bundles', () => {
	const bundle: bundleApi.Bundle = {
		id: 4,
		title: 'Starter',
		description: '',
		status: 'draft',
		member_count: 2,
		follower_count: 3,
		updated_at: '2026-10-30 10:00:00',
	};
	const detail: bundleApi.BundleDetail = {
		bundle,
		members: [
			{ type: 'course', id: 11, title: 'Course A', available: true },
			{ type: 'curriculum', id: 5, title: 'Unit B', available: true },
		],
	};
	beforeEach( () => {
		vi.mocked( bundleApi.listBundles ).mockResolvedValue( {
			bundles: [ bundle ],
			maxMembers: 100,
		} );
		vi.mocked( bundleApi.readBundle ).mockResolvedValue( detail );
	} );
	it( 'reorders members and saves them against the read version', async () => {
		const user = userEvent.setup();
		vi.mocked( bundleApi.saveMembers ).mockResolvedValue( detail );
		open( bundles, BundlesPage );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Edit bundle: Starter',
			} )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Move Unit B up' } )
		);
		expect(
			screen.getByRole( 'button', { name: 'Publish' } )
		).toBeDisabled();
		await user.click(
			screen.getByRole( 'button', { name: 'Save members' } )
		);
		await waitFor( () =>
			expect( bundleApi.saveMembers ).toHaveBeenCalledWith(
				bundle,
				[ detail.members[ 1 ], detail.members[ 0 ] ],
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'keeps member edits after a stale save and reloads the version', async () => {
		const user = userEvent.setup();
		vi.mocked( bundleApi.saveMembers ).mockRejectedValueOnce(
			stale( 'ohmylms_bundle_stale' )
		);
		open( bundles, BundlesPage );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Edit bundle: Starter',
			} )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Remove Course A' } )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Save members' } )
		);
		await screen.findAllByText( /Changed elsewhere/ );
		await user.click(
			screen.getByRole( 'button', {
				name: 'Reload bundle and keep my edits',
			} )
		);
		await screen.findAllByText( /Bundle reloaded/ );
		expect(
			screen.queryByRole( 'button', { name: 'Remove Course A' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Save members' } )
		).toBeEnabled();
	} );
	it( 'warns about followers before deleting', async () => {
		const user = userEvent.setup();
		vi.mocked( bundleApi.deleteBundle ).mockResolvedValue();
		open( bundles, BundlesPage );
		await user.click(
			await screen.findByRole( 'button', {
				name: 'Edit bundle: Starter',
			} )
		);
		await user.click(
			await screen.findByRole( 'button', { name: 'Delete bundle…' } )
		);
		const dialog = within( screen.getByRole( 'dialog' ) );
		expect(
			dialog.getByText( /3 learners follow this bundle/ )
		).toBeVisible();
		await user.click(
			dialog.getByRole( 'button', { name: 'Delete bundle' } )
		);
		await waitFor( () =>
			expect( bundleApi.deleteBundle ).toHaveBeenCalledWith(
				bundle,
				true,
				expect.any( AbortSignal )
			)
		);
	} );
	it( 'moves members within bounds', () => {
		expect( bundleApi.moveMember( detail.members, 0, -1 ) ).toBe(
			detail.members
		);
		expect(
			bundleApi
				.moveMember( detail.members, 0, 1 )
				.map( ( row ) => row.id )
		).toEqual( [ 5, 11 ] );
	} );
} );
