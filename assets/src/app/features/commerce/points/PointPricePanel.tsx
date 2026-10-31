import {
	Button,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { get, patch } from '../../../lib/api';
import { useOperation } from '../useOperation';
import { useRequest } from '../useRequest';

const MAX_POINTS = 100000;

export interface CoursePointPrice {
	course_id: number;
	point_price: number;
	points_enabled: boolean;
	purchase_enabled: boolean;
}

/**
 * Validate the server's point price of a course.
 *
 * @param value Untrusted body.
 * @return Typed price.
 */
export function parsePointPrice( value: unknown ): CoursePointPrice {
	const row = ( value && 'object' === typeof value ? value : {} ) as Record<
		string,
		unknown
	>;
	if (
		! Number.isSafeInteger( row.course_id ) ||
		! Number.isSafeInteger( row.point_price ) ||
		'boolean' !== typeof row.points_enabled ||
		'boolean' !== typeof row.purchase_enabled
	) {
		throw new Error(
			__( 'The server returned an invalid point price.', 'ohmylms' )
		);
	}
	return row as unknown as CoursePointPrice;
}

/**
 * Check a typed point price: empty or a whole number from 0 to 100000.
 *
 * @param input Field value.
 * @return Message, or null when valid.
 */
export function pointPriceError( input: string ): string | null {
	const value = Number( input );
	return '' === input.trim() ||
		( Number.isInteger( value ) && value >= 0 && value <= MAX_POINTS )
		? null
		: sprintf(
				/* translators: %d: maximum points. */
				__( 'Enter a whole number of points from 0 to %d.', 'ohmylms' ),
				MAX_POINTS
			);
}

const route = ( id: number ) =>
	`/ohmylms/v1/point-purchases/courses/${ Math.max( 1, Math.trunc( id ) ) }`;

/**
 * Bonus point price of a course (Rewards › purchase with points). Saved on its own, separately from
 * the course form, through `ohmylms/v1/point-purchases/courses/<id>`.
 *
 * @param props          Component props.
 * @param props.courseId Course ID.
 * @param props.disabled Disable while the course form saves.
 */
export function PointPricePanel( {
	courseId,
	disabled = false,
}: {
	courseId: number;
	disabled?: boolean;
} ) {
	const request = useRequest(
		async ( signal ) =>
			parsePointPrice(
				await get< unknown >( route( courseId ), { signal } )
			),
		String( courseId )
	);
	const operation = useOperation();
	const [ price, setPrice ] = useState( '' );
	const [ enabled, setEnabled ] = useState( true );
	const [ saved, setSaved ] = useState( false );
	const stored = request.data;
	useEffect( () => {
		if ( stored ) {
			setPrice( stored.point_price ? String( stored.point_price ) : '' );
			setEnabled( stored.points_enabled );
		}
	}, [ stored ] );

	if ( request.error && ! stored ) {
		return 403 === request.error.status ? null : (
			<Notice status="error" isDismissible={ false }>
				{ request.error.message }
			</Notice>
		);
	}
	if ( ! stored ) {
		return null;
	}
	const error = pointPriceError( price );
	const dirty =
		Number( price || 0 ) !== stored.point_price ||
		enabled !== stored.points_enabled;
	const save = async () => {
		if ( error || ! dirty ) {
			return;
		}
		setSaved( false );
		const done = await operation.run( async ( signal ) => {
			request.replace(
				parsePointPrice(
					await patch< unknown >(
						route( courseId ),
						{
							point_price: Number( price || 0 ),
							points_enabled: enabled,
						},
						{ signal }
					)
				)
			);
		} );
		setSaved( done );
	};

	return (
		<fieldset disabled={ disabled || operation.busy }>
			<legend>{ __( 'Bonus points price', 'ohmylms' ) }</legend>
			{ ! stored.purchase_enabled && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Buying with points is off. Turn it on in Engagement › Gamification › Rewards; until then this price is not offered.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ saved && ! dirty && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Point price saved.', 'ohmylms' ) }
				</Notice>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Can be bought with points', 'ohmylms' ) }
				checked={ enabled }
				onChange={ setEnabled }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="number"
				min={ 0 }
				max={ MAX_POINTS }
				label={ __( 'Price in points', 'ohmylms' ) }
				help={
					error ??
					__(
						'Learners spend this many bonus points instead of paying. Leave empty or 0 to sell for money only.',
						'ohmylms'
					)
				}
				aria-invalid={ error ? true : undefined }
				value={ price }
				onChange={ setPrice }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						void save();
					}
				} }
			/>
			<Button
				type="button"
				variant="secondary"
				isBusy={ operation.busy }
				disabled={ Boolean( error ) || ! dirty || operation.busy }
				accessibleWhenDisabled
				onClick={ () => void save() }
			>
				{ __( 'Save point price', 'ohmylms' ) }
			</Button>
		</fieldset>
	);
}
