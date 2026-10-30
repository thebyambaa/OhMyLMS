import { label } from '../beta';
import { format } from '../beta/public';
import { secret } from '../beta/internal';
import { nested } from '../beta/deep';
import type { Shape } from '../beta/types';

export const page: Shape = format( label + secret + nested );
