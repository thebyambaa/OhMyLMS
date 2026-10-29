import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getTaxonomies } from './model';
import { TaxonomyList } from './TaxonomyList';

/**
 * The `/taxonomies` route: one tab per legacy taxonomy. Both lists are read-only views; the
 * curriculum and course bundles screens own the data.
 */
export default function TaxonomiesPage() {
	const taxonomies = getTaxonomies();
	return (
		<>
			<h2>{ __( 'Categories and tags', 'ohmylms' ) }</h2>
			<TabPanel
				className="ohmylms-taxonomy-tabs"
				tabs={ taxonomies.map( ( { kind, title } ) => ( {
					name: kind,
					title,
				} ) ) }
			>
				{ ( tab ) => {
					const info = taxonomies.find(
						( candidate ) => candidate.kind === tab.name
					);
					return info ? <TaxonomyList info={ info } /> : null;
				} }
			</TabPanel>
		</>
	);
}
