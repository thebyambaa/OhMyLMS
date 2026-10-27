import { createElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { CourseLearning } from './CourseLearning';

export function createCourseSettings( readRuntime ) {
	return function CourseSettings() {
		const {
			I: Controls,
			T: { default: store },
			f: Router,
			Lz: Basics,
			SH: Cohort,
			wV: Resources,
			iH: Organization,
			OH: Engagement,
		} = readRuntime();
		const { id, step = 'settings', subStep = 'basics' } = Router.g();
		const navigate = Router.Zp();
		const course = useSelect(
			( select ) => select( store ).getCourse(),
			[ store ]
		);
		const tabs = [
			{
				label: __( 'Basics', 'ohmylms' ),
				key: 'basics',
				children: <Basics />,
			},
			{
				label: __( 'Learning', 'ohmylms' ),
				key: 'learning',
				children: <CourseLearning courseId={ id } />,
			},
			...( course?.type === 'cohort-based'
				? [
						{
							label: __( 'Cohort Settings', 'ohmylms' ),
							key: 'cohort',
							children: <Cohort />,
						},
					]
				: [] ),
			{
				label: __( 'Resources', 'ohmylms' ),
				key: 'resources',
				children: <Resources />,
			},
			{
				label: __( 'Organize', 'ohmylms' ),
				key: 'organize',
				children: <Organization />,
			},
			{
				label: __( 'Engagement', 'ohmylms' ),
				key: 'engagement',
				children: <Engagement />,
			},
		];
		return (
			<Controls.ContainerWP>
				<Controls.SpacerWP marginBottom={ 0 } paddingY={ 10 }>
					<Controls.CardWP
						variant="secondary"
						isBorderless
						minHeight="calc(100vh - 200px)"
					>
						<Controls.SpacerWP padding={ 10 } marginBottom={ 0 }>
							<Controls.TabsWP
								items={ tabs }
								activekey={ subStep }
								onChange={ ( next ) =>
									navigate(
										`/course-edit/${ id }/${ step }/${ next }`
									)
								}
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.SpacerWP>
			</Controls.ContainerWP>
		);
	};
}
