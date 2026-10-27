/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Notice, Spinner } from '@wordpress/components';
import { SyllabusWorkspace } from './SyllabusWorkspace';
import { loadTree } from './api.mjs';

/** Keep existing comparison bookmarks working through the syllabus editor. */
export function ComparisonWorkspace() {
	const roadmapId =
		Number(
			new URLSearchParams(
				window.location.hash.split( '?' )[ 1 ] || ''
			).get( 'roadmap' )
		) || 0;
	return roadmapId > 0 ? (
		<SyllabusWorkspace key={ roadmapId } syllabusId={ roadmapId } />
	) : (
		<RoadmapPicker />
	);
}

function RoadmapPicker() {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		let current = true;
		loadTree()
			.then(
				( response ) =>
					current &&
					setItems(
						response.items.filter(
							( item ) => item.item_type === 'roadmap'
						)
					)
			)
			.catch( ( cause ) => current && setError( cause.message ) );
		return () => {
			current = false;
		};
	}, [] );
	return (
		<section className="ohmylms-ws ohmylms-ws-fallback">
			<h1>{ __( 'Shared skills', 'ohmylms' ) }</h1>
			<p>
				{ __(
					'Open a roadmap, then use Open visual editor beside Skills to edit its connections. Manage source syllabuses on the roadmap home.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! items && ! error && <Spinner /> }
			{ items && (
				<ul>
					{ items.map( ( item ) => (
						<li key={ item.id }>
							<Button
								variant="link"
								href={ `#/content-hub/curriculum/syllabus/${ item.id }` }
							>
								{ item.name }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<Button variant="secondary" href="#/content-hub/curriculum">
				{ __( 'Open curriculum', 'ohmylms' ) }
			</Button>
		</section>
	);
}
