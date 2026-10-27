/* Local filtering only: titles and categories come from the published server snapshot. */
document.querySelectorAll( '[data-directory]' ).forEach( ( directory ) => {
	const search = directory.querySelector( '[data-skill-search]' );
	if ( ! search ) return;
	const buttons = [
		...directory.querySelectorAll( 'button[data-category]' ),
	];
	let category = '';
	const update = () => {
		const query = search.value.trim().toLocaleLowerCase();
		let count = 0;
		directory.querySelectorAll( '[data-skill]' ).forEach( ( skill ) => {
			skill.hidden = Boolean(
				( category && skill.dataset.category !== category ) ||
				! skill.textContent.toLocaleLowerCase().includes( query )
			);
			if ( ! skill.hidden ) count += 1;
		} );
		directory
			.querySelectorAll( '[data-skill-group]' )
			.forEach( ( group ) => {
				group.hidden = ! [
					...group.querySelectorAll( '[data-skill]' ),
				].some( ( skill ) => ! skill.hidden );
			} );
		directory.querySelectorAll( '[data-topic]' ).forEach( ( topic ) => {
			topic.hidden = ! [
				...topic.querySelectorAll( '[data-skill-group]' ),
			].some( ( group ) => ! group.hidden );
		} );
		const results = directory.querySelector( '[data-skill-results]' );
		results.textContent = `${ count } ${ results.dataset.label }`;
		directory.querySelector( '[data-no-results]' ).hidden = count !== 0;
	};
	search.addEventListener( 'input', update );
	buttons.forEach( ( button ) =>
		button.addEventListener( 'click', () => {
			category = button.dataset.category;
			buttons.forEach( ( entry ) =>
				entry.setAttribute( 'aria-pressed', String( entry === button ) )
			);
			update();
		} )
	);
} );
