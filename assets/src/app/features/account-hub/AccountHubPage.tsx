import { __ } from '@wordpress/i18n';
import { Link, useSearchParams } from 'react-router-dom';
import { ClassesTab } from './ClassesTab';
import { tabFromParam } from './model';
import { ParentsTab } from './ParentsTab';
import { SchoolsTab } from './SchoolsTab';
import { Tabs, tabId, tabPanelId } from './Tabs';
import { TeachersTab } from './TeachersTab';
import { TAB_IDS, type TabId } from './types';

const LABELS: Record< TabId, string > = {
	schools: __( 'Schools', 'ohmylms' ),
	classes: __( 'Classes', 'ohmylms' ),
	teachers: __( 'Teachers', 'ohmylms' ),
	parents: __( 'Parents', 'ohmylms' ),
};

const PANELS: Record< TabId, () => JSX.Element > = {
	schools: SchoolsTab,
	classes: ClassesTab,
	teachers: TeachersTab,
	parents: ParentsTab,
};

/**
 * Account Hub at `#/account-hub`. The tab is the `tab` query parameter
 * (`#/account-hub?tab=classes`); each tab loads when it is opened. Students have their own screen.
 */
export default function AccountHubPage() {
	const [ params, setParams ] = useSearchParams();
	const selected = tabFromParam( params.get( 'tab' ) );
	const Panel = PANELS[ selected ];

	return (
		<>
			<h2>{ __( 'Account Hub', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Schools, classes, teachers and parents. Student accounts are managed on the Students screen:',
					'ohmylms'
				) }{ ' ' }
				<Link to="/students">{ __( 'Open students', 'ohmylms' ) }</Link>
			</p>
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
			<div
				role="tabpanel"
				id={ tabPanelId( selected ) }
				aria-labelledby={ tabId( selected ) }
			>
				<Panel key={ selected } />
			</div>
		</>
	);
}
