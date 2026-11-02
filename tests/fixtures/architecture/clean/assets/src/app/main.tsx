import { register } from '../extensions/registry.mjs';
import { Button } from '../ui/Button';
import { slug } from '../features/authored/helper';
import { routes } from './features/alpha';
import type { Beta } from './features/beta';

register( routes, Button, slug );
export type { Beta };
