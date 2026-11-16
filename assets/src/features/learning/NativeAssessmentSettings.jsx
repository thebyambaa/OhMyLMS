/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { dispatch, useSelect } from '@wordpress/data';
import { RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Assessment rules in the quiz's `ohmylms_quiz` attribute: `kind` and the other keys of
 * includes/Assessment/AssessmentSettings.php.
 *
 * @typedef {{ kind?: string, accommodations?: Record<string, number>, [ key: string ]: unknown }} NativeAssessmentRules
 */

/**
 * Share assessment rules through the native document's save transaction.
 *
 * @param {boolean} enabled Whether the native editor is in use.
 * @return {{settings: NativeAssessmentRules|undefined, setSettings: (assessment: NativeAssessmentRules) => void}} Current rules and their update action.
 */
export function useNativeAssessmentSettings( enabled = true ) {
	const value = useSelect(
		( getStore ) =>
			enabled
				? getStore( 'core/editor' ).getEditedPostAttribute(
						'ohmylms_quiz'
					)
				: null,
		[ enabled ]
	);
	return {
		settings: value?.assessment,
		setSettings: ( assessment ) =>
			dispatch( 'core/editor' ).editPost( {
				ohmylms_quiz: { ...value, assessment },
			} ),
	};
}

/**
 * Choose how the shared assessment is delivered without resetting its rules.
 *
 * @return {React.ReactElement|null} Assessment type control.
 */
export function NativeAssessmentType() {
	const { settings, setSettings } = useNativeAssessmentSettings();
	if ( ! settings ) {
		return null;
	}
	const linkedExam = Boolean(
		window.ohmylmsNativeLesson?.profile?.panel === 'exam' &&
		window.ohmylmsNativeLesson?.profile?.data?.id
	);
	const options = [
		{ value: 'quiz', label: __( 'Quiz', 'ohmylms' ) },
		{ value: 'exam', label: __( 'Exam', 'ohmylms' ) },
	];
	/** @type {string} */
	let help = __(
		'Use the same questions as a quiz or an exam. Review the settings below before saving.',
		'ohmylms'
	);
	if ( linkedExam ) {
		help = __( 'This assessment is linked to an exam.', 'ohmylms' );
	} else if ( 'practice' === settings.kind ) {
		help = __(
			'This assessment retains its saved practice rules. Choose Quiz or Exam to change its type.',
			'ohmylms'
		);
	}
	return (
		<RadioControl
			label={ __( 'Assessment type', 'ohmylms' ) }
			selected={ settings.kind }
			options={ options }
			disabled={ linkedExam }
			help={ help }
			onChange={ ( kind ) => setSettings( { ...settings, kind } ) }
		/>
	);
}
