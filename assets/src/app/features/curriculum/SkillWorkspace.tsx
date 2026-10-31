import { AiRunsPanel } from './AiRunsPanel';
import { ContentLinksPanel } from './ContentLinksPanel';
import { MappingsPanel } from './MappingsPanel';
import { PlacementPanel } from './PlacementPanel';
import { ProvenancePanel } from './ProvenancePanel';
import { SkillQuizzesPanel } from './SkillQuizzesPanel';
import type { WorkspaceSection } from './workspace';

/**
 * The skill workspace sections, loaded in their own chunk: combined mappings, AI runs, shared content
 * links, domain placement, skill-owned quizzes and provenance review.
 *
 * @param props         Component properties.
 * @param props.section Section to show.
 */
export default function SkillWorkspace( {
	section,
}: {
	section: WorkspaceSection;
} ) {
	switch ( section ) {
		case 'mappings':
			return <MappingsPanel />;
		case 'ai':
			return <AiRunsPanel />;
		case 'content':
			return <ContentLinksPanel />;
		case 'placement':
			return <PlacementPanel />;
		case 'quizzes':
			return <SkillQuizzesPanel />;
		case 'provenance':
			return <ProvenancePanel />;
	}
}
