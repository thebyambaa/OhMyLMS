import { __ } from '@wordpress/i18n';
import { useLocation } from 'react-router-dom';
import { AiRunsPanel } from './AiRunsPanel';
import { ContentLinksPanel } from './ContentLinksPanel';
import { MappingsPanel } from './MappingsPanel';
import { PlacementPanel } from './PlacementPanel';
import { ProvenancePanel } from './ProvenancePanel';
import { SkillQuizzesPanel } from './SkillQuizzesPanel';
import { WORKSPACE_SECTIONS, type WorkspaceSection } from './workspace';

function Section( { section }: { section: WorkspaceSection } ) {
	switch ( section ) {
		case 'mappings':
			return <MappingsPanel />;
		case 'ai-runs':
			return <AiRunsPanel />;
		case 'content-links':
			return <ContentLinksPanel />;
		case 'placement':
			return <PlacementPanel />;
		case 'skill-quizzes':
			return <SkillQuizzesPanel />;
		case 'provenance':
			return <ProvenancePanel />;
	}
}

/**
 * The skill workspace sections of Content Hub › Skills (`/content-hub/skills/<section>`, one chunk):
 * combined mappings, AI runs, shared content links, domain placement, skill-owned quizzes and
 * provenance review. The section is the last segment of the route.
 */
export default function SkillWorkspace() {
	const { pathname } = useLocation();
	const section =
		WORKSPACE_SECTIONS.find(
			( id ) => pathname === `/content-hub/skills/${ id }`
		) ?? 'mappings';
	return (
		<>
			<h2>{ __( 'Skill workspace', 'ohmylms' ) }</h2>
			<Section section={ section } />
		</>
	);
}
