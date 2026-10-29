/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createCommunityDetails( readRuntime ) {
	return function CommunityDetails( props ) {
		const {
			L2: ImageField,
			M,
			Pf: DetailField,
			React,
			T: StoreModule,
			b: I18n,
			f9,
			g: ReactHooks,
			p9,
			v9,
			y: WordPressData,
		} = readRuntime();
		var errors = props.errors,
			n = ( props.setErrors, props.validate );
		M().noConflict();
		var r = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectCommunity();
			}, [] ),
			setCommunity = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).setCommunity,
			o = function ( e ) {
				return e
					.toLowerCase()
					.trim()
					.replace( /[^\w\s-]/g, '' )
					.replace( /[\s_-]+/g, '-' )
					.replace( /^-+|-+$/g, '' );
			},
			i = function ( e, t, _iLocal ) {
				var l = p9( p9( {}, r ), {}, f9( {}, e, t ) );
				if ( ( 'name' === e || 'title' === e ) && t ) {
					var c = o( t ),
						u = r.slug || '';
					if ( ! u || u === o( r.title || r.name ) ) {
						l.slug = c;
					}
				}
				if ( 'thumbnail' === e ) {
					l.space_photo = _iLocal;
				} else if ( 'cover_image_url' === e ) {
					l.cover_image = _iLocal;
				}
				setCommunity( l );
				n( l );
			};
		return (
			( 0, ReactHooks.useEffect )( function () {
				( function ( e, t ) {
					var _nLocal = p9( {}, e ),
						_rLocal = ! 1;
					for ( var _oLocal in t ) {
						if ( ! (
							e &&
							e.hasOwnProperty( _oLocal ) &&
							e[ _oLocal ]
						) ) {
							{
								_nLocal[ _oLocal ] = t[ _oLocal ];
								_rLocal = ! 0;
							}
						}
					}
					if ( _rLocal ) {
						setCommunity( _nLocal );
					}
				} )( r, v9 );
				n( r );
			}, [] ),
			(
				<React.Fragment>
					<DetailField
						title={ ( 0, I18n.__ )( 'Name', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'What would you like to call this community?',
							'ohmylms'
						) }
						value={ decodeHtmlEntities(
							r === null || r === void 0 ? void 0 : r.title
						) }
						onChange={ function ( e ) {
							return i( 'title', e );
						} }
						error={
							errors === null || errors === void 0
								? void 0
								: errors.title
						}
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<DetailField
						title={ ( 0, I18n.__ )( 'Slug', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'The URL-friendly version of the name. This will be used in the community URL.',
							'ohmylms'
						) }
						value={
							( r === null || r === void 0 ? void 0 : r.slug ) ||
							''
						}
						onChange={ function ( e ) {
							return i( 'slug', e );
						} }
						placeholder={ ( 0, I18n.__ )(
							'community-slug',
							'ohmylms'
						) }
						error={
							errors === null || errors === void 0
								? void 0
								: errors.slug
						}
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<DetailField
						title={ ( 0, I18n.__ )( 'Description', 'ohmylms' ) }
						description={ ( 0, I18n.__ )(
							'Describe your space to help members understand its purpose.',
							'ohmylms'
						) }
						value={ decodeHtmlEntities(
							r === null || r === void 0 ? void 0 : r.description
						) }
						onChange={ function ( e ) {
							return i( 'description', e );
						} }
						inputType={ 'textarea' }
					/>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<Controls.SpacerWP padding={ 4 } marginBottom={ 2 }>
						<ImageField
							title={ ( 0, I18n.__ )( 'Space Photo', 'ohmylms' ) }
							description={ ( 0, I18n.__ )(
								'Upload a photo that represents your community space.',
								'ohmylms'
							) }
							handleChange={ function ( e, t ) {
								return i( 'thumbnail', e, t );
							} }
							handleRemove={ function () {
								return i( 'thumbnail', null, null );
							} }
							brandingImg={
								r === null || r === void 0
									? void 0
									: r.thumbnail
							}
							alertTitle={ ( 0, I18n.__ )(
								'Remove Space Photo',
								'ohmylms'
							) }
							alertDescription={ ( 0, I18n.__ )(
								'Are you sure you want to remove this space photo?',
								'ohmylms'
							) }
							showDivider={ ! 1 }
							maxWidth={ '345px' }
						/>
					</Controls.SpacerWP>
					<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
					<Controls.SpacerWP padding={ 4 } marginBottom={ 2 }>
						<ImageField
							title={ ( 0, I18n.__ )( 'Cover Image', 'ohmylms' ) }
							description={ ( 0, I18n.__ )(
								'Upload a photo that represents your community space.',
								'ohmylms'
							) }
							handleChange={ function ( e, t ) {
								return i( 'cover_image_url', e, t );
							} }
							handleRemove={ function () {
								return i( 'cover_image_url', null, null );
							} }
							brandingImg={
								r === null || r === void 0
									? void 0
									: r.cover_image_url
							}
							alertTitle={ ( 0, I18n.__ )(
								'Remove Cover Image',
								'ohmylms'
							) }
							alertDescription={ ( 0, I18n.__ )(
								'Are you sure you want to remove this cover image?',
								'ohmylms'
							) }
							showDivider={ ! 1 }
							maxWidth={ '345px' }
						/>
					</Controls.SpacerWP>
				</React.Fragment>
			 )
		 );
	};
}
