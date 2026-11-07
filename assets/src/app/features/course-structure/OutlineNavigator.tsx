import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Outline } from './model';
import type { ConnectionTarget } from './CourseConnections';
import { unitKey } from './reorder';

/** Width from which the navigator is a side rail; the stylesheet uses the same breakpoint. */
const RAIL_QUERY = '(min-width: 1441px)';

/** Whether the outline overview starts open: always as a side rail, folded away on narrower pages. */
function startsOpen() {
	return (
		'function' === typeof window.matchMedia &&
		window.matchMedia( RAIL_QUERY ).matches
	);
}

/**
 * Compact outline navigation stays separate from placement transport and editing.
 * @param root0
 * @param root0.draft
 * @param root0.onNavigate
 * @param root0.onConnect
 */
export function OutlineNavigator( {
	draft,
	onNavigate,
	onConnect,
}: {
	draft: Outline;
	onNavigate: ( key: string ) => void;
	onConnect: ( target: ConnectionTarget ) => void;
} ) {
	const [ open, setOpen ] = useState( startsOpen );
	return (
		<nav
			className="oml-outline-navigation"
			aria-label={ __( 'Course outline navigation', 'ohmylms' ) }
		>
			<details
				open={ open }
				onToggle={ ( event ) => setOpen( event.currentTarget.open ) }
			>
				<summary>
					<h2>{ __( 'Course outline', 'ohmylms' ) }</h2>
					<span className="oml-outline-navigation-count">
						{ sprintf(
							/* translators: %d: number of units. */
							_n(
								'%d unit',
								'%d units',
								draft.units.length,
								'ohmylms'
							),
							draft.units.length
						) }
					</span>
				</summary>
				<Button
					variant="secondary"
					onClick={ () =>
						onConnect( { kind: 'course', id: draft.course_id } )
					}
				>
					{ __( 'Connect course skills', 'ohmylms' ) }
				</Button>
				<ol>
					{ draft.units.map( ( unit ) => (
						<li key={ unit.id }>
							<Button
								variant="tertiary"
								onClick={ () =>
									onNavigate( unitKey( unit.id ) )
								}
							>
								{ unit.title || __( 'New unit', 'ohmylms' ) }
							</Button>
							<ul>
								{ draft.program.items
									.filter(
										( item ) => item.chapter_id === unit.id
									)
									.map( ( item ) => (
										<li key={ item.id }>
											<Button
												variant="link"
												onClick={ () =>
													onNavigate( item.id )
												}
												aria-label={ sprintf(
													/* translators: %s: activity title. */ __(
														'Go to %s',
														'ohmylms'
													),
													item.name ||
														String(
															item.content_id
														)
												) }
											>
												{ item.name ||
													String( item.content_id ) }
											</Button>
										</li>
									) ) }
							</ul>
						</li>
					) ) }
				</ol>
				{ ! draft.units.length && (
					<p>
						{ __(
							'Add your first unit to start building this course.',
							'ohmylms'
						) }
					</p>
				) }
			</details>
		</nav>
	);
}
