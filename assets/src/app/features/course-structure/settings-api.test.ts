import * as client from '../../lib/api';
import {
	parseSettings,
	readSettings,
	saveSettings,
	type CourseSettings,
} from './settings-api';
import { parsePublication } from './publication-api';

vi.mock( '../../lib/api', async ( original ) => ( {
	...( await original< typeof client >() ),
	get: vi.fn(),
	put: vi.fn(),
} ) );
const settings: CourseSettings = {
	course_id: 12,
	revision: 'token',
	title: 'Math',
	description: '',
	author_id: 1,
	image_id: 0,
	status: 'draft',
	price_type: 'free',
	regular_price: '0',
	sale_price: '',
	access_type: 'public',
	has_capacity: false,
	capacity: 0,
};
beforeEach( () => vi.resetAllMocks() );

it( 'rejects malformed and cross-course responses', async () => {
	expect( () =>
		parseSettings( { ...settings, has_capacity: 'yes' } )
	).toThrow();
	expect( () => parseSettings( { ...settings, image_id: -1 } ) ).toThrow();
	vi.mocked( client.get ).mockResolvedValue( { ...settings, course_id: 13 } );
	await expect( readSettings( 12 ) ).rejects.toThrow();
	expect( () =>
		parsePublication( {
			course_id: 12,
			revision: 'token',
			readiness: 'ready',
			units: [],
		} )
	).toThrow();
} );

it( 'omits response-only identity and empty passwords from the mutation contract', async () => {
	vi.mocked( client.put ).mockResolvedValue( settings );
	const signal = new AbortController().signal;
	await saveSettings( settings, '', signal );
	const { course_id: ignored, ...payload } = settings;
	expect( ignored ).toBe( 12 );
	expect( client.put ).toHaveBeenCalledWith(
		'/ohmylms/v1/authoring/courses/12/settings',
		payload,
		{ signal }
	);
} );
