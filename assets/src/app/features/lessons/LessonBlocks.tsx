import {
	BlockEditorProvider,
	BlockList,
	BlockInspector,
	BlockToolbar,
	BlockTools,
	WritingFlow,
	Inserter,
} from '@wordpress/block-editor';
import {
	getBlockType,
	parse,
	serialize,
	type BlockInstance,
} from '@wordpress/blocks';
import { registerCoreBlocks } from '@wordpress/block-library';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * The installed WordPress owns parsing, serialization, rich text and block behavior.
 * @param root0
 * @param root0.initial
 * @param root0.onChange
 * @param root0.disabled
 */
export default function LessonBlocks( {
	initial,
	onChange,
	disabled,
}: {
	initial: string;
	onChange: ( content: string ) => void;
	disabled: boolean;
} ) {
	const [ blocks, setBlocks ] = useState( () => {
		if ( ! getBlockType( 'core/paragraph' ) ) {
			registerCoreBlocks();
		}
		return parse( initial );
	} );
	function update( next: BlockInstance[] ) {
		if ( disabled ) {
			return;
		}
		setBlocks( next );
		onChange( serialize( next ) );
	}
	return (
		<div className="ohmylms-lesson-blocks">
			<BlockEditorProvider
				value={ blocks }
				onChange={ update }
				onInput={ update }
				settings={ {
					hasFixedToolbar: true,
					allowedBlockTypes: [
						'core/paragraph',
						'core/heading',
						'core/list',
						'core/list-item',
						'core/image',
						'core/file',
						'core/video',
						'core/audio',
						'core/quote',
						'core/code',
						'core/separator',
						'core/table',
						'core/html',
					],
				} }
			>
				<div
					className="ohmylms-lesson-blocks-toolbar"
					aria-label={ __( 'Block tools', 'ohmylms' ) }
				>
					<Inserter />
					<BlockToolbar />
				</div>
				<div className="ohmylms-lesson-blocks-layout">
					<div className="editor-styles-wrapper">
						<BlockTools>
							<WritingFlow>
								<BlockList />
							</WritingFlow>
						</BlockTools>
					</div>
					<aside aria-label={ __( 'Block settings', 'ohmylms' ) }>
						<BlockInspector />
					</aside>
				</div>
			</BlockEditorProvider>
		</div>
	);
}
