import { createElement, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { createCourse } from './api.mjs';
import { CourseBuildChoices } from './CourseBuildChoices';
export function createCourseCreateDialog( readRuntime ) {
	return function CourseCreateDialog( { isOpen, onClose } ) {
		const {
			T: { default: store },
			I: Controls,
			QG: { A: Modal },
			JG: CourseTypes,
			f: Router,
		} = readRuntime();
		const integrations = useSelect(
			( select ) => select( store ).getAllIntegrations(),
			[ store ]
		);
		const hasCohort = Boolean( integrations?.cohort?.is_enable );
		const canCreateCohort = true;
		const actions = useDispatch( store );
		const navigate = Router.Zp();
		const [ courseType, setCourseType ] = useState( 'self-paced' );
		const [ step, setStep ] = useState( hasCohort ? 1 : 2 );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( null );
		const creating = useRef( false );
		async function create( enableCommunity, courseTitle ) {
			if (
				creating.current ||
				( courseType === 'cohort-based' && ! canCreateCohort )
			) {
				return;
			}
			creating.current = true;
			setLoading( true );
			setError( null );
			try {
				const course = await createCourse( {
					title: courseTitle?.trim() || 'Untitled Course',
					status: 'draft',
					course_type: hasCohort ? courseType : 'self-paced',
					creation_method: '',
					isCommunityEnable: enableCommunity ? 'yes' : 'no',
				} );
				actions.setCourse( course );
				navigate( `/course-edit/${ course.id }/content` );
				onClose();
			} catch ( cause ) {
				setError(
					cause.message ||
						__(
							'Could not create course. Please try again.',
							'ohmylms'
						)
				);
			} finally {
				creating.current = false;
				setLoading( false );
			}
		}
		const badgeStyle = {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
			marginInlineEnd: '5px',
		};
		const typeLabel =
			courseType === 'self-paced'
				? __( 'Self Paced', 'ohmylms' )
				: __( 'Cohort Based', 'ohmylms' );
		const title = hasCohort ? (
			<Controls.FlexWP align="center" justify="center" gap={ 0 }>
				<Controls.ButtonWP
					onClick={ () => setStep( 1 ) }
					className={ `ohmylms-course-type-indicator ${ step === 1 ? 'ohmylms-step-active' : '' } ${ step === 2 ? 'ohmylms-step-done' : '' }` }
				>
					<Controls.BadgeWP
						isRounded
						width="30px"
						height="30px"
						style={ badgeStyle }
						variant={ step === 2 ? 'success' : 'default' }
					>
						{ step === 1 ? '1' : '✓' }
					</Controls.BadgeWP>
					{ step === 1 ? __( 'Course Type', 'ohmylms' ) : typeLabel }
				</Controls.ButtonWP>
				<Controls.ProgressBarWP
					value={ step === 1 ? 0 : 100 }
					style={ {
						width: '20px',
					} }
				/>
				<Controls.ButtonWP
					style={ {
						cursor: 'default',
					} }
					className={ `ohmylms-course-type-indicator last-step ${ step === 2 ? 'ohmylms-step-active' : '' }` }
				>
					<Controls.BadgeWP
						isRounded
						width="30px"
						height="30px"
						style={ badgeStyle }
					>
						2
					</Controls.BadgeWP>
					{ __( 'Starting content', 'ohmylms' ) }
				</Controls.ButtonWP>
			</Controls.FlexWP>
		) : (
			<Controls.TextWP as="h1" size="19px" weight="700">
				{ __( 'OhMyLMS', 'ohmylms' ) }
			</Controls.TextWP>
		);
		return (
			<Modal
				isOpen={ isOpen }
				title={ title }
				shouldCloseOnEsc
				shouldCloseOnClickOutside
				onRequestClose={ onClose }
				size="fill"
				style={ {
					maxWidth: '790px',
					background: '#FFFFFF',
				} }
				className={ `ohmylms-course-type-modal ${ hasCohort ? '' : 'ohmylms-no-cohort-type' }` }
			>
				{ error && <p role="alert">{ error }</p> }
				{ step === 1 && (
					<CourseTypes
						setCourseType={ ( type ) => {
							if ( type !== 'cohort-based' || canCreateCohort ) {
								setCourseType( type );
								setStep( 2 );
							}
						} }
					/>
				) }
				{ step === 2 && (
					<CourseBuildChoices
						loading={ loading }
						onScratch={ create }
						onCreated={ ( course ) => {
							navigate( `/course-edit/${ course.id }/content` );
							onClose();
						} }
					/>
				) }
			</Modal>
		);
	};
}
