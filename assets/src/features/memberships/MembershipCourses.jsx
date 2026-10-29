import { createElement } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { CurriculumMindmap } from './CurriculumMindmap';
import { restUrl } from '../restUrl.mjs';

/**
 * Which courses a membership plan includes: individual courses, curriculum items (with everything
 * below them) and Course Bundles, minus exclusions. Category and tag rules saved before those were
 * replaced still grant access, so they are listed here and can only be removed.
 * @param readRuntime
 */
export function createMembershipCourses( readRuntime ) {
	return function MembershipCourses() {
		const {
			I: Controls,
			Ea,
			T: StoreModule,
			b: I18n,
			g: Hooks,
			y: Data,
			l: apiFetch,
			Ge,
		} = readRuntime();
		const plan = Data.useSelect(
			( select ) =>
				select( StoreModule.default ).selectMembershipPlanData(),
			[]
		);
		const { updateMembershipPlan } = Data.useDispatch(
			StoreModule.default
		);
		const [ courses, setCourses ] = Hooks.useState( [] );
		const [ items, setItems ] = Hooks.useState( [] );
		const [ preview, setPreview ] = Hooks.useState( null );
		const [ error, setError ] = Hooks.useState( '' );
		const [ previewError, setPreviewError ] = Hooks.useState( '' );
		const [ loading, setLoading ] = Hooks.useState( false );
		const [ itemStatus, setItemStatus ] = Hooks.useState( 'loading' );
		const searchSequence = Hooks.useRef( 0 );
		// Resolve against WordPress's localized REST root, not the admin page URL.
		const request = ( { path, ...settings } ) => {
			const root = window.ohmylms_params?.api_url;
			return apiFetch()( {
				...settings,
				...( root
					? {
							url: restUrl( root, path ),
						}
					: {
							path,
						} ),
			} );
		};
		const options = ( rows ) =>
			rows.map( ( item ) => ( {
				value: Number( item.term_id ?? item.id ),
				label: Ge( item.name ),
			} ) );
		const ids = ( list ) => ( list || [] ).map( Number );
		const selection = {
			all_learning_content: Boolean( plan?.all_learning_content ),
			products: plan?.products || [],
			course_curriculum: ids( plan?.course_curriculum ),
			course_bundles: ids( plan?.course_bundles ),
			// Rules saved before categories and tags were replaced; they keep granting access until removed.
			course_categories: ids( plan?.course_categories ),
			course_tags: ids( plan?.course_tags ),
			excluded_courses: plan?.excluded_courses || [],
		};
		const selectionKey = JSON.stringify( selection );
		const hiddenTopicIds = new Set(
			items
				.filter( ( item ) => item.item_type === 'topic' )
				.map( ( item ) => item.value )
		);
		const visibleItems = items.filter(
			( item ) => ! hiddenTopicIds.has( item.value )
		);
		const hiddenSelections = selection.course_curriculum.filter( ( id ) =>
			hiddenTopicIds.has( id )
		);
		Hooks.useEffect( () => {
			let active = true;
			request( {
				path: '/ohmylms/v1/curriculum/outline?courses=1',
			} )
				.then( ( outline ) => {
					if ( active ) {
						setItems(
							( outline.items || [] ).map( ( item ) => ( {
								value: Number( item.id ),
								label: Ge( item.name ),
								item_type: item.item_type,
								parent: Number( item.parent_id || 0 ),
								courses: ( item.courses || [] ).map(
									( course ) => ( {
										...course,
										title: Ge( course.title ),
									} )
								),
							} ) )
						);
						setItemStatus( 'ready' );
					}
				} )
				.catch( ( cause ) => {
					if ( active ) {
						setItemStatus( 'error' );
						setError(
							cause.message ||
								I18n.__(
									'Could not load the curriculum.',
									'ohmylms'
								)
						);
					}
				} );

			return () => {
				active = false;
			};
		}, [] );
		async function searchCourses( search = '' ) {
			const sequence = ++searchSequence.current;
			try {
				const data = await request( {
					path:
						'/ohmylms/v1/courses?post_status=publish&per_page=100&search=' +
						encodeURIComponent( search ),
				} );
				if ( sequence === searchSequence.current ) {
					setCourses( options( data ) );
				}
			} catch ( cause ) {
				setError(
					cause.message ||
						I18n.__( 'Could not load courses.', 'ohmylms' )
				);
			}
		}
		Hooks.useEffect( () => {
			searchCourses();
			return () => {
				searchSequence.current++;
			};
		}, [] );
		Hooks.useEffect( () => {
			let active = true;
			if ( selection.excluded_courses.length ) {
				const query = selection.excluded_courses
					.map( ( id ) => 'include[]=' + encodeURIComponent( id ) )
					.join( '&' );
				request( {
					path:
						'/ohmylms/v1/courses?post_status=publish&per_page=100&' +
						query,
				} )
					.then( ( data ) => {
						if ( active ) {
							setCourses( ( current ) => [
								...new Map(
									[ ...current, ...options( data ) ].map(
										( item ) => [ item.value, item ]
									)
								).values(),
							] );
						}
					} )
					.catch( ( cause ) => {
						if ( active ) {
							setError( cause.message );
						}
					} );
			}
			return () => {
				active = false;
			};
		}, [] );
		Hooks.useEffect( () => {
			let active = true;
			setLoading( true );
			const timer = setTimeout( () => {
				request( {
					path: '/ohmylms/v1/membership/course-preview',
					method: 'POST',
					data: JSON.parse( selectionKey ),
				} )
					.then( ( data ) => {
						if ( active ) {
							setPreview( data );
							setPreviewError( '' );
						}
					} )
					.catch( ( cause ) => {
						if ( active ) {
							setPreview( null );
							setPreviewError(
								cause.message ||
									I18n.__(
										'Could not preview courses.',
										'ohmylms'
									)
							);
						}
					} )
					.finally( () => {
						if ( active ) {
							setLoading( false );
						}
					} );
			}, 200 );
			return () => {
				active = false;
				clearTimeout( timer );
			};
		}, [ selectionKey ] );
		const selected = ( _idsLocal, choices ) =>
			_idsLocal.map(
				( id ) =>
					choices.find( ( item ) => item.value === Number( id ) ) || {
						value: Number( id ),
						label: '#' + id,
					}
			);
		const direct = selection.products.map( ( item ) => ( {
			value: Number( item.id ),
			label: Ge( item.label || item.name || '#' + item.id ),
		} ) );
		const courseChoices = [
			...new Map(
				[
					...courses,
					...direct,
					...( preview?.courses || [] ).map( ( item ) => ( {
						value: item.id,
						label: Ge( item.name ),
					} ) ),
				].map( ( item ) => [ item.value, item ] )
			).values(),
		];
		const field = (
			label,
			value,
			choices,
			onChange,
			searchable = false
		) => (
			<div
				style={ {
					marginBottom: 20,
				} }
			>
				<Controls.HeadingWP level={ 4 }>{ label }</Controls.HeadingWP>
				<Controls.AdvancedSelectWP
					isMulti
					closeMenuOnSelect={ false }
					value={ value }
					options={ choices }
					onChange={ ( rows ) => onChange( rows || [] ) }
					onSearch={ searchable ? searchCourses : undefined }
				/>
			</div>
		);
		// Old category and tag rules still on the plan, by name, that can be removed but not added to.
		const legacy = [
			...( plan?.legacy_rules?.categories || [] )
				.filter( ( rule ) =>
					selection.course_categories.includes( rule.id )
				)
				.map( ( rule ) => ( {
					...rule,
					field: 'course_categories',
					kind: I18n.__( 'category', 'ohmylms' ),
				} ) ),
			...( plan?.legacy_rules?.tags || [] )
				.filter( ( rule ) => selection.course_tags.includes( rule.id ) )
				.map( ( rule ) => ( {
					...rule,
					field: 'course_tags',
					kind: I18n.__( 'tag', 'ohmylms' ),
				} ) ),
		];
		return (
			<Controls.SpacerWP
				marginTop={ 4 }
				className="ohmylms-membership-plan-course-section"
			>
				<Ea isBorderless variant="secondary">
					<Controls.SpacerWP padding={ 6 } margin={ 0 }>
						<CheckboxControl
							label={ I18n.__(
								'All learning content',
								'ohmylms'
							) }
							help={ I18n.__(
								'Include all current and future published courses, including their lessons and quizzes. Excluded courses still apply.',
								'ohmylms'
							) }
							checked={ selection.all_learning_content }
							onChange={ ( checked ) =>
								updateMembershipPlan(
									'all_learning_content',
									checked
								)
							}
							__nextHasNoMarginBottom
						/>
						<p>
							{ I18n.__(
								'Include individual courses, curriculum items. Matching courses below a curriculum item and future published courses are included automatically. Exclusions override every inclusion.',
								'ohmylms'
							) }
						</p>
						{ field(
							I18n.__( 'Individual courses', 'ohmylms' ),
							direct,
							courseChoices,
							( rows ) =>
								updateMembershipPlan(
									'products',
									rows.map( ( item ) => ( {
										...item,
										id: item.value,
										name: item.label,
									} ) )
								),
							true
						) }
						{ field(
							I18n.__( 'Curriculum', 'ohmylms' ),
							selected(
								selection.course_curriculum.filter(
									( id ) => ! hiddenTopicIds.has( id )
								),
								visibleItems
							),
							visibleItems,
							( rows ) =>
								updateMembershipPlan( 'course_curriculum', [
									...hiddenSelections,
									...rows.map( ( item ) => item.value ),
								] )
						) }
						<CurriculumMindmap
							items={ items }
							selected={ selection.course_curriculum }
							onChange={ ( _idsLocal2 ) =>
								updateMembershipPlan(
									'course_curriculum',
									_idsLocal2
								)
							}
							emptyMessage={ ( () => {
								if ( itemStatus === 'loading' ) {
									return I18n.__(
										'Loading the curriculum…',
										'ohmylms'
									);
								}
								if ( itemStatus === 'error' ) {
									return I18n.__(
										'The curriculum could not be loaded. Please reload this page to try again.',
										'ohmylms'
									);
								}
								return undefined;
							} )() }
						/>

						{ field(
							I18n.__( 'Excluded courses', 'ohmylms' ),
							selected(
								selection.excluded_courses,
								courseChoices
							),
							courseChoices,
							( rows ) =>
								updateMembershipPlan(
									'excluded_courses',
									rows.map( ( item ) => item.value )
								),
							true
						) }
						{ legacy.length > 0 && (
							<Notice
								status="warning"
								isDismissible={ false }
								className="ohmylms-membership-legacy-rules"
							>
								<p>
									{ I18n.__(
										'This plan still includes courses through old course categories and tags. They keep granting access, so members are not affected, but they can no longer be changed. Remove one once its courses are covered by curriculum items.',
										'ohmylms'
									) }
								</p>
								<ul>
									{ legacy.map( ( rule ) => (
										<li key={ rule.field + rule.id }>
											{ Ge( rule.name ) } ({ rule.kind }){ ' ' }
											<Button
												variant="secondary"
												size="small"
												onClick={ () =>
													updateMembershipPlan(
														rule.field,
														selection[
															rule.field
														].filter(
															( id ) =>
																id !== rule.id
														)
													)
												}
											>
												{ I18n.__(
													'Remove',
													'ohmylms'
												) }
											</Button>
										</li>
									) ) }
								</ul>
							</Notice>
						) }
						<Notice status="info" isDismissible={ false }>
							{ I18n.__(
								'Active members gain access to matching courses automatically. Removing a match or excluding a course removes access granted by this plan, including courses already started.',
								'ohmylms'
							) }
						</Notice>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						{ previewError && (
							<Notice status="error" isDismissible={ false }>
								{ previewError }
							</Notice>
						) }
						<Controls.HeadingWP level={ 4 }>
							{ I18n.__( 'Included courses preview', 'ohmylms' ) }
							{ ! loading && preview
								? ` (${ preview.total })`
								: '' }
						</Controls.HeadingWP>
						{ loading ? (
							<Spinner />
						) : (
							preview &&
							( preview.courses.length ? (
								<table className="widefat striped">
									<thead>
										<tr>
											<th>
												{ I18n.__(
													'Course',
													'ohmylms'
												) }
											</th>
											<th>
												{ I18n.__(
													'Included through',
													'ohmylms'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ preview.courses.map( ( course ) => (
											<tr key={ course.id }>
												<td>{ Ge( course.name ) }</td>
												<td>
													{ course.reasons.join(
														', '
													) }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							) : (
								<p>
									{ I18n.__(
										'No published courses currently match this plan.',
										'ohmylms'
									) }
								</p>
							) )
						) }
					</Controls.SpacerWP>
				</Ea>
			</Controls.SpacerWP>
		);
	};
}
