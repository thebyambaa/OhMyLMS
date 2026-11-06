import { __, sprintf } from '@wordpress/i18n';
import { Badge } from '../../ui/Badge';
import { Button } from '../../ui/Button';
import { IconButton } from '../../ui/IconButton';
import { type Question, TYPES, studioType } from './quiz-model';

/**
 * Left rail: every question in order, the current one marked, with order controls and Add question.
 *
 * @param props          Props.
 * @param props.items    Questions.
 * @param props.current  Index of the question on the canvas.
 * @param props.canEdit  Whether the person may add and reorder.
 * @param props.onSelect Choose a question.
 * @param props.onMove   Move a question by -1 or +1.
 * @param props.onAdd    Add a question.
 */
export function QuizRail( {
	items,
	current,
	canEdit,
	onSelect,
	onMove,
	onAdd,
}: {
	items: Question[];
	current: number;
	canEdit: boolean;
	onSelect: ( index: number ) => void;
	onMove: ( index: number, direction: number ) => void;
	onAdd: () => void;
} ) {
	const label = ( type: string ) =>
		TYPES.find( ( row ) => row[ 0 ] === studioType( type ) )?.[ 1 ] || type;
	return (
		<nav
			className="oml-qe-rail"
			aria-label={ __( 'Questions', 'ohmylms' ) }
		>
			<h2>
				{ sprintf(
					/* translators: %d: number of questions. */
					__( 'Questions (%d)', 'ohmylms' ),
					items.length
				) }
			</h2>
			<ol>
				{ items.map( ( question, index ) => (
					<li
						key={ question.id || `new-${ index }` }
						className={
							index === current
								? 'oml-qe-item is-current'
								: 'oml-qe-item'
						}
					>
						<button
							type="button"
							className="oml-qe-pick"
							aria-current={
								index === current ? 'true' : undefined
							}
							onClick={ () => onSelect( index ) }
						>
							<span className="oml-qe-num">{ index + 1 }</span>
							<span className="oml-qe-snippet">
								{ question.prompt ||
									__( 'New question', 'ohmylms' ) }
							</span>
							<Badge>{ label( question.type ) }</Badge>
						</button>
						{ canEdit && index === current && (
							<span className="oml-qe-order">
								<IconButton
									label={ __(
										'Move question up',
										'ohmylms'
									) }
									disabled={ index === 0 }
									onClick={ () => onMove( index, -1 ) }
								>
									↑
								</IconButton>
								<IconButton
									label={ __(
										'Move question down',
										'ohmylms'
									) }
									disabled={ index === items.length - 1 }
									onClick={ () => onMove( index, 1 ) }
								>
									↓
								</IconButton>
							</span>
						) }
					</li>
				) ) }
			</ol>
			{ canEdit && (
				<Button variant="primary" block onClick={ onAdd }>
					{ __( 'Add question', 'ohmylms' ) }
				</Button>
			) }
		</nav>
	);
}
