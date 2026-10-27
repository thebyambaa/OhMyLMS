import { createContext, useContext } from '@wordpress/element';

/** Shared editor state and actions for the accordion tree, so rows do not need long prop chains. */
export const CurriculumContext = createContext( null );
export const useCurriculum = () => useContext( CurriculumContext );

/** The syllabus workspace: the open syllabus, what is selected, the course it is, and the actions on them. */
export const WorkspaceContext = createContext( null );
export const useWorkspace = () => useContext( WorkspaceContext );
