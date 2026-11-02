/** Install paper span filters before the question blocks register. */
import { registerPaperBlocks } from '../paper/public.mjs';

if ( typeof window !== 'undefined' && window.ohmylmsNativeLesson?.printUrls ) {
	registerPaperBlocks();
}
