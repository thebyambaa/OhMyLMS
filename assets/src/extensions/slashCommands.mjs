/** Extra items for the lesson editor's "/" menu, offered in an "Interactive" group. */
export const SLASH_GROUP = Object.freeze( {
	name: 'ohmylms-extensions',
	title: 'Interactive',
} );

export function slashGroups( registry ) {
	const commands = registry.list( 'slash-command' ).map( ( entry ) => ( {
		name: entry.id,
		label: entry.label,
		iconName: entry.iconName || 'Eye',
		description: entry.description || '',
		aliases: entry.aliases || [],
		action: entry.action,
	} ) );
	return commands.length ? [ { ...SLASH_GROUP, commands } ] : [];
}

export function registerBuiltinSlashCommands( registry ) {
	registry.registerSlashCommand( 'reveal-card', {
		label: 'Reveal card',
		description: 'A prompt with an answer students can show or hide',
		aliases: [ 'reveal', 'answer', 'flip' ],
		iconName: 'Eye',
		priority: 10,
		action: ( editor ) =>
			editor
				.chain()
				.focus()
				.insertContent(
					'<p>[ohmylms_activity type="reveal" prompt="Write the question or prompt here" answer="Write the answer here"]</p>'
				)
				.run(),
	} );
}
