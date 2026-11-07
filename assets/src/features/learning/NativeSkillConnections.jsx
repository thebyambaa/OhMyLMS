import { createElement } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Keep skill connections reachable from lesson, quiz and question block editors.
 *
 * @return {React.ReactElement|null} Document sidebar panel for saved LMS content.
 */
function NativeSkillConnections() {
	const { type, id } = useSelect(
		( select ) => ( {
			type: select( 'core/editor' ).getCurrentPostType(),
			id: select( 'core/editor' ).getCurrentPostId(),
		} ),
		[]
	);
	const kind = {
		'ohmylms-lesson': 'lesson',
		'ohmylms-quiz': 'quiz',
		'ohmylms-question': 'question',
	}[ type ];
	// A quiz chooses its skills in its own Skills panel, and each question in its block sidebar.
	if ( ! kind || ! id || 'quiz' === kind ) {
		return null;
	}
	const url = new URL( 'admin.php', window.location.href );
	url.searchParams.set( 'page', 'ohmylms-content' );
	url.searchParams.set( 'tab', 'skills' );
	url.searchParams.set( 'section', 'content-links' );
	url.searchParams.set( 'kind', kind );
	url.searchParams.set( 'content', String( id ) );
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-skill-connections"
			title={ __( 'Skills and curriculum', 'ohmylms' ) }
		>
			<p>
				{ 'lesson' === kind
					? __( 'Connect the skills this lesson teaches.', 'ohmylms' )
					: __(
							'Connect skill coverage. Question primary skills determine mastery evidence.',
							'ohmylms'
						) }
			</p>
			<Button
				variant="secondary"
				href={ url.href }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __(
					'Manage skill connections (opens in a new tab)',
					'ohmylms'
				) }
			</Button>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'ohmylms-native-skill-connections', {
	render: NativeSkillConnections,
} );
