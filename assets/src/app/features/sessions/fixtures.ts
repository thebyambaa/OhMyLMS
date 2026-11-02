/** Test fixtures only; production components never import this module. */
export const sessionBody = {
	id: 5,
	title: 'Weekly review',
	status: 'publish',
	provider: 'zoom',
	start: '2030-03-04T10:00',
	timezone: 'Europe/Berlin',
	duration: 45,
	state: 'upcoming',
	course: { id: 40, title: 'Algebra' },
	meeting: { created: true, join_url: 'https://zoom.us/j/987' },
	description: 'Chapter 3',
	password: 'abc123',
	options: {
		host_video: true,
		participant_video: true,
		join_before_host: false,
		mute_upon_entry: true,
		auto_record: false,
	},
	can_delete: true,
};
