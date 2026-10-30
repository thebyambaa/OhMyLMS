import { BankAnswerFields } from '../question-editor/BankAnswerFields';
import { registerCoreQuestionEditors } from '../question-editor/registerCoreEditors';
import { __ } from '@wordpress/i18n';

/**
 * Core admin pages, registered through the public extension registry before app bootstrap.
 * @param registry
 * @param components
 */
export function registerQuestionBankPages( registry, components ) {
	const {
		QuestionBankPage,
		SkillsPage,
		PerformancePage,
		AssessmentSettingsPanel,
		InlineCheckPanel,
	} = components;
	const flags = window.ohmylmsAssessment || {};
	const enabled = ( value, fallback = true ) =>
		value === undefined
			? fallback
			: value === true || value === 1 || value === '1';
	if ( ! enabled( flags.bankUi ) ) {
		return;
	}
	registry.registerAdminPage( 'question-bank', {
		label: __( 'Question bank', 'ohmylms' ),
		render: QuestionBankPage,
		priority: 1,
	} );
	registry.registerEditorPanel( 'assessment-settings', {
		label: __( 'Assessment settings', 'ohmylms' ),
		slot: '/quiz-edit/:id',
		render: AssessmentSettingsPanel,
		priority: 1,
	} );
	if ( enabled( flags.practice ) ) {
		registry.registerEditorPanel( 'inline-question-checks', {
			label: __( 'Inline question checks', 'ohmylms' ),
			slot: '/lesson-edit/:id',
			render: InlineCheckPanel,
			priority: 1,
		} );
	}
	registerCoreQuestionEditors( registry, {
		...components,
		BankAnswerFields,
	} );
	registry.registerAdminPage( 'performance', {
		label: __( 'Skill performance', 'ohmylms' ),
		render: PerformancePage,
		priority: 1,
	} );
	registry.registerAdminPage( 'skills', {
		label: __( 'Skills', 'ohmylms' ),
		render: SkillsPage,
		priority: 1,
	} );
}
