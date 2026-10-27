import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { MovableTabs } from '../navigation/MovableTabs';

export function PeopleTabs( {
	active = 'students',
	studentsUrl,
	canListUsers = true,
	onChange,
	movable = false,
	withHome = false,
} ) {
	const studentUrl =
		studentsUrl ||
		`admin.php?page=${ new URLSearchParams( window.location.search ).get( 'page' ) || 'ohmylms' }#/accounthub`;
	// The first tab is the bare Account Hub address; every other tab adds ?tab=.
	const first = withHome ? 'home' : 'students';
	const sections = [
		...( withHome ? [ [ 'home', __( 'Home', 'ohmylms' ) ] ] : [] ),
		[ 'students', __( 'Students', 'ohmylms' ) ],
		...( canListUsers
			? [
					[ 'teachers', __( 'Teachers', 'ohmylms' ) ],
					[ 'parents', __( 'Parents', 'ohmylms' ) ],
					[ 'users', __( 'All accounts', 'ohmylms' ) ],
				]
			: [] ),
		[ 'classes', __( 'Classes', 'ohmylms' ) ],
		[ 'schools', __( 'Schools', 'ohmylms' ) ],
	];
	if ( ! movable ) {
		return (
			<nav
				className="ohmylms-people-tabs"
				aria-label={ __( 'Students and schools', 'ohmylms' ) }
			>
				{ sections.map( ( [ id, text ] ) => (
					<a
						key={ id }
						href={
							id === first
								? studentUrl
								: `${ studentUrl }?tab=${ id }`
						}
						aria-current={ active === id ? 'page' : undefined }
						onClick={
							onChange
								? ( event ) => {
										event.preventDefault();
										onChange( id );
									}
								: undefined
						}
					>
						{ text }
					</a>
				) ) }
			</nav>
		);
	}
	return (
		<MovableTabs
			scope="account-hub"
			className="ohmylms-people-tabs"
			active={ active }
			onChange={ onChange }
			label={ __( 'Students and schools', 'ohmylms' ) }
			tabs={ sections.map( ( [ id ] ) => ( {
				id,
				href: id === first ? studentUrl : `${ studentUrl }?tab=${ id }`,
			} ) ) }
			labels={ Object.fromEntries( sections ) }
		/>
	);
}
