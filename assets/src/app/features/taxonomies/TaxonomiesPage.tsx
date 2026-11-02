import { useListQuery } from '../../components/listQuery';
import { getTaxonomies } from './model';
import { TaxonomyList } from './TaxonomyList';

const DEFAULTS = { taxonomy: 'categories', s: '' };

/**
 * The `/taxonomies` route (Curriculum › Categories and tags): one read-only list per legacy taxonomy,
 * switched by the list's `.subsubsub` views (`&taxonomy=tags`), as WordPress switches a list's views.
 * The curriculum and course bundles screens own the data.
 */
export default function TaxonomiesPage() {
	const taxonomies = getTaxonomies();
	const query = useListQuery( DEFAULTS );
	const info =
		taxonomies.find(
			( candidate ) => candidate.kind === query.values.taxonomy
		) ?? taxonomies[ 0 ];
	if ( ! info ) {
		return null;
	}
	return (
		<TaxonomyList
			key={ info.kind }
			info={ info }
			views={ taxonomies.map( ( candidate ) => ( {
				key: candidate.kind,
				label: candidate.title,
				current: candidate.kind === info.kind,
				href: query.href( { taxonomy: candidate.kind, s: '' } ),
				onSelect: () =>
					query.set( { taxonomy: candidate.kind, s: '' } ),
			} ) ) }
		/>
	);
}
