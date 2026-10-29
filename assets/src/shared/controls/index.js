/**
 * Admin controls kit.
 *
 * Export names keep the `*WP` convention of the recovered application so a feature can switch
 * from `readRuntime().I` to `import * as Controls from '../../shared/controls'` unchanged.
 * Groups: layout and typography, actions and feedback, form fields, pickers. The remaining
 * names re-export controls that have their own shared module.
 */
export * from './layout/index.js';
export * from './actions/index.js';
export * from './forms/index.js';
export * from './pickers/index.js';
export { Avatar as AvatarWP } from '../avatar';
export { CopyToClipboard } from '../copy-to-clipboard';
export { Skeleton as SkeletonWP } from '../skeleton';
export { Switcher as SwitchWP } from '../spinner';
export { Table as TableWP } from '../table';
export { Tooltip as TooltipWP } from '../tooltip';
