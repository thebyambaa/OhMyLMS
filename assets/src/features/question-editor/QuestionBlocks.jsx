import {
	createContext,
	createElement,
	useContext,
	useState,
} from '@wordpress/element';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	BlockControls,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { QUESTION_BLOCK_TYPES, questionBlockName } from './questionBlocks.mjs';
import { QuestionLivePreview } from './QuestionLivePreview';
export const QuestionBlockContext = createContext( null );
function AnswerBlock( { type, label } ) {
	const context = useContext( QuestionBlockContext );
	const [ preview, setPreview ] = useState( false );
	const blockProps = useBlockProps( {
		className: 'ohmylms-question-answer-block',
	} );
	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ preview ? 'edit' : 'visibility' }
						label={
							preview
								? __( 'Edit question', 'ohmylms' )
								: __( 'Preview question', 'ohmylms' )
						}
						isPressed={ preview }
						onClick={ () => setPreview( ! preview ) }
					/>
				</ToolbarGroup>
			</BlockControls>
			<div className="ohmylms-question-block-heading">
				<h3>{ label }</h3>
				<Button
					variant="secondary"
					size="small"
					onClick={ () => setPreview( ! preview ) }
				>
					{ preview
						? __( 'Edit question', 'ohmylms' )
						: __( 'Preview question', 'ohmylms' ) }
				</Button>
			</div>
			{ ( () => {
				if ( context?.type === type ) {
					if ( preview ) {
						return (
							<QuestionLivePreview question={ context.preview } />
						);
					}
					return context.content;
				}
				return (
					<p>
						{ __(
							'Select this question type to configure its answers.',
							'ohmylms'
						) }
					</p>
				);
			} )() }
			{ context?.type === type && (
				<InspectorControls>
					<PanelBody title={ __( 'Question settings', 'ohmylms' ) }>
						{ context.settings }
					</PanelBody>
				</InspectorControls>
			) }
		</div>
	);
}
export function registerQuestionBlocks() {
	for ( const [ type, label ] of QUESTION_BLOCK_TYPES ) {
		const name = questionBlockName( type );
		if ( getBlockType( name ) ) {
			continue;
		}
		registerBlockType( name, {
			apiVersion: 2,
			title: label,
			category: 'text',
			icon: 'welcome-learn-more',
			description: __(
				'An assessment question with answers managed by OhMyLMS.',
				'ohmylms'
			),
			attributes: {
				questionId: {
					type: 'string',
					default: '',
				},
			},
			supports: {
				html: false,
				multiple: false,
				reusable: false,
				lock: false,
			},
			edit: () => <AnswerBlock type={ type } label={ label } />,
			// Assessment data is saved through its existing versioned writer, never in HTML attributes.
			save: () => null,
		} );
	}
}
