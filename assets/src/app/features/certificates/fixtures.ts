import type { Certificate, CertificateOptions } from './types';

/** Test fixtures only; production components never import this module. */
export const options: CertificateOptions = {
	orientations: [
		{ value: 'landscape', label: 'Landscape' },
		{ value: 'portrait', label: 'Portrait' },
	],
	sizes: [
		{ value: 'a4', label: 'A4', width_mm: 297, height_mm: 210 },
		{
			value: 'letter',
			label: 'US Letter',
			width_mm: 279.4,
			height_mm: 215.9,
		},
	],
	font_families: [
		{ value: 'serif', label: 'Serif' },
		{ value: 'sans-serif', label: 'Sans-serif' },
	],
	alignments: [
		{ value: 'left', label: 'Left' },
		{ value: 'center', label: 'Center' },
		{ value: 'right', label: 'Right' },
	],
	statuses: [
		{ value: 'draft', label: 'Draft' },
		{ value: 'publish', label: 'Published' },
	],
	placeholders: [
		{
			value: '{student_name}',
			label: 'Student name',
			sample: 'Alex Morgan',
		},
		{ value: '{course_name}', label: 'Course name', sample: 'Intro' },
		{
			value: '{date}',
			label: 'Completion date',
			sample: 'October 30, 2026',
		},
	],
	limits: {
		max_blocks: 20,
		max_text: 500,
		min_font_size: 8,
		max_font_size: 120,
		max_courses: 200,
	},
};

export const certificate: Certificate = {
	id: 7,
	name: 'Completion',
	status: 'draft',
	orientation: 'landscape',
	size: 'a4',
	font_family: 'serif',
	background_color: '#ffffff',
	background_id: 0,
	background_url: '',
	signature: { image_id: 0, x: 65, y: 80, width: 25 },
	signature_url: '',
	blocks: [
		{
			id: 'title',
			text: 'Certificate of Completion',
			x: 10,
			y: 18,
			width: 80,
			font_size: 40,
			align: 'center',
			color: '#1d2327',
			bold: true,
		},
		{
			id: 'student',
			text: '{student_name}',
			x: 10,
			y: 44,
			width: 80,
			font_size: 34,
			align: 'center',
			color: '#1d2327',
			bold: false,
		},
	],
	course_ids: [ 11 ],
	courses: [ { id: 11, title: 'Algebra' } ],
	course_count: 1,
	created_at: '2026-10-30T00:00:00Z',
	updated_at: '2026-10-30T00:00:00Z',
};
