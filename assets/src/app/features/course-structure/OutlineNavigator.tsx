import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { Outline } from './model';
import type { ConnectionTarget } from './CourseConnections';
import { unitKey } from './reorder';

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
	return (
		<nav
			className="oml-outline-navigation"
			aria-label={ __( 'Course outline navigation', 'ohmylms' ) }
		>
			<h2>{ __( 'Course outline', 'ohmylms' ) }</h2>
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
							onClick={ () => onNavigate( unitKey( unit.id ) ) }
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
													String( item.content_id )
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
		</nav>
	);
}
