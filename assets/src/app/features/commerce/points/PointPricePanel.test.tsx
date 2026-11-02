import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { get, patch } from '../../../lib/api';
import { PointPricePanel, pointPriceError } from './PointPricePanel';

vi.mock( '../../../lib/api', async ( original ) => ( {
	...( await original< typeof import( '../../../lib/api' ) >() ),
	get: vi.fn(),
	patch: vi.fn(),
} ) );

const stored = {
	course_id: 40,
	point_price: 0,
	points_enabled: true,
	purchase_enabled: false,
};

beforeEach( () => {
	vi.clearAllMocks();
	vi.mocked( get ).mockResolvedValue( stored );
} );

describe( 'course point price', () => {
	it( 'accepts empty or whole numbers in range', () => {
		expect( pointPriceError( '' ) ).toBeNull();
		expect( pointPriceError( '50' ) ).toBeNull();
		expect( pointPriceError( '-1' ) ).toBeTruthy();
		expect( pointPriceError( '2.5' ) ).toBeTruthy();
		expect( pointPriceError( '100001' ) ).toBeTruthy();
	} );

	it( 'saves the price on its own route and says when purchases are off', async () => {
		vi.mocked( patch ).mockResolvedValue( { ...stored, point_price: 50 } );
		const user = userEvent.setup();
		render( <PointPricePanel courseId={ 40 } /> );
		expect(
			await screen.findByText( /Buying with points is off/ )
		).toBeInTheDocument();
		const save = screen.getByRole( 'button', { name: 'Save point price' } );
		expect( save ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.type( screen.getByLabelText( 'Price in points' ), '-3' );
		expect( save ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.clear( screen.getByLabelText( 'Price in points' ) );
		await user.type( screen.getByLabelText( 'Price in points' ), '50' );
		await user.click( save );
		expect( patch ).toHaveBeenCalledWith(
			'/ohmylms/v1/point-purchases/courses/40',
			{ point_price: 50, points_enabled: true },
			expect.anything()
		);
		expect(
			await screen.findByText( 'Point price saved.', {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
	} );
} );
