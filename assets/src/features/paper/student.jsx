/** @jsx createElement */
import { createElement, createRoot, flushSync } from '@wordpress/element';
import { Items, annotate } from './paperDocument';
import { paperSettings, paperVersion } from './paperModel.mjs';

/*
 * Paper View for students: the same paper document the editor and the print page show, as one
 * continuous sheet, answered in place.
 *
 * The player's question boxes keep their answer fields, autosave and validation. Each box moves
 * into its question's place on the sheet and shows the printed question; choice options are
 * answered by their printed letters, and other answer types keep their own controls. This runs
 * before the player hydrates, so the player starts from the finished sheet.
 */

const normalize = ( text ) => ( text || '' ).replace( /\s+/g, ' ' ).trim();

/**
 * Answer a printed choice question through the player's own radios or checkboxes.
 *
 * @param {Element} box     Player question box.
 * @param {Element} printed Printed question.
 * @return {boolean} Whether every printed option found its answer field.
 */
function bindChoices( box, printed ) {
	const rows = [
		...printed.querySelectorAll( '.ohmylms-print-options > li' ),
	];
	const fields = [
		...box.querySelectorAll(
			'.quiz-checkbox-radio-options .single-option'
		),
	].map( ( option ) => ( {
		text: normalize( option.querySelector( '.option-title' )?.textContent ),
		input: option.querySelector( 'input' ),
	} ) );
	if ( ! rows.length || rows.length !== fields.length ) {
		return false;
	}
	const pairs = [];
	for ( const row of rows ) {
		const text = normalize(
			row.querySelector( '.ohmylms-print-option' )?.textContent
		);
		const index = fields.findIndex( ( field ) => field.text === text );
		if ( index < 0 || ! fields[ index ].input ) {
			return false;
		}
		pairs.push( [ row, fields.splice( index, 1 )[ 0 ].input ] );
	}
	for ( const [ row, input ] of pairs ) {
		const letter = normalize(
			row.querySelector( '.ohmylms-print-letter' )?.textContent
		);
		input.classList.add( 'ohmylms-paper-choice-input' );
		input.setAttribute(
			'aria-label',
			`${ letter }. ${ normalize(
				row.querySelector( '.ohmylms-print-option' )?.textContent
			) }`
		);
		row.classList.add( 'ohmylms-paper-choice' );
		row.prepend( input );
		row.addEventListener( 'click', ( event ) => {
			if ( event.target !== input ) {
				input.click();
			}
		} );
	}
	box.classList.add( 'has-paper-choices' );
	return true;
}

/**
 * Show a player question box as its printed question.
 *
 * @param {Element} box     Player question box.
 * @param {Element} printed Printed question.
 */
function present( box, printed ) {
	box.classList.add( 'is-paper-question' );
	box.prepend( printed );
	if (
		printed.classList.contains( 'is-choice' ) &&
		bindChoices( box, printed )
	) {
		return;
	}
	// Other answer types keep the player's controls in place of the printed answer space.
	const head = printed.querySelector( '.ohmylms-print-head' );
	[ ...printed.children ]
		.filter( ( child ) => child !== head )
		.forEach( ( child ) => child.remove() );
	const statement = box.querySelector( '.question-box' );
	// Blanks typed into the statement need the player's statement, fields and all.
	if (
		statement?.querySelector(
			'.the-question input, .the-question select, .the-question textarea'
		)
	) {
		const prompt = head?.querySelector( '.ohmylms-print-prompt' );
		if ( prompt ) {
			prompt.replaceChildren( statement );
		}
		box.classList.add( 'has-player-statement' );
	}
}

/**
 * Build the sheet and move the player's question boxes onto it.
 *
 * @param {Element} section The player's quiz section.
 */
function mount( section ) {
	const source = section.querySelector( '.ohmylms-paper-student-data' );
	const form = section.querySelector( '.ohmylms-quiz-form' );
	if ( ! source || ! form ) {
		return;
	}
	const data = JSON.parse( source.textContent );
	const settings = paperSettings( data.settings );
	const version = paperVersion( 'A', settings );
	const context = {
		data,
		settings,
		version,
		showAnswers: false,
		continuous: true,
		derived: annotate( data.items, settings ),
		wrapQuestion: ( item, question ) => (
			<div
				className="ohmylms-paper-slot"
				data-question-id={ item.question.id }
			>
				{ question }
			</div>
		),
	};
	const page = document.createElement( 'div' );
	page.className = `ohmylms-print-page ohmylms-paper-student${
		settings.font === 'serif' ? ' is-serif' : ''
	}`;
	page.style.setProperty(
		'--ohmylms-paper-font-size',
		`${ settings.font_size }pt`
	);
	page.style.setProperty(
		'--ohmylms-paper-margin',
		`${ settings.margin }mm`
	);
	page.style.setProperty(
		'--ohmylms-paper-width',
		settings.size === 'letter' ? '215.9mm' : '210mm'
	);
	form.prepend( page );
	flushSync( () =>
		createRoot( page ).render(
			<div className="ohmylms-print-sheet" data-ohmylms-latex>
				<Items items={ data.items } context={ context } />
			</div>
		)
	);
	const sheet = page.firstElementChild;
	const boxes = new Map(
		[
			...form.querySelectorAll( '.ohmylms-quiz-box[data-question-id]' ),
		].map( ( box ) => [ box.dataset.questionId, box ] )
	);
	sheet.querySelectorAll( '.ohmylms-paper-slot' ).forEach( ( slot ) => {
		const box = boxes.get( slot.dataset.questionId );
		const printed = slot.querySelector( '.ohmylms-print-question' );
		if ( ! box || ! printed ) {
			// Not in this attempt: leave no empty place on the sheet.
			( slot.closest( '.ohmylms-print-block' ) || slot ).remove();
			return;
		}
		boxes.delete( slot.dataset.questionId );
		slot.append( box );
		present( box, printed );
	} );
	// Questions of the attempt that the paper does not place come last, as the player shows them.
	boxes.forEach( ( box ) => {
		const block = document.createElement( 'div' );
		block.className = 'ohmylms-print-block';
		block.append( box );
		sheet.append( block );
	} );
	section.classList.add( 'has-paper-sheet' );
}

document
	.querySelectorAll( '.ohmylms-quiz.ohmylms-paper-form' )
	.forEach( ( section ) => {
		try {
			mount( section );
		} catch {
			// Before any question moved, the player's own one-page list stays usable.
			const page = section.querySelector( '.ohmylms-paper-student' );
			if ( page && ! page.querySelector( '.ohmylms-quiz-box' ) ) {
				page.remove();
			}
		}
	} );
