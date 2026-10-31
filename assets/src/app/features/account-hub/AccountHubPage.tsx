import { __ } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { useAdminPlace } from '../../routing/place';
import { ClassesTab } from './ClassesTab';
import { tabFromParam } from './model';
import { ParentsTab } from './ParentsTab';
import { SchoolsTab } from './SchoolsTab';
import { Tabs, useTabPanelProps } from './Tabs';
import { TeachersTab } from './TeachersTab';
import { TAB_IDS, type TabId } from './types';
import { UsersTab } from './UsersTab';

const LABELS: Record< TabId, string > = {
	schools: __( 'Schools', 'ohmylms' ),
	classes: __( 'Classes', 'ohmylms' ),
	teachers: __( 'Teachers', 'ohmylms' ),
	parents: __( 'Parents', 'ohmylms' ),
	users: __( 'All accounts', 'ohmylms' ),
};

const PANELS: Record< TabId, () => JSX.Element > = {
	schools: SchoolsTab,
	classes: ClassesTab,
	teachers: TeachersTab,
	parents: ParentsTab,
	users: UsersTab,
};

/**
 * Account Hub (`page=ohmylms-accounts`): the server prints the tabs, Students among them; the tab is
 * the route's `tab` query parameter and each tab loads when it is opened. Outside the admin pages the
 * in-page tab list switches tabs and a link leads to the students screen.
 */
export default function AccountHubPage() {
	const [ params, setParams ] = useSearchParams();
	const selected = tabFromParam( params.get( 'tab' ) );
	const Panel = PANELS[ selected ];
	const panelProps = useTabPanelProps();
	const place = useAdminPlace();

	return (
		<>
			{ ! place && (
				<p>
					{ __(
						'Schools, classes, teachers, parents and all accounts. Student enrolments are managed on the Students screen:',
						'ohmylms'
					) }{ ' ' }
					<Link to="/students">
						{ __( 'Open students', 'ohmylms' ) }
					</Link>
				</p>
			) }
			<Tabs
				label={ __( 'Account Hub sections', 'ohmylms' ) }
				tabs={ TAB_IDS.map( ( id ) => ( {
					id,
					label: LABELS[ id ],
				} ) ) }
				selected={ selected }
				onSelect={ ( id ) =>
					setParams( { tab: id }, { replace: true } )
				}
			/>
			<div { ...panelProps( selected, selected ) }>
				<Panel key={ selected } />
			</div>
		</>
	);
}
