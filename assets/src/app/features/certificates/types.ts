/** Alignment of a text block. */
export type TextAlign = 'left' | 'center' | 'right';

/** Publication state of a template. */
export type CertificateStatus = 'draft' | 'publish';

/** Page orientation. */
export type Orientation = 'landscape' | 'portrait';

/** A text block positioned with percentages of the page. */
export interface TextBlock {
	id: string;
	text: string;
	x: number;
	y: number;
	width: number;
	font_size: number;
	align: TextAlign;
	color: string;
	bold: boolean;
}

/** Signature image placement, in percentages of the page. */
export interface Signature {
	image_id: number;
	x: number;
	y: number;
	width: number;
}

/** Course reference returned with a template. */
export interface CourseRef {
	id: number;
	title: string;
}

/** The writable fields of a template, exactly as the REST API accepts them. */
export interface CertificateInput {
	name: string;
	status: CertificateStatus;
	orientation: Orientation;
	size: string;
	font_family: string;
	background_color: string;
	background_id: number;
	signature: Signature;
	blocks: TextBlock[];
	course_ids: number[];
}

/** A template as returned by the REST API. */
export interface Certificate extends CertificateInput {
	id: number;
	background_url: string;
	signature_url: string;
	courses: CourseRef[];
	course_count: number;
	created_at: string;
	updated_at: string;
}

/** A labelled choice. */
export interface Option {
	value: string;
	label: string;
}

/** A page size choice with its dimensions in landscape. */
export interface SizeOption extends Option {
	width_mm: number;
	height_mm: number;
}

/** A placeholder usable in text blocks. */
export interface Placeholder extends Option {
	sample: string;
}

/** Limits the server enforces. */
export interface Limits {
	max_blocks: number;
	max_text: number;
	min_font_size: number;
	max_font_size: number;
	max_courses: number;
}

/** Choices and limits from `GET /certificates/options`. */
export interface CertificateOptions {
	orientations: Option[];
	sizes: SizeOption[];
	font_families: Option[];
	alignments: Option[];
	statuses: Option[];
	placeholders: Placeholder[];
	limits: Limits;
}

/** Server-rendered preview document. */
export interface Preview {
	html: string;
	width_mm: number;
	height_mm: number;
}

/** A course that can be assigned. */
export interface CourseChoice {
	id: number;
	title: string;
	status: string;
}

/** An image from the media library. */
export interface MediaImage {
	id: number;
	url: string;
	thumbnail_url: string;
	title: string;
}

/** A text block while it is being edited: numbers stay strings so partial typing is not lost. */
export interface BlockForm {
	id: string;
	text: string;
	x: string;
	y: string;
	width: string;
	font_size: string;
	align: TextAlign;
	color: string;
	bold: boolean;
}

/** The editor state. Numbers are strings until the form is validated and converted. */
export interface FormState {
	name: string;
	status: CertificateStatus;
	orientation: Orientation;
	size: string;
	font_family: string;
	background_color: string;
	background_id: number;
	background_url: string;
	signature_image_id: number;
	signature_url: string;
	signature_x: string;
	signature_y: string;
	signature_width: string;
	blocks: BlockForm[];
	courses: CourseRef[];
}

/** Field errors keyed by field path, for example `name` or `blocks.title.text`. */
export type FormErrors = Record< string, string >;
